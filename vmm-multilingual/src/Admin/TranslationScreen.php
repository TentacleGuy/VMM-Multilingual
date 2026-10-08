<?php
declare(strict_types=1);
namespace VMM\Multilingual\Admin;
use VMM\Multilingual\Site\Settings;
use VMM\Multilingual\Site\LanguageUrls;
use VMM\Multilingual\Site\PageMetadata;

final class TranslationScreen
{
    public function __construct(private readonly LanguageUrls $urls) {}
    public function register(): void
    {
        add_action('admin_enqueue_scripts', static function (): void {
            if (($_GET['page'] ?? '') === 'vmm-settings') wp_enqueue_script('vmm-settings', plugins_url('assets/settings.js', dirname(__DIR__,2).'/vmm-multilingual.php'), [], '0.10.0', true);
        });
        add_action('admin_menu', function (): void {
            add_menu_page('VMM Multilingual', 'VMM Multilingual', 'edit_pages', 'vmm-multilingual', [$this,'render'], 'dashicons-translation', 59);
            add_submenu_page('vmm-multilingual','Seitendaten','Seitendaten','edit_pages','vmm-multilingual',[$this,'render']);
            add_submenu_page('vmm-multilingual','Sprachswitcher und URLs','Einstellungen','manage_options','vmm-settings',[$this,'settings']);
        });
        add_action('admin_post_vmm_save_metadata', [$this,'saveMetadata']);
        add_action('admin_post_vmm_save_settings', [$this,'saveSettings']);
        add_filter('page_row_actions', static function (array $actions, \WP_Post $post): array {
            if (current_user_can('edit_post',$post->ID)) $actions['vmm'] = '<a href="'.esc_url(admin_url('admin.php?page=vmm-multilingual&object_id='.$post->ID)).'">Sprachdaten / SEO</a>';
            return $actions;
        },10,2);
    }
    public function saveMetadata(): void
    {
        $id = absint($_POST['object_id'] ?? 0);
        if (get_post_type($id) !== 'page' || !current_user_can('edit_pages') || !current_user_can('edit_post',$id)) wp_die('Keine Berechtigung.','',['response'=>403]);
        check_admin_referer('vmm_metadata_'.$id);
        try {
            $data = wp_unslash($_POST['locales'] ?? []);
            if (!is_array($data)) throw new \InvalidArgumentException('Ungültige Seitendaten.');
            PageMetadata::save($id,$data,absint($_POST['revision'] ?? 0),$this->urls);
            wp_safe_redirect(admin_url('admin.php?page=vmm-multilingual&object_id='.$id.'&saved=1'));
            exit;
        } catch (\Throwable $error) { wp_die(esc_html($error->getMessage()),'VMM Multilingual',['response'=>409,'back_link'=>true]); }
    }
    public function saveSettings(): void
    {
        if (!current_user_can('manage_options')) wp_die('Keine Berechtigung.','',['response'=>403]);
        check_admin_referer('vmm_settings');
        try {
            $input = wp_unslash($_POST['settings'] ?? []);
            if (!is_array($input)) throw new \InvalidArgumentException('Ungültige Einstellungen.');
            $languageHosts=$input['language_hosts']??[];
            if(!is_array($languageHosts))throw new \InvalidArgumentException('Ungültige Sprachhostnamen.');
            foreach($languageHosts as $locale=>&$host) {
                if(!isset(\VMM\Multilingual\Site\Languages::all()[$locale]) || $locale===\VMM\Multilingual\Site\Languages::source() || !is_string($host))throw new \InvalidArgumentException('Ungültiger Sprachhostname.');
                $host=strtolower(trim($host));
                if($host!==''&&!preg_match('/^(?=.{1,253}$)[a-z0-9]+(?:[.-][a-z0-9]+)*(?::[0-9]{1,5})?$/D',$host))throw new \InvalidArgumentException('Bitte gültige Hostnamen eingeben.');
                $parts=wp_parse_url(home_url('/'));$base=($parts['host']??'').(isset($parts['port'])?':'.$parts['port']:'');
                if(($input['url_mode']??'')==='subdomain'&&$host===strtolower($base))throw new \InvalidArgumentException('Eine Sprach-Subdomain muss von der Basisadresse abweichen.');
            }unset($host);
            $input['en_host']=$languageHosts['en_GB']??($input['en_host']??Settings::get()['en_host']);
            $settings = Settings::sanitize($input);
            if ($settings['url_mode'] !== 'query' && !get_option('permalink_structure')) throw new \InvalidArgumentException('Verzeichnis und Subdomain benötigen sprechende WordPress-Permalinks.');
            if ($settings['url_mode'] === 'directory') foreach(\VMM\Multilingual\Site\Languages::all() as $locale=>$language) {
                if($locale!==\VMM\Multilingual\Site\Languages::source() && get_page_by_path($language['slug']))throw new \InvalidArgumentException('Sprachpfad bereits belegt: '.$language['slug']);
            }
            update_option('vmm_site_settings',$settings,false);
            $languages = (array)get_option('vmm_languages',\VMM\Multilingual\Site\Languages::defaults());
            $languages['en_GB']['host'] = $settings['en_host'];
            foreach($languageHosts as $locale=>$host)$languages[$locale]['host']=$host;
            update_option('vmm_languages',$languages,false);
            wp_safe_redirect(admin_url('admin.php?page=vmm-settings&tab='.(($_POST['settings_tab']??'')==='urls'?'urls':'switcher').'&saved=1'));
            exit;
        } catch (\Throwable $error) { wp_die(esc_html($error->getMessage()),'VMM Multilingual',['response'=>400,'back_link'=>true]); }
    }
    public function render(): void
    {
        if (!current_user_can('edit_pages')) wp_die('Keine Berechtigung.');
        $id = absint($_GET['object_id'] ?? 0);
        echo '<div class="wrap"><h1>Seitendaten &amp; SEO je Sprache</h1><p>Builder-Texte bearbeitest du direkt in YOOtheme. Hier verwaltest du Seitentitel, URL und Suchmaschinen-/Social-Media-Daten.</p>';
        if (current_user_can('manage_options')) echo '<p><a class="button" href="'.esc_url(admin_url('admin.php?page=vmm-settings')).'">Sprachswitcher &amp; URL-Einstellungen</a></p>';
        if (!$id) {
            echo '<table class="widefat striped"><thead><tr><th>Seite</th><th>Status</th>'.implode('',array_map(static fn($name)=>'<th>'.esc_html($name).'</th>',\VMM\Multilingual\Site\Languages::names())).'</tr></thead><tbody>';
            foreach (get_posts(['post_type'=>'page','post_status'=>['publish','draft','private','pending','future'],'numberposts'=>-1,'orderby'=>'title','order'=>'ASC']) as $post) {
                if (!current_user_can('edit_post',$post->ID)) continue;
                echo '<tr><td><a href="'.esc_url(admin_url('admin.php?page=vmm-multilingual&object_id='.$post->ID)).'">'.esc_html($post->post_title).'</a></td><td>'.esc_html($post->post_status).'</td>';
                foreach (array_keys(\VMM\Multilingual\Site\Languages::all()) as $locale) echo '<td><a href="'.esc_url($this->urls->url($post->ID,$locale)).'">'.esc_html($this->urls->url($post->ID,$locale)).'</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>'; return;
        }
        if (get_post_type($id) !== 'page' || !current_user_can('edit_post',$id)) wp_die('Keine Berechtigung.');
        $post = get_post($id);
        $document = get_post_meta($id,PageMetadata::KEY,true);
        if (isset($_GET['saved'])) echo '<div class="notice notice-success"><p>Seitendaten gespeichert.</p></div>';
        echo '<h2>'.esc_html($post->post_title).'</h2><p>Leere Felder verwenden die vorhandenen WordPress-/SEO-Werte. Der Slug ist ein einzelner URL-Abschnitt; übergeordnete Seiten bleiben erhalten. Bei der Startseite bleibt die URL-Wurzel bestehen.</p><p><a href="'.esc_url(get_edit_post_link($id,'raw')).'">WordPress-Seite bearbeiten</a></p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="vmm_save_metadata"><input type="hidden" name="object_id" value="'.$id.'"><input type="hidden" name="revision" value="'.(int)($document['revision'] ?? 0).'">';
        wp_nonce_field('vmm_metadata_'.$id);
        foreach (\VMM\Multilingual\Site\Languages::names() as $locale=>$label) {
            echo '<section style="background:white;border:1px solid #ccd0d4;padding:20px;margin:20px 0;max-width:1000px"><h2>'.esc_html($label).'</h2><p><a href="'.esc_url($this->urls->url($id,$locale)).'">'.esc_html($this->urls->url($id,$locale)).'</a></p><table class="form-table">';
            foreach (PageMetadata::FIELDS as $field=>$title) {
                $key = 'vmm-'.$locale.'-'.$field;
                $value = (string)($document['locales'][$locale][$field] ?? '');
                $fallback = match($field) { 'title'=>$post->post_title,'slug'=>$post->post_name,'seo_title'=>(string)get_post_meta($id,'_yoast_wpseo_title',true),'description'=>(string)get_post_meta($id,'_yoast_wpseo_metadesc',true),default=>'' };
                $fallback = PageMetadata::value($id,\VMM\Multilingual\Site\Languages::source(),$field) ?: $fallback;
                echo '<tr><th><label for="'.esc_attr($key).'">'.esc_html($title).'</label></th><td>';
                if (str_contains($field,'description')) echo '<textarea class="large-text" rows="3" id="'.esc_attr($key).'" name="locales['.$locale.']['.$field.']" placeholder="'.esc_attr($fallback).'">'.esc_textarea($value).'</textarea>';
                else echo '<input class="regular-text" id="'.esc_attr($key).'" name="locales['.$locale.']['.$field.']" value="'.esc_attr($value).'" placeholder="'.esc_attr($fallback).'">';
                echo '</td></tr>';
            }
            echo '</table></section>';
        }
        submit_button('Seitendaten speichern'); echo '</form></div>';
    }
    public function settings(): void
    {
        if (!current_user_can('manage_options')) wp_die('Keine Berechtigung.');
        $settings = Settings::get();
        global $wp_registered_sidebars;
        $tab=in_array($_GET['tab']??'', ['languages','switcher','urls'],true)?$_GET['tab']:'languages';
        echo '<div class="wrap"><h1>VMM Einstellungen</h1><nav class="nav-tab-wrapper" aria-label="Einstellungsbereiche">';
        foreach(['languages'=>'Sprachen','switcher'=>'Sprachswitcher','urls'=>'URLs & DNS'] as $key=>$label)echo '<a class="nav-tab '.($tab===$key?'nav-tab-active':'').'" href="'.esc_url(admin_url('admin.php?page=vmm-settings&tab='.$key)).'">'.esc_html($label).'</a>';
        echo '</nav>';
        if (isset($_GET['saved'])) echo '<div class="notice notice-success"><p>Einstellungen gespeichert.</p></div>';
        if($tab==='languages'){\VMM\Multilingual\Site\Languages::render();echo '</div>';return;}
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="vmm_save_settings"><input type="hidden" name="settings_tab" value="'.esc_attr($tab).'">'; wp_nonce_field('vmm_settings');
        echo '<div '.($tab!=='urls'?'hidden':'').'>';
        echo '<h2>Sprach-URLs</h2><table class="form-table">';
        $this->select('url_mode','URL-Format',['query'=>'Parameter: /seite/?lang=en','directory'=>'Verzeichnis: /en/seite/','subdomain'=>'Subdomain: en.example.com/seite/'],$settings);
        $targets=array_diff_key(\VMM\Multilingual\Site\Languages::all(),[\VMM\Multilingual\Site\Languages::source()=>true]);
        $first=true;$hostOptions='';
        foreach($targets as $locale=>$language) {
            $hostId=$first?'vmm-en_host':'vmm-host-'.$locale;$first=false;
            $hostOptions.='<option value="'.esc_attr($hostId).'" data-code="'.esc_attr($language['slug']).'">'.esc_html($language['name']).'</option>';
            echo '<tr><th><label for="'.esc_attr($hostId).'">'.esc_html($language['name']).'-Hostname</label></th><td><input id="'.esc_attr($hostId).'" class="regular-text vmm-language-host" name="settings[language_hosts]['.esc_attr($locale).']" value="'.esc_attr(\VMM\Multilingual\Site\Languages::host($locale)).'"><p class="description">Bei Subdomain: DNS, Webserver und HTTPS auf dieselbe WordPress-Installation richten. Leer verwendet Sprachcode + Basisdomain.</p></td></tr>';
        }
        echo '</table><div id="vmm-hosting-planner" hidden><h3>Einrichtung beim Webhosting-Anbieter</h3><p><label>Sprach-Subdomain für die Anleitung <select id="vmm-dns-language">'.$hostOptions.'</select></label></p><p>Auch lokal kannst du hier die spätere öffentliche Domain planen. Diese Vorschau ändert weder WordPress-Adresse noch Sprach-URL-Einstellungen.</p><p><label for="vmm-public-url">Öffentliche WordPress-Adresse</label><br><input type="url" id="vmm-public-url" class="regular-text" placeholder="https://deine-domain.de/"></p><p><label for="vmm-public-en">Öffentlicher Sprachhostname (optional)</label><br><input id="vmm-public-en" class="regular-text" placeholder="Automatisch Sprachcode + Domain"></p><p><label for="vmm-hosting-root">WordPress-Zielverzeichnis im Hosting-Panel (optional)</label><br><input id="vmm-hosting-root" class="regular-text" placeholder="Aus der bestehenden Domain-Zuordnung übernehmen"></p></div>';
        echo '<section id="vmm-subdomain-help" data-home="'.esc_attr(home_url('/')).'" hidden style="background:white;padding:20px;border:1px solid #ccd0d4;max-width:1000px" aria-label="Subdomain-Einrichtung"></section>';
        echo '</div><div '.($tab!=='switcher'?'hidden':'').'><h2>Sprachswitcher</h2><h3>Darstellung</h3><table class="form-table">';
        echo '<tr><th>Sichtbare Bestandteile</th><td><input type="hidden" name="settings[components_present]" value="1">';
        foreach(['name'=>'Name','flag'=>'Flagge','code'=>'Sprachcode'] as $value=>$label)echo '<label style="margin-right:20px"><input type="checkbox" name="settings[components][]" value="'.$value.'" '.checked(in_array($value,$settings['components'],true),true,false).'>'.esc_html($label).'</label>';
        echo '<p class="description">Beliebig kombinieren; mindestens einen Bestandteil wählen.</p></td></tr>';
        $this->select('layout','Anordnung',['dropdown'=>'Dropdown','horizontal'=>'Nebeneinander','vertical'=>'Übereinander'],$settings);
        echo '</table><h3>Platzierung</h3><table class="form-table">';
        $this->select('placement','Automatische Platzierung',['none'=>'Keine – Widget oder Shortcode verwenden','sidebar'=>'In vorhandener Widgetposition','floating'=>'Schwebend'],$settings);
        $this->select('floating_position','Schwebende Position',['bottom-right'=>'Unten rechts','bottom-left'=>'Unten links','top-right'=>'Oben rechts','top-left'=>'Oben links','middle-right'=>'Mittig rechts','middle-left'=>'Mittig links'],$settings);
        $positions = [''=>'Bitte wählen']; foreach ($wp_registered_sidebars as $key=>$sidebar) $positions[$key] = $sidebar['name'].' ('.$key.')';
        $this->select('sidebar','Widgetposition',$positions,$settings);
        echo '<tr><th>Aktuelle Sprache</th><td><label><input type="checkbox" name="settings[hide_current]" value="1" '.checked($settings['hide_current'],true,false).'> In der Auswahlliste ausblenden</label></td></tr></table><p>Alternativ unter <a href="'.esc_url(admin_url('widgets.php')).'">Design → Widgets</a> das Widget <strong>VMM Sprachswitcher</strong> in eine beliebige YOOtheme-Position setzen. Pro Widget ist eine andere Darstellung möglich.</p><p>Shortcode: <code>[vmm_language_switcher]</code> oder <code>[vmm_language_switcher display="codes"]</code>. Wähle bei manueller Widget-/Shortcode-Platzierung „Keine“, um doppelte Ausgaben zu vermeiden.</p>';
        echo '</div>';submit_button('Einstellungen speichern'); echo '</form></div>';
    }
    private function select(string $key,string $label,array $options,array $settings): void
    {
        echo '<tr><th><label for="vmm-'.esc_attr($key).'">'.esc_html($label).'</label></th><td><select id="vmm-'.esc_attr($key).'" name="settings['.esc_attr($key).']">';
        foreach ($options as $value=>$title) echo '<option value="'.esc_attr($value).'" '.selected($settings[$key],$value,false).'>'.esc_html($title).'</option>';
        echo '</select></td></tr>';
    }
}


