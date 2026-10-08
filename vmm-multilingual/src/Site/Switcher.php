<?php
declare(strict_types=1);
namespace VMM\Multilingual\Site;

final class Switcher
{
    public function __construct(private readonly LanguageUrls $urls) {}
    public function render(array $attributes = []): string
    {
        if (!is_singular(ContentTranslations::types())) return '';
        $settings = Settings::get();
        if(is_string($attributes['components']??null))$attributes['components']=array_map('trim',explode(',',$attributes['components']));
        $appearance = Settings::appearance(isset($attributes['components']) || isset($attributes['layout']) ? array_merge($settings,$attributes) : (!empty($attributes['display']) ? $attributes : $settings));
        $hide = isset($attributes['hide_current']) ? filter_var($attributes['hide_current'],FILTER_VALIDATE_BOOLEAN) : $settings['hide_current'];
        $locale = $this->urls->locale(); $links=[]; $current='';
        foreach(Languages::all() as $language=>$data) {
            if(!ContentTranslations::available(get_queried_object_id(),$language))continue;
            $parts=[];
            if(in_array('flag',$appearance['components'],true)) $parts[]=Languages::flag($data['flag']);
            if(in_array('name',$appearance['components'],true)) $parts[]=esc_html($data['name']);
            if(in_array('code',$appearance['components'],true)) $parts[]='<span class="vmm-language-code">'.esc_html(strtoupper($data['slug'])).'</span>';
            $visible=implode(' ',array_filter($parts));if($visible==='')$visible=esc_html($data['name']);
            if($language===$locale)$current=$visible;
            if($hide && $language===$locale)continue;
            $links[]='<a aria-label="'.esc_attr($data['name']).'" lang="'.esc_attr(str_replace('_','-',$language)).'" hreflang="'.esc_attr(str_replace('_','-',$language)).'" '.($language===$locale?'aria-current="page" ':'').'href="'.esc_url($this->urls->url(get_queried_object_id(),$language)).'">'.$visible.'</a>';
        }
        if(!$links)return '';
        $content=implode('',$links);
        if($appearance['layout']==='dropdown')$content='<details class="vmm-language-dropdown"><summary aria-label="Sprache wählen">'.$current.' <span aria-hidden="true">▾</span></summary><div class="vmm-language-options">'.$content.'</div></details>';
        return '<nav class="vmm-switcher vmm-switcher-'.esc_attr($appearance['layout']).'" aria-label="Sprache">'.$content.'</nav>';
    }

    public function register(): void
    {
        add_shortcode('vmm_language_switcher', fn($atts) => $this->render(is_array($atts) ? $atts : []));
        add_action('widgets_init', function (): void {
            register_widget(SwitcherWidget::class);
            wp_register_sidebar_widget('vmm-auto-switcher', 'VMM Sprachswitcher (automatisch)', function (array $args): void {
                $html = $this->render();
                if ($html !== '') echo $args['before_widget'].$html.$args['after_widget'];
            });
        });
        add_action('wp_footer', function (): void { if (Settings::get()['placement'] === 'floating') echo '<aside class="vmm-switcher-floating vmm-floating-'.esc_attr(Settings::get()['floating_position']).'">'.$this->render().'</aside>'; });
        add_filter('sidebars_widgets', static function (array $sidebars): array {
            $settings = Settings::get();
            if (!is_admin() && $settings['placement'] === 'sidebar' && $settings['sidebar'] !== '') $sidebars[$settings['sidebar']][] = 'vmm-auto-switcher';
            return $sidebars;
        });
        add_action('wp_enqueue_scripts', static function (): void { wp_enqueue_style('vmm-switcher', plugins_url('assets/switcher.css', dirname(__DIR__, 2).'/vmm-multilingual.php'), [], '0.9.0'); });
    }
}
