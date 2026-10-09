<?php
declare(strict_types=1);
namespace VMM\Multilingual\Site;

/** Menu editing uses the same _vmm_menu records as Appearance > Menus. */
final class VisualMenu
{
 public static function document(int $id,string $locale): array {
  $post=get_post($id);if(!$post||$post->post_type!=='nav_menu_item')throw new \InvalidArgumentException('Menüpunkt nicht vorhanden.');
  $item=wp_setup_nav_menu_item($post);$data=(array)get_post_meta($id,'_vmm_menu',true);$base=MenuTranslations::translation($data,Languages::source());$row=$data['locales'][$locale]??($locale==='en_GB'?$data:[]);
  $source=['label'=>$base['label']?:$item->title,'url'=>$base['url']?:$item->url];
  return ['id'=>$id,'source'=>$source,'row'=>['mode'=>$row['mode']??((!empty($row['label'])||!empty($row['url']))?'custom':'inherit'),'label'=>(string)($row['label']??''),'url'=>(string)($row['url']??'')],'revision'=>ContentTranslations::hash([$data,$source]),'confirmed_same'=>$row['confirmed_same']??[],'only'=>$data['only']??''];
 }
 public static function register(): void {
  // YOOtheme uses its own walker, which carries item classes but skips WP link attributes.
  add_filter('wp_get_nav_menu_items',static function($items){if(VisualEditor::frame()&&current_user_can('edit_theme_options'))foreach($items as $item){$item->classes[]='vmm-menu-item-'.(int)$item->ID;}return $items;},100);
  add_filter('nav_menu_link_attributes',static function($attrs,$item){if(VisualEditor::frame()&&current_user_can('edit_theme_options'))$attrs['data-vmm-menu']=(string)$item->ID;return $attrs;},100,2);
  add_action('rest_api_init',static function(){register_rest_route('vmm-multilingual/v1','/visual/menu/(?P<id>\d+)',[
   'methods'=>['GET','PUT'],'permission_callback'=>static fn()=>current_user_can('edit_theme_options'),
   'callback'=>static function($r){$lock=null;try{
    $data=$r->get_method()==='GET'?$r->get_params():$r->get_json_params();$locale=$data['locale']??'';$id=(int)$r['id'];
    if(!is_string($locale)||!isset(Languages::all()[$locale])||$locale===Languages::source()||strlen((string)$r->get_body())>20000)throw new \InvalidArgumentException('Ungültige Sprache oder Eingabe.');
    if($r->get_method()==='PUT'){
     if(($data['source_locale']??'')!==Languages::source())throw new \RuntimeException('Ausgangssprache geändert. Bitte neu laden.');
     global $wpdb;$lock='vmm_menu_'.get_current_blog_id().'_'.$id;if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,2)',$lock))!==1){$lock=null;throw new \RuntimeException('Menüpunkt wird gerade gespeichert.');}
     wp_cache_delete($id,'post_meta');$doc=self::document($id,$locale);if(($data['revision']??'')!==$doc['revision'])throw new \RuntimeException('Menüpunkt inzwischen geändert. Bitte neu laden.');
     if(!in_array($data['mode']??'', ['inherit','custom','hide'],true)||!is_array($data['fields']??null))throw new \InvalidArgumentException('Ungültige Behandlung.');
     $row=['mode'=>$data['mode'],'label'=>'','url'=>'','confirmed_same'=>[]];foreach($data['fields'] as $key=>$field){if(!in_array($key,['label','url'],true)||!is_array($field)||!in_array($field['mode']??'', ['inherit','custom'],true)||!is_string($field['value']??null))throw new \InvalidArgumentException('Ungültiges Menüfeld.');if($field['mode']==='custom'&&!empty($field['confirmed_same'])&&$field['value']===$doc['source'][$key])$row['confirmed_same'][$key]=true;if($field['mode']==='custom')$row[$key]=$key==='url'?esc_url_raw($field['value'],['http','https','mailto','tel']):sanitize_text_field($field['value']);}
     $stored=(array)get_post_meta($id,'_vmm_menu',true);$stored['locales'][$locale]=array_replace($stored['locales'][$locale]??[],$row);update_post_meta($id,'_vmm_menu',wp_slash($stored));
    }
    return self::document($id,$locale);
   }catch(\Throwable $e){return new \WP_Error('vmm_visual_menu',$e->getMessage(),['status'=>409]);}finally{if($lock!==null){global $wpdb;$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}}}
  ]);});
 }
}
