<?php
declare(strict_types=1);
namespace VMM\Multilingual\Site;

use VMM\Multilingual\Core\NodeIdentity;
use VMM\Multilingual\Core\TranslationOverlay;
use VMM\Multilingual\Database\TranslationStore;
use VMM\Multilingual\Integrations\Yootheme\FieldPolicy;

/** Preserve the former source before changing which language supplies fallback values. */
final class SourceLanguage
{
    public static function change(string $locale): void
    {
        $old = Languages::source();
        if ($old === $locale) return;
        if (!isset(Languages::all()[$locale])) throw new \InvalidArgumentException('Ausgangssprache muss aktiv sein.');
        foreach(Languages::all() as $language=>$profile) {
            if($language!==$locale && get_page_by_path($profile['slug'])) throw new \InvalidArgumentException('Sprachpfad bereits belegt: '.$profile['slug']);
        }
        global $wpdb;
        $locks = [];
        $store = new TranslationStore();
        $pages = get_posts(['post_type'=>ContentTranslations::types(),'post_status'=>'any','numberposts'=>-1]);
        try {
            $sourceLock='vmm_source_'.get_current_blog_id();
            if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,5)',$sourceLock))!==1)throw new \RuntimeException('Ausgangssprache wird gerade geändert.');
            $locks[]=$sourceLock;
            wp_cache_delete('vmm_source_language','options');
            $old=Languages::source();
            if($old===$locale)return;
            $stringLock='vmm_strings_'.get_current_blog_id();
            if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,5)',$stringLock))!==1)throw new \RuntimeException('Plugin-Inhalte werden gerade gespeichert.');
            $locks[]=$stringLock;
            // Same locks as editor saves: a source change cannot race an open/save operation.
            foreach ($pages as $page) {
                $key = 'vmm_'.get_current_blog_id().'_'.$page->ID;
                if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,5)', $key)) !== 1) throw new \RuntimeException('Eine Seite wird gerade gespeichert. Bitte erneut versuchen.');
                $locks[] = $key;
            }
            $wpdb->query('START TRANSACTION');
            $strings=(array)get_option(PluginTranslations::OPTION,[]);$catalogs=new PluginTranslations();
            foreach($strings as $plugin=>&$document){
                if(!isset(PluginTranslations::plugins()[$plugin]))continue;
                foreach($catalogs->catalog($plugin,true) as $key=>$row){$sourceValue=$row['source'];$newValue=PluginTranslations::value($plugin,$key,$sourceValue,$locale);foreach([$old=>$sourceValue,$locale=>$newValue] as $language=>$value){$record=$document['locales'][$language][$key]??[];$record['value']=$value;$record['source_hash']=ContentTranslations::hash([$sourceValue]);$document['locales'][$language][$key]=$record;}}
                $document['revision']=(int)($document['revision']??0)+1;
            }unset($document);if($strings)update_option(PluginTranslations::OPTION,$strings,false);
            $fields = function_exists('YOOtheme\\app') ? (new FieldPolicy())->all() : [];
            foreach ($pages as $page) {
                $native=(array)get_post_meta($page->ID,ContentTranslations::KEY,true);
                $oldValues=ContentTranslations::effective($page->ID,$old);
                $newValues=ContentTranslations::effective($page->ID,$locale);
                foreach([$old=>$oldValues,$locale=>$newValues] as $language=>$values)foreach($values as $key=>$value)$native['locales'][$language]['fields'][$key]=['value'=>$value,'source_hash'=>ContentTranslations::hash([$value])];
                $native['revision']=(int)($native['revision']??0)+1;
                update_post_meta($page->ID,ContentTranslations::KEY,wp_slash($native));
                $json = function_exists('YOOtheme\\app') ? \YOOtheme\Builder\Wordpress\PostHelper::matchContent($page->post_content) : null;
                if ($json) {
                    $tree = json_decode($json,true,100,JSON_THROW_ON_ERROR);
                    $identified = (new NodeIdentity())->initialize($tree);
                    if ($tree !== $identified) {
                        wp_save_post_revision($page->ID);
                        $result = wp_update_post(wp_slash(['ID'=>$page->ID,'post_content'=>str_replace($json,wp_json_encode($identified,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$page->post_content)]),true);
                        if (is_wp_error($result)) throw new \RuntimeException($result->get_error_message());
                    }
                    $doc = $store->get($page->ID,$old);
                    $effective = (new TranslationOverlay($fields))->apply($identified,$doc['records']);
                    $records = $doc['records'];
                    $walk = function(array $node) use (&$walk,&$records,$fields):void {
                        foreach ($fields[$node['type']]??[] as $field) if (!array_key_exists($field,$node['source']['props']??[])) {
                            $value = $node['props'][$field]??'';
                            if (is_string($value)) $records[$node['vmm_id']][$field]=['mode'=>'translate','value'=>$value,'source_hash'=>TranslationOverlay::sourceHash($value)];
                        }
                        foreach ($node['children']??[] as $child) $walk($child);
                    };
                    $walk($effective);
                    $store->save($page->ID,$old,$doc['revision'],$records);
                }
                $meta=(array)get_post_meta($page->ID,PageMetadata::KEY,true);
                foreach(PageMetadata::FIELDS as $field=>$label) {
                    $fallback=match($field){'title'=>$page->post_title,'slug'=>$page->post_name,'seo_title'=>get_post_meta($page->ID,'_yoast_wpseo_title',true),'description'=>get_post_meta($page->ID,'_yoast_wpseo_metadesc',true),default=>''};
                    if(empty($meta['locales'][$old][$field]))$meta['locales'][$old][$field]=(string)$fallback;
                }
                $meta['revision']=(int)($meta['revision']??0)+1;
                update_post_meta($page->ID,PageMetadata::KEY,wp_slash($meta));
            }
            foreach(get_posts(['post_type'=>'nav_menu_item','post_status'=>'any','numberposts'=>-1]) as $item) {
                $data=(array)get_post_meta($item->ID,'_vmm_menu',true);
                $effective=MenuTranslations::translation($data,$old);
                $effective['label']=$effective['label']?:$item->post_title;
                $effective['url']=$effective['url']?:(string)get_post_meta($item->ID,'_menu_item_url',true);
                $effective['mode']=$effective['mode']==='hide'?'hide':'custom';
                $data['locales'][$old]=$effective;
                update_post_meta($item->ID,'_vmm_menu',wp_slash($data));
            }
            update_option('vmm_source_language',$locale,false);
            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            wp_cache_flush();
            throw $e;
        } finally {
            foreach($locks as $key)$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$key));
        }
    }
}
