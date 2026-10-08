<?php
declare(strict_types=1);

namespace VMM\Multilingual\Integrations\Yootheme;

final class EditorAssets
{
    public static function widgetNames(): array
    {
        global $wp_registered_widgets;
        $names = [];
        foreach ($wp_registered_widgets ?? [] as $id=>$widget) {
            $callback = $widget['callback'] ?? null;
            $object = is_array($callback) ? ($callback[0] ?? null) : null;
            $settings = $object instanceof \WP_Widget ? $object->get_settings() : [];
            $number = $widget['params'][0]['number'] ?? null;
            $names[$id] = (string)(($settings[$number]['title'] ?? '') ?: ($widget['name'] ?? $id));
        }
        return $names;
    }
    public function register(YoothemeAdapter $adapter): void
    {
        add_action('after_setup_theme', function () use ($adapter): void {
            if (!$adapter->compatible()) {
                return;
            }
            \YOOtheme\Event::on('customizer.init', function (): void {
                $root = dirname(__DIR__, 3);
                $url = plugins_url('assets/editor.js', $root . '/vmm-multilingual.php');
                $core = get_template_directory_uri() . '/assets/admin/js/customizer.js?ver=' . wp_get_theme(get_template())->get('Version');
                $config = wp_json_encode(['widgetNames'=>self::widgetNames(), 'siteUrl'=>home_url('/'), 'sourceLocale'=>\VMM\Multilingual\Site\Languages::source(), 'languages'=>\VMM\Multilingual\Site\Languages::names(), 'endpoint' => rest_url('vmm-multilingual/v1/editor/page/'), 'nonce' => wp_create_nonce('wp_rest')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                $script = 'import ' . wp_json_encode($core) . '; import { install } from ' . wp_json_encode($url . '?v=' . filemtime($root . '/assets/editor.js')) . '; install(window.yootheme, ' . $config . ');';
                $metadata = \YOOtheme\app(\YOOtheme\Metadata::class);
                $metadata->set('script:vmm-editor', $script, ['type' => 'module']);
                $metadata->set('style:vmm-editor', ['href' => plugins_url('assets/editor.css', $root . '/vmm-multilingual.php') . '?v=' . filemtime($root . '/assets/editor.css')]);
            }, -50);
        }, 35);
    }
}

