<?php
declare(strict_types=1);
namespace VMM\Multilingual\Site;

final class PageMetadata
{
    public const KEY = '_vmm_page_metadata';
    public const FIELDS = ['title'=>'Seitentitel','slug'=>'Slug','seo_title'=>'SEO-Titel','description'=>'Meta Description','social_title'=>'Social-Media-Titel','social_description'=>'Social-Media-Beschreibung'];
    public static function value(int $id, string $locale, string $field): string
    {
        $content = get_post_meta($id, ContentTranslations::KEY, true);
        foreach (array_unique([$locale, Languages::source()]) as $language) {
            if (isset($content['locales'][$language]['fields'][$field]['value'])) return (string)$content['locales'][$language]['fields'][$field]['value'];
        }
        $data = get_post_meta($id, self::KEY, true);
        return (string) (($data['locales'][$locale][$field] ?? '') ?: ($data['locales'][Languages::source()][$field] ?? ''));
    }
    public static function save(int $id, array $input, int $revision, LanguageUrls $urls): void
    {
        global $wpdb;
        $lock = 'vmm_metadata_'.get_current_blog_id();
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,5)', $lock)) !== 1) throw new \RuntimeException('Einstellungen sind gerade gesperrt.');
        try {
            wp_cache_delete($id, 'post_meta');
            $old = get_post_meta($id, self::KEY, true);
            if ((int) ($old['revision'] ?? 0) !== $revision) throw new \RuntimeException('Die Seitendaten wurden inzwischen geändert. Bitte neu laden.');
            $next = ['revision'=>$revision+1,'locales'=>$old['locales']??[]];
            foreach (array_keys(Languages::all()) as $locale) {
                foreach (self::FIELDS as $field=>$label) {
                    $value = $input[$locale][$field] ?? '';
                    if (!is_string($value)) throw new \InvalidArgumentException('Ungültiges Feld.');
                    if ($field === 'slug' && strpbrk($value, '/?#') !== false) throw new \InvalidArgumentException('Der Slug darf nur einen einzelnen URL-Abschnitt enthalten.');
                    $next['locales'][$locale][$field] = $field === 'slug' ? sanitize_title($value) : sanitize_text_field($value);
                }
                $slug = $next['locales'][$locale]['slug'];
                if ($slug !== '' && in_array($slug, array_merge(array_column(Languages::all(),'slug'), ['wp-admin','wp-json','feed','wp-login.php']), true)) throw new \InvalidArgumentException('Dieser Slug ist reserviert.');
            }
                foreach (array_keys(Languages::all()) as $locale) {
                    $paths = [];
                    foreach (get_posts(['post_type'=>'page','post_status'=>'any','numberposts'=>-1]) as $page) {
                        $path = $urls->path($page->ID, $locale, [$id=>$next]);
                        if (isset($paths[$path])) throw new \InvalidArgumentException('Slug-Konflikt: '.$path.' ('.$locale.'). Bitte einen anderen Slug wählen.');
                        $paths[$path] = true;
                    }
                }
            if (!update_post_meta($id, self::KEY, wp_slash($next))) throw new \RuntimeException('Seitendaten konnten nicht gespeichert werden.');
        } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
    }

    public function register(LanguageUrls $urls): void
    {
        $get = static function (string $field) use ($urls): string { return !is_admin() && is_singular('page') ? self::value(get_queried_object_id(), $urls->locale(), $field) : ''; };
        add_filter('the_title', static function ($title, $id) use ($urls) { return !is_admin() && !(defined('REST_REQUEST') && REST_REQUEST) && get_post_type($id) === 'page' ? (self::value((int) $id, $urls->locale(), 'title') ?: $title) : $title; }, 20, 2);
        add_filter('document_title_parts', static function (array $parts) use ($get): array { if ($title = $get('title')) $parts['title'] = $title; return $parts; });
        add_filter('pre_get_document_title', static fn($title) => $get('seo_title') ?: $title, 30);
        foreach (['wpseo_title'=>'seo_title','wpseo_metadesc'=>'description','wpseo_opengraph_title'=>'social_title','wpseo_twitter_title'=>'social_title','wpseo_opengraph_desc'=>'social_description','wpseo_twitter_description'=>'social_description'] as $hook=>$field) add_filter($hook, static fn($value) => $get($field) ?: $value, 30);
        foreach (['wpseo_canonical','wpseo_opengraph_url'] as $hook) add_filter($hook, static fn($value) => is_singular('page') ? $urls->url(get_queried_object_id(), $urls->locale()) : $value, 30);
        add_filter('wpseo_opengraph_locale', static fn($value) => is_singular('page') ? $urls->locale() : $value);
        add_filter('get_canonical_url', static fn($url, $post) => $post->post_type === 'page' ? $urls->url($post->ID, $urls->locale()) : $url, 20, 2);
        add_action('wp_head', static function () use ($get): void {
            if (defined('WPSEO_VERSION')) return;
            if ($description = $get('description')) echo '<meta name="description" content="'.esc_attr($description).'">'."\n";
            foreach (['social_title'=>['og:title','twitter:title'],'social_description'=>['og:description','twitter:description']] as $field=>$tags) {
                if ($value = $get($field)) foreach ($tags as $tag) echo '<meta '.(str_starts_with($tag,'og:') ? 'property' : 'name').'="'.esc_attr($tag).'" content="'.esc_attr($value).'">'."\n";
            }
        });
    }
}
