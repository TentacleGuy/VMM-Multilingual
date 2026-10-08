<?php
declare(strict_types=1);
namespace VMM\Multilingual\Site;
final class MenuTranslations
{
    public static function translation(array $data,string $locale):array
    {
        $source=Languages::source();
        $read=static function(string $language)use($data):array {
            $row=$data['locales'][$language]??($language==='en_GB'?$data:[]);
            return ['mode'=>$row['mode']??((!empty($row['label'])||!empty($row['url']))?'custom':'inherit'),'label'=>(string)($row['label']??''),'url'=>(string)($row['url']??'')];
        };
        $base=$read($source);$row=$read($locale);
        if($locale===$source)return $row;
        if($row['mode']==='inherit')return $base;
        return ['mode'=>$row['mode'],'label'=>$row['label']?:$base['label'],'url'=>$row['url']?:$base['url']];
    }
    public function register(LanguageUrls $urls):void
    {
        add_action('wp_nav_menu_item_custom_fields',static function($id):void {
            $data=(array)get_post_meta($id,'_vmm_menu',true);
            $only=$data['only']??(($data['visibility']??'both')!=='both'?$data['visibility']:'');
            echo '<div class="vmm-menu-translation" data-id="'.(int)$id.'"><input class="vmm-menu-only" type="hidden" name="vmm_menu['.(int)$id.'][only]" value="'.esc_attr($only).'"><p class="vmm-menu-scope"></p>';
            foreach(Languages::names() as $locale=>$name) {
                $row=$data['locales'][$locale]??($locale==='en_GB'?$data:[]);
                $mode=$row['mode']??((!empty($row['label'])||!empty($row['url']))?'custom':'inherit');
                echo '<div class="vmm-menu-translation-fields" data-locale="'.esc_attr($locale).'" hidden><p><label>Behandlung in '.esc_html($name).' <select class="vmm-menu-mode" name="vmm_menu['.$id.'][locales]['.esc_attr($locale).'][mode]">';
                foreach(['inherit'=>'Übernehmen','custom'=>'Anpassen','hide'=>'Nicht anzeigen'] as $value=>$label)echo '<option value="'.$value.'" '.selected($mode,$value,false).'>'.$label.'</option>';
                echo '</select></label></p><div class="vmm-menu-custom"><p><label>Beschriftung<br><input class="widefat vmm-menu-label" name="vmm_menu['.$id.'][locales]['.esc_attr($locale).'][label]" value="'.esc_attr($row['label']??'').'"></label><small>Leer: Beschriftung der Ausgangssprache übernehmen.</small></p><p><label>Ziel<br><input type="url" class="widefat vmm-menu-url" name="vmm_menu['.$id.'][locales]['.esc_attr($locale).'][url]" value="'.esc_attr($row['url']??'').'"></label><small>Leer: Ziel übernehmen; interne Seiten werden automatisch in der gewählten Sprache geöffnet.</small></p></div><p class="vmm-menu-original"></p></div>';
            }
            echo '</div>';
        });
        add_action('wp_update_nav_menu_item',static function($menu,$id):void {
            if(!current_user_can('edit_theme_options')||!isset($_POST['vmm_menu_nonce'],$_POST['vmm_menu'][$id])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vmm_menu_nonce'])),'vmm_menu_translate'))return;
            $input=wp_unslash($_POST['vmm_menu'][$id]);if(!is_array($input))return;
            $old=(array)get_post_meta($id,'_vmm_menu',true);
            $next=$old;$next['visibility']='both';$next['only']=isset(Languages::all()[$input['only']??''])?$input['only']:'';
            foreach(Languages::all() as $locale=>$language) {
                $row=$input['locales'][$locale]??null;if(!is_array($row))continue;
                $next['locales'][$locale]=['mode'=>in_array($row['mode']??'', ['inherit','custom','hide'],true)?$row['mode']:'inherit','label'=>sanitize_text_field(is_string($row['label']??null)?$row['label']:''),'url'=>esc_url_raw(is_string($row['url']??null)?$row['url']:'',['http','https','mailto','tel'])];
            }
            update_post_meta($id,'_vmm_menu',wp_slash($next));
        },20,2);
        add_action('admin_footer-nav-menus.php',static function():void {
            echo '<div id="vmm-menu-sidebar" hidden class="postbox" data-source="'.esc_attr(Languages::source()).'"><h2 style="padding:12px">VMM Sprache</h2><div class="inside"><label>Bearbeitungssprache <select id="vmm-menu-locale">';
            foreach(Languages::names() as $locale=>$name)echo '<option value="'.esc_attr($locale).'" '.selected($locale,Languages::source(),false).'>'.esc_html($name).'</option>';
            echo '</select></label><p>Ausgangsmenü übernehmen, Einträge anpassen oder ausblenden. Neue Einträge gehören nur zur gewählten Übersetzung; in der Ausgangssprache angelegte Einträge gelten für alle Sprachen.</p><p>Reihenfolge und Unterpunkte bilden eine gemeinsame Grundstruktur.</p></div></div><div id="vmm-menu-nonce" hidden>';wp_nonce_field('vmm_menu_translate','vmm_menu_nonce');echo '</div>';
        });
        add_action('admin_enqueue_scripts',static function($hook):void {
            if($hook==='nav-menus.php')wp_enqueue_script('vmm-menu-translate',plugins_url('assets/menu-translations.js',dirname(__DIR__,2).'/vmm-multilingual.php'),[],(string)filemtime(dirname(__DIR__,2).'/assets/menu-translations.js'),true);
        });
        add_filter('wp_get_nav_menu_items',static function($items)use($urls) {
            if(is_admin())return $items;
            $locale=$urls->locale();$removed=[];$result=[];
            foreach($items as $item) {
                $data=(array)get_post_meta($item->ID,'_vmm_menu',true);$row=self::translation($data,$locale);
                $only=$data['only']??(($data['visibility']??'both')!=='both'?$data['visibility']:'');
                if(($only!==''&&$only!==$locale)||$row['mode']==='hide')$removed[(int)$item->ID]=true;
            }
            do{$changed=false;foreach($items as $item)if(isset($removed[(int)$item->menu_item_parent])&&!isset($removed[(int)$item->ID])){$removed[(int)$item->ID]=true;$changed=true;}}while($changed);
            foreach($items as $item) {
                if(isset($removed[(int)$item->ID]))continue;
                $item=clone $item;$data=(array)get_post_meta($item->ID,'_vmm_menu',true);$row=self::translation($data,$locale);
                if($row['label']!=='')$item->title=$row['label'];
                if($row['url']!=='') {
                    $item->url=$row['url'];$own=$data['locales'][$locale]??($locale==='en_GB'?$data:[]);
                    if(($own['mode']??'inherit')==='inherit'||empty($own['url']))$item->url=$urls->localizeLink($item->url,$locale);
                }
                elseif($item->type==='post_type'&&$item->object==='page')$item->url=$urls->url((int)$item->object_id,$locale);
                else $item->url=$urls->localizeLink($item->url,$locale);
                $result[]=$item;
            }
            return $result;
        },40);
    }
}
