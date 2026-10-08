<?php
declare(strict_types=1);

namespace VMM\Multilingual\Integrations\Yootheme;

use VMM\Multilingual\Core\NodeIdentity;
use VMM\Multilingual\Core\TranslationOverlay;
use VMM\Multilingual\Database\TranslationStore;

final class EditorService
{
    public function __construct(private readonly TranslationStore $store, private readonly YoothemeAdapter $adapter, private readonly FieldPolicy $policy)
    {
    }

    /** Explicitly assign identities, preserving the original tree and a WP revision. */
    public function initialize(int $id): array
    {
        $post = get_post($id);
        $json = \YOOtheme\Builder\Wordpress\PostHelper::matchContent($post->post_content);
        $tree = $json ? json_decode($json, true, 100, JSON_THROW_ON_ERROR) : ['type' => 'layout', 'children' => []];
        $identified = (new NodeIdentity())->initialize($tree);
        if ($tree !== $identified && $json) {
            wp_save_post_revision($id);
            $content = str_replace($json, wp_json_encode($identified, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $post->post_content);
            $result = wp_update_post(wp_slash(['ID' => $id, 'post_content' => $content]), true);
            if (is_wp_error($result)) {
                throw new \RuntimeException($result->get_error_message());
            }
        }
        return $identified;
    }

    public function open(int $id, string $locale): array
    {
        return $this->locked($id, function () use ($id, $locale): array {
            return $this->document($id, $locale, $this->initialize($id));
        });
    }

    private function document(int $id, string $locale, array $master): array
    {
        $document = $this->store->get($id, $locale);
        $policy = $this->policy->all();
        $post = get_post($id);
        $source = \VMM\Multilingual\Site\Languages::source();
        $sourceRecords = $this->store->get($id, $source)['records'];
        $baseline = (new TranslationOverlay($policy))->apply($master, $sourceRecords);
        return [
            'id' => $id, 'locale' => $locale, 'master' => $baseline, 'source_locale' => $source,
            'tree' => (new TranslationOverlay($policy))->apply($baseline, $document['records']),
            'revision' => $document['revision'], 'master_hash' => hash('sha256', $post->post_content . wp_json_encode($sourceRecords) . $source),
            'records' => $document['records'], 'fields' => $policy, 'resources' => $this->policy->resources(),
            'collision' => \YOOtheme\Builder\Wordpress\PostHelper::getCollision($post),
        ];
    }

    public function save(int $id, array $input): array
    {
        return $this->locked($id, function () use ($id, $input): array {
            global $wpdb;
            $locale = $input['locale'];
            $master = $this->initialize($id);
            $current = $this->document($id, $locale, $master);
            if (!hash_equals($current['master_hash'], (string) ($input['master_hash'] ?? '')) || $current['revision'] !== ($input['revision'] ?? null)) {
                throw new \RuntimeException('Seite oder Übersetzung wurde inzwischen geändert. Bitte Seite neu laden.');
            }
            if (!is_array($input['tree'] ?? null)) {
                throw new \InvalidArgumentException('Der Builder-Baum fehlt.');
            }
            $edited = $this->policy->sanitize($input['tree']);
            $edited = (new NodeIdentity())->initialize($edited);
            $records = $current['records'];
            if (isset($input['source_locale']) && $input['source_locale'] !== \VMM\Multilingual\Site\Languages::source()) throw new \RuntimeException('Ausgangssprache geändert. Bitte Editor neu laden.');
            {
                $split = (new TranslationOverlay($current['fields']))->split($master, $current['tree'], $edited, $records);
                $next = $split['master'];
                $records = $split['records'];
                $nodes=[];
                $index=function(array $node)use(&$index,&$nodes):void{$nodes[$node['vmm_id']]=$node;foreach($node['children']??[] as $child)$index($child);};
                $index($edited);
                $editedNodes=$nodes;$nodes=[];$index($current['master']);$sourceNodes=$nodes;$nodes=$editedNodes;
                $resources=$this->policy->resources();
                foreach(['inherit','overrides'] as $mode) {
                    if(!is_array($input[$mode]??[]))throw new \InvalidArgumentException('Ungültige Übernahme.');
                    foreach($input[$mode]??[] as $nodeId=>$fields) {
                        if(!isset($nodes[$nodeId])||!is_array($fields))throw new \InvalidArgumentException('Ungültiges Element.');
                        foreach($fields as $field) {
                            if(!is_string($field)||!isset($resources[$nodes[$nodeId]['type']][$field])||array_key_exists($field,$nodes[$nodeId]['source']['props']??[]))throw new \InvalidArgumentException('Ungültiges abweichendes Feld.');
                            if($mode==='inherit')unset($records[$nodeId][$field]);
                            else $records[$nodeId][$field]=['mode'=>'translate','value'=>$nodes[$nodeId]['props'][$field]??'','source_hash'=>TranslationOverlay::sourceHash($sourceNodes[$nodeId]['props'][$field]??'')];
                        }
                    }
                }
            }
            $next = $this->stripOrigins($next);
            $builder = \YOOtheme\app(\YOOtheme\Builder::class);
            $json = wp_json_encode($next, JSON_THROW_ON_ERROR);
            $full = wp_json_encode($builder->withParams(['context' => 'save'])->load($json), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $intro = $builder->withParams(['context' => 'content'])->render($json);
            $content = ($next['children'] ?? []) ? "$intro\n<!--more-->\n<!-- $full -->" : '';
            if ($next === $master) {
                $content = get_post($id)->post_content;
            }
            $wpdb->query('START TRANSACTION');
            try {
                {
                    $this->store->save($id, $locale, $current['revision'], $records);
                }
                if ($content !== get_post($id)->post_content) {
                    $result = wp_update_post(wp_slash(['ID' => $id, 'post_content' => $content]), true);
                    if (is_wp_error($result)) {
                        throw new \RuntimeException($result->get_error_message());
                    }
                }
                update_post_meta($id, '_edit_last', get_current_user_id());
                $wpdb->query('COMMIT');
            } catch (\Throwable $error) {
                $wpdb->query('ROLLBACK');
                clean_post_cache($id);
                throw $error;
            }
            clean_post_cache($id);
            return ['id' => $id, 'collision' => \YOOtheme\Builder\Wordpress\PostHelper::getCollision(get_post($id)), 'vmm' => $this->document($id, $locale, $next)];
        });
    }

    /** Element edits share the overlay store, without resaving/normalizing the layout. */
    public function saveFields(int $id,array $input): array
    {
        return $this->locked($id,function()use($id,$input):array{
            $locale=$input['locale']??'';
            if(!is_string($locale)||!isset(\VMM\Multilingual\Site\Languages::all()[$locale])||$locale===\VMM\Multilingual\Site\Languages::source())throw new \InvalidArgumentException('Ungültige Übersetzungssprache.');
            $current=$this->document($id,$locale,$this->initialize($id));
            if(($input['source_locale']??'')!==$current['source_locale']||($input['revision']??-1)!==$current['revision']||!hash_equals($current['master_hash'],(string)($input['master_hash']??'')))throw new \RuntimeException('Seite oder Übersetzung inzwischen geändert. Bitte neu laden.');
            $nodes=[];$walk=function($node)use(&$walk,&$nodes){$nodes[$node['vmm_id']]=$node;foreach($node['children']??[] as $child)$walk($child);};$walk($current['master']);
            $nodeId=$input['node']??'';$node=$nodes[$nodeId]??null;$changes=$input['fields']??null;
            if(!$node||!is_array($changes)||!$changes)throw new \InvalidArgumentException('Element oder Felder fehlen.');
            $records=$current['records'];
            foreach($changes as $field=>$entry){
                if(!in_array($field,$current['fields'][$node['type']]??[],true)||array_key_exists($field,$node['source']['props']??[])||!is_array($entry)||!in_array($entry['mode']??'',['inherit','custom'],true)||!is_string($entry['value']??null))throw new \InvalidArgumentException('Ungültiges Elementfeld.');
                if($entry['mode']==='inherit'){unset($records[$nodeId][$field]);continue;}
                $edited=$node;$edited['props'][$field]=$entry['value'];$edited=$this->policy->sanitize($edited);
                $records[$nodeId][$field]=['mode'=>'translate','value'=>$edited['props'][$field],'source_hash'=>TranslationOverlay::sourceHash($node['props'][$field]??'')];
            }
            $this->store->save($id,$locale,$current['revision'],$records);
            return $this->document($id,$locale,$this->initialize($id));
        });
    }

    private function stripOrigins(array $node): array
    {
        unset($node['vmm_origin_id']);
        foreach ($node['children'] ?? [] as $i => $child) {
            $node['children'][$i] = $this->stripOrigins($child);
        }
        return $node;
    }

    private function locked(int $id, callable $operation): array
    {
        global $wpdb;
        if (!$this->adapter->compatible()) {
            throw new \RuntimeException('Nicht unterstützte YOOtheme-Version.');
        }
        $key = 'vmm_' . get_current_blog_id() . '_' . $id;
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $key)) !== 1) {
            throw new \RuntimeException('Seite ist gerade gesperrt. Bitte erneut versuchen.');
        }
        try {
            clean_post_cache($id);
            wp_cache_delete('vmm_source_language','options');
            return $operation();
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $key));
        }
    }
}
