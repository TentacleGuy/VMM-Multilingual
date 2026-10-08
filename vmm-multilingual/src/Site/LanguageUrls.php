<?php
declare(strict_types=1);
namespace VMM\Multilingual\Site;

final class LanguageUrls
{
    private static int $rawDepth = 0;
    public function locale(): string
    {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) return Languages::source();
        $languages = Languages::all();
        if (isset($_GET['vmm_lang']) && is_string($_GET['vmm_lang']) && isset($languages[$_GET['vmm_lang']])) return $_GET['vmm_lang'];
        $settings = Settings::get(); $path = $this->requestPath();
        foreach ($languages as $locale=>$language) {
            if ($locale === Languages::source()) continue;
            if ($settings['url_mode'] === 'query' && ($_GET['lang'] ?? '') === $language['slug']) return $locale;
            if ($settings['url_mode'] === 'subdomain' && Languages::host($locale) !== '' && strtolower((string)($_SERVER['HTTP_HOST'] ?? '')) === Languages::host($locale)) return $locale;
            if ($settings['url_mode'] === 'directory' && ($path === $language['slug'] || str_starts_with($path,$language['slug'].'/'))) return $locale;
        }
        return Languages::source();
    }

    private function requestPath(): string
    {
        $path = trim((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
        $base = trim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');
        if ($base !== '' && ($path === $base || str_starts_with($path, $base.'/'))) $path = ltrim(substr($path, strlen($base)), '/');
        return $path;
    }

    public function rawUrl(int $id): string
    {
        self::$rawDepth++;
        try { return (string) get_permalink($id); } finally { self::$rawDepth--; }
    }

    /** Translate inherited links to local pages; preserve fragments and external destinations. */
    public function localizeLink(string $link, string $locale): string
    {
        if ($link === '' || str_starts_with($link, '#') || preg_match('/^(mailto:|tel:)/i', $link)) return $link;
        $absolute = str_starts_with($link, '/') && !str_starts_with($link, '//') ? home_url($link) : $link;
        $parts = wp_parse_url($absolute);
        $home = wp_parse_url(home_url('/'));
        if (!$parts || strtolower($parts['host'] ?? '') !== strtolower($home['host'] ?? '')) return $link;
        $id = url_to_postid($absolute);
        if (!$id || !in_array(get_post_type($id), ContentTranslations::types(), true)) return $link;
        $translated = $this->url($id, $locale);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            unset($query['lang'], $query['vmm_lang'], $query['page_id']);
            if ($query) $translated = add_query_arg($query, $translated);
        }
        return $translated . (isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }

    public function path(int $id, string $locale, array $overrides = []): string
    {
        if (get_post_type($id) !== 'page') {
            $path=trim((string)wp_parse_url($this->rawUrl($id),PHP_URL_PATH),'/');
            $base=trim((string)wp_parse_url(home_url('/'),PHP_URL_PATH),'/');
            if($base!==''&&str_starts_with($path,$base.'/'))$path=substr($path,strlen($base)+1);
            $slug=PageMetadata::value($id,$locale,'slug');
            if($slug!=='')$path=preg_replace_callback('~(^|/)'.preg_quote(get_post($id)->post_name,'~').'(?=/|$)~',static fn($match)=>$match[1].$slug,$path);
            return $path;
        }
        if ((int) get_option('page_on_front') === $id && get_option('show_on_front') === 'page') return '';
        $segments = [];
        $slug = static function(int $page) use($locale,$overrides):string {
            if (!isset($overrides[$page])) return PageMetadata::value($page,$locale,'slug') ?: get_post($page)->post_name;
            $locales=$overrides[$page]['locales'];
            return ($locales[$locale]['slug']??'') ?: (($locales[Languages::source()]['slug']??'') ?: get_post($page)->post_name);
        };
        foreach (array_reverse(get_post_ancestors($id)) as $parent) $segments[] = $slug((int)$parent);
        $segments[] = $slug($id);
        return implode('/', $segments);
    }

    public function url(int $id, string $locale): string
    {
        $settings = Settings::get();
        $path = $this->path($id, $locale);
        $url = home_url('/'.($path === '' ? '' : user_trailingslashit($path)));
        if (!get_option('permalink_structure')) $url = add_query_arg(get_post_type($id)==='page'?'page_id':'p', $id, home_url('/'));
        if ($locale === Languages::source()) return $url;
        if ($settings['url_mode'] === 'query') return add_query_arg('lang', Languages::all()[$locale]['slug'], $url);
        if ($settings['url_mode'] === 'directory') return !get_option('permalink_structure')?add_query_arg(get_post_type($id)==='page'?'page_id':'p',$id,home_url('/'.Languages::all()[$locale]['slug'].'/')):home_url('/'.Languages::all()[$locale]['slug'].'/'.($path === '' ? '' : user_trailingslashit($path)));
        $parts = wp_parse_url($url);
        return ($parts['scheme'] ?? 'https').'://'.Languages::host($locale).($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    public function register(): void
    {
        add_action('parse_request', function (\WP $wp): void {
            if (is_admin() || isset($_GET['rest_route']) || str_starts_with($this->requestPath(), 'wp-json')) return;
            $locale = $this->locale();
            $path = $this->requestPath();
            $code = Languages::all()[$locale]['slug'];
            if ($locale !== Languages::source() && Settings::get()['url_mode'] === 'directory' && ($path === $code || str_starts_with($path, $code.'/'))) $path = ltrim(substr($path, strlen($code)), '/');
            if ($path === '' && $locale !== Languages::source() && Settings::get()['url_mode'] === 'directory') {
                if(isset($wp->query_vars['p'])||isset($wp->query_vars['page_id']))return;
                $wp->query_vars = get_option('show_on_front') === 'page' ? ['page_id'=>(int) get_option('page_on_front')] : [];
                return;
            }
            if ($path === '' || isset($wp->query_vars['page_id']) || isset($wp->query_vars['p'])) return;
            foreach (get_posts(['post_type'=>ContentTranslations::types(),'post_status'=>['publish','private','draft','pending','future'],'numberposts'=>-1]) as $page) {
                if ($this->path($page->ID, $locale) === $path) {
                    $wp->query_vars = array_intersect_key($wp->query_vars, array_flip(['preview','preview_id','preview_nonce','paged','page']));
                    $wp->query_vars[$page->post_type==='page'?'page_id':'p'] = $page->ID;
                    if($page->post_type!=='page')$wp->query_vars['post_type']=$page->post_type;
                    return;
                }
            }
        }, 20);
        add_filter('page_link', function (string $url, int $id): string {
            return self::$rawDepth===0 && !is_admin() && !(defined('REST_REQUEST') && REST_REQUEST) ? $this->url($id, $this->locale()) : $url;
        }, 20, 2);
        foreach(['post_link','post_type_link'] as $hook)add_filter($hook,function($url,$post){return self::$rawDepth===0&&!is_admin()&&!(defined('REST_REQUEST')&&REST_REQUEST)&&in_array($post->post_type,ContentTranslations::types(),true)?$this->url($post->ID,$this->locale()):$url;},20,2);
        add_filter('redirect_canonical', function ($redirect) {
            return is_singular(ContentTranslations::types()) && ($this->locale() !== Languages::source() || PageMetadata::value(get_queried_object_id(), Languages::source(), 'slug') !== '') ? false : $redirect;
        });
        add_action('template_redirect', function (): void {
            if ($this->locale() !== Languages::source()) { if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true); nocache_headers(); }
        }, 0);
        add_action('wp_head', function (): void {
            if (!is_singular(ContentTranslations::types())) return;
            $id = get_queried_object_id();
            foreach (array_combine(array_keys(Languages::all()),array_map(static fn($locale)=>str_replace('_','-',$locale),array_keys(Languages::all()))) as $locale=>$code) if(ContentTranslations::available($id,$locale)) echo '<link rel="alternate" hreflang="'.esc_attr($code).'" href="'.esc_url($this->url($id, $locale)).'">' . "\n";
            echo '<link rel="alternate" hreflang="x-default" href="'.esc_url($this->url($id, Languages::source())).'">' . "\n";
        });
    }
}
