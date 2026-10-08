<?php
/**
 * Plugin Name: VMM Multilingual
 * Description: Sprachabhängige Inhalte für einen gemeinsamen YOOtheme-Master. Technische Testversion.
 * Version: 0.10.0
 * Requires PHP: 8.1
 * Requires at least: 6.1
 * Text Domain: vmm-multilingual
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
spl_autoload_register(static function (string $class): void {
    $prefix = 'VMM\\Multilingual\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
register_activation_hook(__FILE__, [VMM\Multilingual\Database\TranslationStore::class, 'install']);
add_action('plugins_loaded', static function (): void {
    $store = new VMM\Multilingual\Database\TranslationStore();
    $adapter = new VMM\Multilingual\Integrations\Yootheme\YoothemeAdapter($store);
    $adapter->register();
    $policy = new VMM\Multilingual\Integrations\Yootheme\FieldPolicy();
    $editor = new VMM\Multilingual\Integrations\Yootheme\EditorService($store, $adapter, $policy);
    (new VMM\Multilingual\Rest\EditorController($editor))->register();
    (new VMM\Multilingual\Integrations\Yootheme\EditorAssets())->register($adapter);
    $urls = new VMM\Multilingual\Site\LanguageUrls();
    VMM\Multilingual\Site\Languages::register();
    $urls->register();
    (new VMM\Multilingual\Site\PageMetadata())->register($urls);
    (new VMM\Multilingual\Site\Switcher($urls))->register();
    (new VMM\Multilingual\Site\Alternatives())->register($urls);
    (new VMM\Multilingual\Site\MenuTranslations())->register($urls);
    (new VMM\Multilingual\Admin\AlternativesScreen())->register();
    (new VMM\Multilingual\Admin\TranslationScreen($urls))->register();
});


