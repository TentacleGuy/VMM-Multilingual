<?php
declare(strict_types=1);

namespace VMM\Multilingual\Database;

use RuntimeException;

/** One versioned overlay document per page/locale; no layout data is stored. */
final class TranslationStore
{
    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $wpdb->prefix . 'vmm_page_overlays';
        dbDelta("CREATE TABLE $table (
            page_id bigint(20) unsigned NOT NULL,
            locale varchar(20) NOT NULL,
            revision bigint(20) unsigned NOT NULL DEFAULT 1,
            records longtext NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (page_id,locale)
        ) {$wpdb->get_charset_collate()};");
        update_option('vmm_db_version', '1', false);
    }

    public function get(int $page, string $locale): array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT revision, records FROM {$wpdb->prefix}vmm_page_overlays WHERE page_id=%d AND locale=%s", $page, $locale), ARRAY_A);
        return $row ? ['revision' => (int) $row['revision'], 'records' => json_decode($row['records'], true, 512, JSON_THROW_ON_ERROR)] : ['revision' => 0, 'records' => []];
    }

    public function save(int $page, string $locale, int $revision, array $records): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vmm_page_overlays';
        $json = wp_json_encode($records, JSON_THROW_ON_ERROR);
        $now = current_time('mysql', true);
        $result = $revision === 0
            ? $wpdb->insert($table, ['page_id' => $page, 'locale' => $locale, 'revision' => 1, 'records' => $json, 'updated_at' => $now], ['%d', '%s', '%d', '%s', '%s'])
            : $wpdb->query($wpdb->prepare("UPDATE $table SET records=%s, revision=revision+1, updated_at=%s WHERE page_id=%d AND locale=%s AND revision=%d", $json, $now, $page, $locale, $revision));
        if ($result !== 1) {
            throw new RuntimeException('Speicherkonflikt oder Datenbankfehler. Bitte neu laden und erneut bearbeiten.');
        }
        clean_post_cache($page);
        do_action('vmm_multilingual_translation_saved', $page, $locale);
    }
}
