<?php
declare(strict_types=1);
namespace VMM\Multilingual\Integrations;
use VMM\Multilingual\Site\{Languages,LanguageUrls,PluginTranslations as Strings};
/** Optional public hooks added to FWB 1.5.1, retaining its standalone behavior. */
final class BookingAdapter
{
    public const PLUGIN='ferienwohnung-buchung/ferienwohnung-buchung.php';
    public static function catalog(): array {
        if(!class_exists('FWB_I18n'))return [];$rows=[];
        foreach(\FWB_I18n::catalog() as $text){$key=Strings::key('fwb','',$text);$rows[$key]=Strings::row($key,'Systemtext',$text);}
        $blocks=[];foreach(\FWB_Layout::base()['zones'] as $zone)foreach($zone as $block)$blocks[$block['id']]=$block;
        foreach(\FWB_I18n::texts(\FWB_Layout::base()) as $path=>$text){
            $key='layout:'.$path;[$id,$field]=explode('/',$path,2);$block=$blocks[$id]??[];
            $names=['label'=>'Beschriftung','content'=>'Inhalt / Hinweis','summary_template'=>'Preiszusammenfassung','detail_template'=>'Preisdetails','appearance/placeholder'=>'Eingabehinweis','appearance/help'=>'Hilfetext','calendar_labels/free_label'=>'Kalenderlegende · frei','calendar_labels/busy_label'=>'Kalenderlegende · belegt','calendar_labels/half_label'=>'Kalenderlegende · An-/Abreise','calendar_labels/selected_label'=>'Kalenderlegende · ausgewählt'];
            $context=(string)($block['label']??$id);if($context==='')$context=$id;
            $hint=str_ends_with($path,'/placeholder')?'Eingabehinweis im Formularfeld, kein Shortcode und kein Linkziel. Ein leerer Ausgangswert bedeutet: kein Eingabehinweis hinterlegt.':'';
            if($id==='consent'&&str_ends_with($path,'/placeholder'))$hint.=' Den Datenschutz-Link bearbeitest du unter „Datenschutz-Link“; {privacy_link} in der Zustimmung setzt diesen Link ein.';
            $type=in_array($field,['content','appearance/help'],true)?'html':'text';
            $rows[$key]=Strings::row($key,($names[$field]??$field).' · '.$context,(string)$text,$type,['context'=>$context,'group'=>$context,'hint'=>$hint,'path'=>$path]);
            foreach(self::resources((string)$text) as $resource){$resourceKey='resource:'.$path.':'.$resource['index'];$rows[$resourceKey]=Strings::row($resourceKey,($resource['type']==='image'?'Bild':'Link').' · '.$context,$resource['value'],$resource['type'],['context'=>$context,'path'=>$path]);}
        }
        $settings=\FWB_Settings::get();foreach(['property_name','rules','tax_note','privacy_url','invoice_note'] as $field){$key='settings:'.$field;$rows[$key]=Strings::row($key,(['property_name'=>'Unterkunftsname','rules'=>'Hausregeln · {rules}','tax_note'=>'Ortstaxenhinweis · {tax_note}','privacy_url'=>'Datenschutz-Link · {privacy_link}','invoice_note'=>'Rechnungshinweis · {invoice_note}'][$field]),(string)$settings[$field],$field==='privacy_url'?'url':'html');}
        foreach(self::templates() as $field=>$text){
            $area=str_starts_with($field,'pdf_')?'pdf':'email';$key='templates:'.$field;
            $names=['request'=>'Anfrage','confirmed'=>'Bestätigung','cancelled'=>'Stornierung','rejected'=>'Absage','invoice'=>'Rechnung','host'=>'Gastgeber-Benachrichtigung'];
            $parts=explode('_',$field);$name=$field==='signature'?'Signatur':($area==='pdf'?(['pdf_header'=>'Kopfzeile','pdf_body'=>'Rechnungstext','pdf_footer'=>'Fußzeile'][$field]??$field):($names[$parts[0]]??$parts[0]).' · '.(str_ends_with($field,'_subject')?'Betreff':'Nachricht'));
            $rows[$key]=Strings::row($key,($area==='pdf'?'PDF':'E-Mail').' · '.$name,(string)$text,str_ends_with($field,'_subject')?'text':'html',['area'=>$area,'group'=>($area==='pdf'?'PDF · Rechnung':($field==='signature'?'E-Mail · Signatur':'E-Mail · '.($names[$parts[0]]??$parts[0]))),'path'=>$field]);
            foreach(self::resources((string)$text) as $resource){$rk='resource:templates/'.$field.':'.$resource['index'];$rows[$rk]=Strings::row($rk,($resource['type']==='image'?'Bild':'Link').' · '.$name,$resource['value'],$resource['type'],['area'=>$area,'path'=>$field]);}
        }
        $design=array_merge(\FWB_Documents::pdf_defaults(),(array)get_option('fwb_pdf_design',[]));
        $rows['pdf:logo']=Strings::row('pdf:logo','PDF · Logo',(string)(wp_get_attachment_url((int)$design['logo_id'])?:''),'image',['area'=>'pdf','hint'=>'Lokales PNG/JPEG aus der Mediathek, bis 3 MB. Leer bedeutet: kein Logo.']);
        foreach($rows as &$row)$row['area']=$row['area']??(str_starts_with($row['key'],'layout:')||str_starts_with($row['key'],'resource:')?'website':(str_starts_with($row['key'],'settings:')?'shared':'system'));unset($row);
        // Put the visible form first, then the system messages.
        $rank=static fn($r)=>$r['source']===''?3:(str_starts_with($r['key'],'layout:')?0:(str_starts_with($r['key'],'settings:')?1:2));
        uasort($rows,static fn($a,$b)=>$rank($a)<=>$rank($b));
        return $rows;
    }
    private static function locale(): string {
        $slug=\FWB_I18n::current();foreach(Languages::all() as $locale=>$profile)if($profile['slug']===$slug)return $locale;return Languages::source();
    }
    public static function templates(): array {
        return array_merge(\FWB_I18n::base_templates(),['signature'=>(string)get_option('fwb_mail_signature',''),'host_subject'=>'Neue Buchungsanfrage {booking_number}','host_body'=>"Neue Anfrage von {name}\n{arrival} bis {departure}\n\n{booking_admin_url}"]);
    }
    public static function resources(string $html): array {
        if(!class_exists('WP_HTML_Tag_Processor'))return [];$processor=new \WP_HTML_Tag_Processor($html);$rows=[];$index=0;
        while($processor->next_tag()){$tag=$processor->get_tag();$attribute=$tag==='IMG'?'src':($tag==='A'?'href':null);if($attribute===null)continue;$value=$processor->get_attribute($attribute);if(is_string($value)&&$value!=='')$rows[]=['index'=>$index++,'type'=>$tag==='IMG'?'image':'url','value'=>$value];}return $rows;
    }
    private static function projectResources(string $html,string $path,string $locale): string {
        if(!class_exists('WP_HTML_Tag_Processor'))return $html;$processor=new \WP_HTML_Tag_Processor($html);$index=0;
        while($processor->next_tag()){$tag=$processor->get_tag();$attribute=$tag==='IMG'?'src':($tag==='A'?'href':null);if($attribute===null)continue;$value=$processor->get_attribute($attribute);if(!is_string($value)||$value==='')continue;$translated=Strings::value(self::PLUGIN,'resource:'.$path.':'.$index++,$value,$locale);$processor->set_attribute($attribute,$translated);if($tag==='IMG'&&$translated!==$value){$processor->remove_attribute('srcset');$processor->remove_attribute('sizes');}}return $processor->get_updated_html();
    }
    public function register(LanguageUrls $urls): void {
        add_action('admin_init',static function(){
            if(!current_user_can('manage_options')||!defined('FWB_I18n::VMM_TRANSLATION_HOOKS')||get_option('vmm_booking_imported_v2'))return;
            global $wpdb;$lock='vmm_strings_'.get_current_blog_id();if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,5)',$lock))!==1)return;
            try {
            wp_cache_delete(Strings::OPTION,'options');wp_cache_delete('vmm_booking_imported_v2','options');if(get_option('vmm_booking_imported_v2'))return;
            $data=(array)get_option(Strings::OPTION,[]);$rows=self::catalog();$changed=false;
            foreach(Languages::catalog() as $locale=>$profile){if($locale===Languages::source())continue;foreach(['layout','settings','templates'] as $group)foreach((array)get_option('fwb_i18n_'.$profile['slug'].'_'.$group,[]) as $path=>$record){$key=$group.':'.$path;if(!isset($rows[$key])||!is_string($record['value']??null)||isset($data[self::PLUGIN]['locales'][$locale][$key]))continue;$data[self::PLUGIN]['locales'][$locale][$key]=['value'=>$record['value'],'source_hash'=>\VMM\Multilingual\Site\ContentTranslations::hash([$record['source']??$rows[$key]['source']])];$changed=true;}
                foreach($rows as $key=>$row){if(isset($data[self::PLUGIN]['locales'][$locale][$key]))continue;$value=\FWB_I18n::builtin($row['source'],$profile['slug']);if($value!==null&&$value!==$row['source']){$data[self::PLUGIN]['locales'][$locale][$key]=['value'=>$value,'source_hash'=>\VMM\Multilingual\Site\ContentTranslations::hash([$row['source']])];$changed=true;}}
                if(str_starts_with($locale,'en'))foreach(\FWB_I18n::english() as $source=>$value){$key=Strings::key('fwb','',$source);if(!isset($rows[$key])||isset($data[self::PLUGIN]['locales'][$locale][$key]))continue;$data[self::PLUGIN]['locales'][$locale][$key]=['value'=>$value,'source_hash'=>\VMM\Multilingual\Site\ContentTranslations::hash([$source])];$changed=true;}
            }
            if($changed){$data[self::PLUGIN]['revision']=(int)($data[self::PLUGIN]['revision']??0)+1;update_option(Strings::OPTION,$data,false);}update_option('vmm_booking_imported_v2',1,false);
            } finally {$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
        });
        add_filter('vmm_plugin_issues',static function($issues,$plugin){if($plugin===self::PLUGIN&&(!defined('FWB_I18n::VMM_TRANSLATION_HOOKS')||\FWB_I18n::VMM_TRANSLATION_HOOKS<2))$issues[]='Die Ausgabeschnittstellen dieses Plugins fehlen. Bitte den mitgelieferten booking-hooks.patch einspielen; gespeicherte Übersetzungen bleiben erhalten.';return $issues;},10,2);
        \VMM\Multilingual\Site\PluginContent::provider(self::PLUGIN,[self::class,'catalog']);
        add_filter('vmm_visual_plugin_binding',static function($binding,$plugin,$element,$slot){
            if($plugin!==self::PLUGIN)return $binding;
            $block=$element;while($block instanceof \DOMElement&&!$block->hasAttribute('data-block'))$block=$block->parentNode;
            if(!$block instanceof \DOMElement)return $binding;$id=$block->getAttribute('data-block');$tag=strtolower($element->tagName);
            if($slot==='placeholder'&&in_array($tag,['input','textarea'],true))return 'layout:'.$id.'/appearance/placeholder';
            if(str_starts_with($slot,'text:')&&(($tag==='label'&&$element->hasAttribute('for'))||in_array($tag,['h1','h2','h3','h4','h5','h6'],true)||($tag==='button'&&$element->getAttribute('type')==='submit')))return 'layout:'.$id.'/label';
            return $binding;
        },10,4);
        add_filter('fwb_i18n_languages',static function($languages){$out=[];foreach(Languages::all() as $locale=>$profile)$out[$profile['slug']]=['name'=>$profile['name'],'locale'=>$locale];return $out;});
        add_filter('fwb_i18n_source',static fn($source)=>Languages::all()[Languages::source()]['slug']);
        add_filter('fwb_i18n_current',static fn($current)=>!is_admin()&&!(defined('REST_REQUEST')&&REST_REQUEST)?Languages::all()[$urls->locale()]['slug']:$current);
        add_filter('fwb_i18n_text',static function($translated,$text){return Strings::value(self::PLUGIN,Strings::key('fwb','',$text),$text,self::locale());},10,2);
        add_filter('fwb_i18n_layout',static function($layout){$base=\FWB_Layout::base();$texts=\FWB_I18n::texts($base);foreach($texts as $path=>&$text)$text=self::projectResources(Strings::value(self::PLUGIN,'layout:'.$path,(string)$text,self::locale()),$path,self::locale());unset($text);return \FWB_I18n::with_texts($base,$texts);});
        add_filter('fwb_i18n_settings',static function($settings){$base=\FWB_Settings::get();foreach(['property_name','rules','tax_note','privacy_url','invoice_note'] as $field)$settings[$field]=Strings::value(self::PLUGIN,'settings:'.$field,(string)$base[$field],self::locale());return $settings;});
        add_filter('fwb_i18n_templates',static function($templates){foreach(self::templates() as $field=>$text)if($field!=='signature')$templates[$field]=self::projectResources(Strings::value(self::PLUGIN,'templates:'.$field,(string)$text,self::locale()),'templates/'.$field,self::locale());return $templates;});
        add_filter('fwb_i18n_signature',static fn($value)=>self::projectResources(Strings::value(self::PLUGIN,'templates:signature',(string)get_option('fwb_mail_signature',''),self::locale()),'templates/signature',self::locale()));
        add_filter('fwb_i18n_formats',static function($formats){$data=(array)get_option(Strings::OPTION,[]);foreach(['request','confirmed','cancelled','rejected','invoice','host'] as $kind)foreach(array_unique([Languages::source(),self::locale()]) as $locale)if(isset($data[self::PLUGIN]['locales'][$locale]['templates:'.$kind.'_body']['value']))$formats[$kind]=preg_match('/<[a-z][^>]*>/i',$data[self::PLUGIN]['locales'][$locale]['templates:'.$kind.'_body']['value'])?'html':'text';return $formats;});
        add_filter('fwb_i18n_variables',static function($variables,$booking){$base=\FWB_Settings::get();$quote=$booking['data']['quote'];foreach(['rules','tax_note'] as $field)if(!isset($quote[$field])||$quote[$field]===$base[$field])$variables[$field]=Strings::value(self::PLUGIN,'settings:'.$field,(string)$base[$field],self::locale());return $variables;},10,2);
        add_filter('fwb_i18n_pdf_design',static function($design){$base=array_merge(\FWB_Documents::pdf_defaults(),(array)get_option('fwb_pdf_design',[]));$url=Strings::value(self::PLUGIN,'pdf:logo',(string)(wp_get_attachment_url((int)$base['logo_id'])?:''),self::locale());$design['logo_id']=$url===''?'0':(string)attachment_url_to_postid($url);return $design;});
        add_filter('vmm_plugin_validate_value',static function($value,$plugin,$key){if($plugin===self::PLUGIN&&$value!==''&&($key==='pdf:logo'||str_starts_with($key,'templates:pdf_')||str_starts_with($key,'resource:templates/pdf_'))){$images=($key==='pdf:logo'||str_starts_with($key,'resource:'))?[$value]:array_column(array_filter(self::resources($value),static fn($r)=>$r['type']==='image'),'value');foreach($images as $url){$id=attachment_url_to_postid($url);if(!$id)throw new \InvalidArgumentException('PDF-Bild: bitte ein lokales PNG/JPEG aus der Mediathek auswählen.');\FWB_Documents::logo($id);}}return $value;},10,3);

    }
}
