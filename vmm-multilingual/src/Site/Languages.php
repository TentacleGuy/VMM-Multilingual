<?php
declare(strict_types=1);
namespace VMM\Multilingual\Site;
final class Languages
{
 public static function source(): string { $locale=(string)get_option('vmm_source_language','de_DE'); return isset(self::catalog()[$locale])?$locale:'de_DE'; }
 public static function defaults(): array { return array_intersect_key(self::presets(),array_flip(['de_DE','en_GB'])); }
 private static function presets(): array { return ['de_DE'=>['name'=>'Deutsch','slug'=>'de','flag'=>'DE','host'=>''],'en_GB'=>['name'=>'English','slug'=>'en','flag'=>'GB','host'=>''],'fr_FR'=>['name'=>'Français','slug'=>'fr','flag'=>'FR','host'=>''],'es_ES'=>['name'=>'Español','slug'=>'es','flag'=>'ES','host'=>''],'it_IT'=>['name'=>'Italiano','slug'=>'it','flag'=>'IT','host'=>''],'nl_NL'=>['name'=>'Nederlands','slug'=>'nl','flag'=>'NL','host'=>''],'pt_PT'=>['name'=>'Português','slug'=>'pt','flag'=>'PT','host'=>''],'pl_PL'=>['name'=>'Polski','slug'=>'pl','flag'=>'PL','host'=>''],'cs_CZ'=>['name'=>'Čeština','slug'=>'cs','flag'=>'CZ','host'=>''],'sk_SK'=>['name'=>'Slovenčina','slug'=>'sk','flag'=>'SK','host'=>''],'hu_HU'=>['name'=>'Magyar','slug'=>'hu','flag'=>'HU','host'=>''],'sl_SI'=>['name'=>'Slovenščina','slug'=>'sl','flag'=>'SI','host'=>''],'hr_HR'=>['name'=>'Hrvatski','slug'=>'hr','flag'=>'HR','host'=>''],'ro_RO'=>['name'=>'Română','slug'=>'ro','flag'=>'RO','host'=>''],'bg_BG'=>['name'=>'Български','slug'=>'bg','flag'=>'BG','host'=>''],'el_GR'=>['name'=>'Ελληνικά','slug'=>'el','flag'=>'GR','host'=>''],'da_DK'=>['name'=>'Dansk','slug'=>'da','flag'=>'DK','host'=>''],'sv_SE'=>['name'=>'Svenska','slug'=>'sv','flag'=>'SE','host'=>''],'nb_NO'=>['name'=>'Norsk','slug'=>'no','flag'=>'NO','host'=>''],'fi_FI'=>['name'=>'Suomi','slug'=>'fi','flag'=>'FI','host'=>''],'tr_TR'=>['name'=>'Türkçe','slug'=>'tr','flag'=>'TR','host'=>''],'uk_UA'=>['name'=>'Українська','slug'=>'uk','flag'=>'UA','host'=>''],'ru_RU'=>['name'=>'Русский','slug'=>'ru','flag'=>'RU','host'=>''],'ja_JP'=>['name'=>'日本語','slug'=>'ja','flag'=>'JP','host'=>''],'ko_KR'=>['name'=>'한국어','slug'=>'ko','flag'=>'KR','host'=>''],'zh_CN'=>['name'=>'简体中文','slug'=>'zh','flag'=>'CN','host'=>'']]; }
 public static function catalog(): array { return array_replace(self::presets(),(array)get_option('vmm_languages',self::defaults())); }
 public static function all(): array {
  $stored=(array)get_option('vmm_languages',self::defaults());
  $enabled=(array)get_option('vmm_enabled_languages',array_keys($stored));$enabled[]=self::source();
  return array_intersect_key(self::catalog(),array_flip($enabled));
 }
 public static function name(string $locale): string {
  static $names=null;$names??=json_decode((string)file_get_contents(dirname(__DIR__,2).'/assets/language-names.json'),true);
  if(isset($names[self::source()][$locale]))return $names[self::source()][$locale];
  if(class_exists(\Locale::class))return \Locale::getDisplayLanguage($locale,self::source());
  $de=['de'=>'Deutsch','en'=>'Englisch','fr'=>'Französisch','es'=>'Spanisch','it'=>'Italienisch','nl'=>'Niederländisch','pt'=>'Portugiesisch','pl'=>'Polnisch','cs'=>'Tschechisch','sk'=>'Slowakisch','hu'=>'Ungarisch','sl'=>'Slowenisch','hr'=>'Kroatisch','ro'=>'Rumänisch','bg'=>'Bulgarisch','el'=>'Griechisch','da'=>'Dänisch','sv'=>'Schwedisch','nb'=>'Norwegisch','fi'=>'Finnisch','tr'=>'Türkisch','uk'=>'Ukrainisch','ru'=>'Russisch','ja'=>'Japanisch','ko'=>'Koreanisch','zh'=>'Chinesisch (vereinfacht)'];
  return str_starts_with(self::source(),'de')?($de[substr($locale,0,2)]??$locale):(self::catalog()[$locale]['name']??$locale);
 }
 public static function names(): array {return array_combine(array_keys(self::all()),array_map([self::class,'name'],array_keys(self::all())));}
 public static function host(string $locale): string {
  $language=self::catalog()[$locale]??null;if(!$language||$locale===self::source())return '';
  if($language['host']!=='')return $language['host'];
  if($locale==='en_GB'&&Settings::get()['en_host']!=='')return Settings::get()['en_host'];
  $parts=wp_parse_url(home_url('/'));return $language['slug'].'.'.preg_replace('/^www\./','',$parts['host']??'').(isset($parts['port'])?':'.$parts['port']:'');
 }
 public static function select(array $enabled): void {
  $catalog=self::catalog();$next=[self::source()];
  foreach($enabled as $locale){if(!is_string($locale)||!isset($catalog[$locale]))throw new \InvalidArgumentException('Ungültige Sprache.');if($locale!==self::source())$next[]=$locale;}
  foreach($next as $locale)if($locale!==self::source()&&get_page_by_path($catalog[$locale]['slug']))throw new \InvalidArgumentException('Sprachpfad bereits belegt: '.$catalog[$locale]['slug']);
  // Store only existing data and newly selected presets; disabled translations remain intact.
  $stored=(array)get_option('vmm_languages',self::defaults());foreach($next as $locale)$stored[$locale]=$catalog[$locale];
  update_option('vmm_languages',$stored,false);update_option('vmm_enabled_languages',array_values(array_unique($next)),false);
 }
 public static function register(): void {
  add_action('admin_init',static function():void {if(($_GET['page']??'')==='vmm-languages'&&current_user_can('manage_options')){wp_safe_redirect(admin_url('admin.php?page=vmm-settings&tab=languages'));exit;}});
  add_action('admin_post_vmm_languages',static function():void {
   if(!current_user_can('manage_options'))wp_die('Keine Berechtigung.');check_admin_referer('vmm_languages');
   try{$enabled=wp_unslash($_POST['enabled_languages']??[]);if(!is_array($enabled))throw new \InvalidArgumentException('Ungültige Auswahl.'); $source=sanitize_text_field(wp_unslash($_POST['source_language']??self::source())); if(!isset(self::catalog()[$source]) || !in_array($source,array_merge($enabled,[self::source()]),true))throw new \InvalidArgumentException('Ausgangssprache muss aktiv sein.'); $enabled[]=$source; self::select($enabled); SourceLanguage::change($source);wp_safe_redirect(admin_url('admin.php?page=vmm-settings&tab=languages&saved=1'));exit;}
   catch(\Throwable $e){wp_die(esc_html($e->getMessage()),'Sprachen',['response'=>400,'back_link'=>true]);}
  });
 }
 public static function render(): void {
  echo '<h2>Aktive Sprachen</h2><p>Sprachen auswählen und speichern. Namen, URL-Codes und Flaggen sind fertig eingerichtet. Die Ausgangssprache liefert die Standardwerte für Inhalte und Menüs. Geöffnete Editoren danach neu laden.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="vmm_languages">';wp_nonce_field('vmm_languages');
  $active=self::all();echo '<p><label>Ausgangssprache <select name="source_language">'; foreach(self::catalog() as $locale=>$language)echo '<option value="'.esc_attr($locale).'" '.selected(self::source(),$locale,false).'>'.esc_html(self::name($locale)).'</option>';echo '</select></label></p><p>Die gewählte Ausgangssprache muss angehakt sein. Beim Wechsel bleiben vorhandene Originalinhalte als Sprachversion erhalten; anschließend geöffnete Editoren neu laden.</p>';echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px;max-width:1100px">';
  foreach(self::catalog() as $locale=>$language)echo '<label style="display:flex;align-items:center;gap:10px;padding:14px;background:white;border:1px solid #ccd0d4;border-radius:4px"><input type="checkbox" name="enabled_languages[]" value="'.esc_attr($locale).'" '.checked(isset($active[$locale]),true,false).' '.disabled($locale,self::source(),false).'>'.self::flag($language['flag']).'<span>'.esc_html(self::name($locale)).($locale===self::source()?' · Ausgangssprache':'').'<small style="display:block;color:#646970">'.esc_html(strtoupper($language['slug'])).'</small></span></label>';
  echo '</div><p>Deaktivieren blendet eine Sprache auf der Website und in Editoren aus. Übersetzungen bleiben gespeichert. Bei Subdomains wird der Hostname automatisch als Sprachcode + Domain gebildet; DNS und SSL beim Hosting einrichten.</p>';submit_button('Sprachen speichern');echo '</form>';
 }
 public static function flag(string $country): string
 {
  if(!preg_match('/^[A-Z]{2}$/D',$country))return '';
  // Local SVGs avoid platform-dependent emoji rendering and external requests.
  $file=dirname(__DIR__,2).'/assets/flags/'.strtolower($country).'.svg';
  if(is_file($file))return '<img class="vmm-flag" style="display:inline-block;width:24px;height:16px;min-width:24px;flex:0 0 24px;object-fit:cover;vertical-align:middle" alt="" aria-hidden="true" width="24" height="16" src="'.esc_url(plugins_url('assets/flags/'.strtolower($country).'.svg',dirname(__DIR__,2).'/vmm-multilingual.php')).'">';
  return '<span aria-hidden="true">'.'&#'.(127397+ord($country[0])).';'.'&#'.(127397+ord($country[1])).';'.'</span>';
 }
}
