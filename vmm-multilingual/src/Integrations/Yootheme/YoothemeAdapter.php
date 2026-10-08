<?php
declare(strict_types=1);

namespace VMM\Multilingual\Integrations\Yootheme;

use RuntimeException;
use VMM\Multilingual\Database\TranslationStore;

final class YoothemeAdapter
{
    private array $cache = [];
    private array $targets = [];
    private ?FieldPolicy $policy = null;

    public function __construct(private readonly TranslationStore $store)
    {
    }

    public function compatible(): bool
    {
        return wp_get_theme(get_template())->get('Version') === PhaseOneFields::VERSION && function_exists('YOOtheme\\app');
    }

    public function register(): void
    {
        add_action('after_setup_theme', function (): void {
            if (!$this->compatible()) {
                return;
            }
            \YOOtheme\app()->extend(\YOOtheme\Builder::class, function (\YOOtheme\Builder $builder): void {
                $builder->addTransform('prerender', [$this, 'overlay'], 0);
                $module = $builder->getType('module');
                $render = $module->transforms['render'] ?? null;
                if (is_callable($render)) {
                    $module->transforms['render'] = static fn(...$args) => \VMM\Multilingual\Site\Alternatives::withBuilderWidget(static fn() => $render(...$args));
                }
            });
        }, 30);
        add_filter('language_attributes', function (string $attributes): string {
            return !is_admin() ? preg_replace('/lang="[^"]*"/', 'lang="'.esc_attr(str_replace('_','-',$this->locale())).'"', $attributes) : $attributes;
        });
        add_action('template_redirect', function (): void {
            if (isset($_GET['vmm_lang'])) {
                if (!defined('DONOTCACHEPAGE')) {
                    define('DONOTCACHEPAGE', true);
                }
                nocache_headers();
            }
        }, 0);
    }

    public function locale(): string
    {
        return (new \VMM\Multilingual\Site\LanguageUrls())->locale();
    }
    public function overlay(object $node, array $params): void
    {
        // Never project translated values into the native editor until its save adapter is ready.
        if (\YOOtheme\app(\YOOtheme\Config::class)->get('app.isCustomizer') || empty($params['post']->ID)) {
            return;
        }
        $page = (int) $params['post']->ID;
        if (!isset($this->cache[$page])) {
            $source = $this->store->get($page, \VMM\Multilingual\Site\Languages::source())['records'];
            $target = $this->targets[$page] = $this->store->get($page, $this->locale())['records'];
            foreach($target as $id=>&$fields)foreach($fields as $field=>$record)if(($record['mode']??'')==='inherit')unset($fields[$field]);unset($fields);
            $this->cache[$page] = array_replace_recursive($source, $target);
        }
        $resources=($this->policy ??= new FieldPolicy())->resources();
        foreach (($this->policy ??= new FieldPolicy())->all()[$node->type] ?? [] as $field) {
            if (isset($node->source->props->$field)) {
                continue;
            }
            $record = $this->cache[$page][$node->vmm_id ?? ''][$field] ?? null;
            if (($record['mode'] ?? '') === 'translate' && isset($record['value']) && is_string($record['value'])) {
                $node->props[$field] = apply_filters('vmm_multilingual_translate_value', $record['value'], $page, $node->vmm_id, $field, $this->locale());
            }
        }
        foreach ($resources[$node->type] ?? [] as $field=>$kind) {
            if ($kind !== 'link' || isset($node->source->props->$field)) continue;
            $target = $this->targets[$page][$node->vmm_id ?? ''][$field] ?? null;
            if (!$target || ($target['mode'] ?? '') === 'inherit') {
                $node->props[$field] = (new \VMM\Multilingual\Site\LanguageUrls())->localizeLink((string)($node->props[$field] ?? ''), $this->locale());
            }
        }
    }

    public function tree(int $page): array
    {
        if (!$this->compatible()) {
            throw new RuntimeException('Diese Testversion unterstützt ausschließlich YOOtheme Pro 5.0.46.');
        }
        $post = get_post($page);
        $json = $post ? \YOOtheme\Builder\Wordpress\PostHelper::matchContent($post->post_content) : null;
        if (!$json) {
            throw new RuntimeException('Diese Seite enthält keinen YOOtheme-Builder-Baum.');
        }
        return json_decode($json, true, 100, JSON_THROW_ON_ERROR);
    }

    public function fields(array $tree): array
    {
        $fields = [];
        $seen = [];
        $walk = function (array $node, int $depth = 0) use (&$walk, &$fields, &$seen): void {
            if ($depth > 90) {
                throw new RuntimeException('Builder-Baum zu tief.');
            }
            $id = $node['vmm_id'] ?? '';
            if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/D', $id) || isset($seen[$id])) {
                throw new RuntimeException('Fehlende oder doppelte Element-ID. Bitte zunächst die vorbereitete VMM-Testseite verwenden.');
            }
            $seen[$id] = true;
            foreach (($this->policy ??= new FieldPolicy())->all()[$node['type']] ?? [] as $field) {
                if (array_key_exists($field, $node['source']['props'] ?? [])) {
                    continue;
                }
                $fields[] = ['id' => $id, 'type' => $node['type'], 'field' => $field, 'master' => (string) ($node['props'][$field] ?? '')];
            }
            foreach ($node['children'] ?? [] as $child) {
                $walk($child, $depth + 1);
            }
        };
        $walk($tree);
        return $fields;
    }

    public function switcher(): string
    {
        return (new \VMM\Multilingual\Site\Switcher(new \VMM\Multilingual\Site\LanguageUrls()))->render();
    }
}
