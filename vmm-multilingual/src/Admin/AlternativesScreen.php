<?php
declare(strict_types=1);
namespace VMM\Multilingual\Admin;
use VMM\Multilingual\Site\Alternatives;
final class AlternativesScreen
{
 public function register(): void
 {
  add_action('in_widget_form',function($widget):void {
   if (!current_user_can('edit_theme_options')) return;
   $language=Alternatives::widgetLanguage($widget->id);
   echo '<p><label>Anzeigen in <select name="'.esc_attr($widget->get_field_name('_vmm_language')).'">';
   foreach(array_merge(['both'=>'Alle Sprachen'],\VMM\Multilingual\Site\Languages::names()) as $value=>$label) echo '<option value="'.$value.'" '.selected($language,$value,false).'>'.$label.'</option>';
   echo '</select></label></p>';
  },20,1);
  add_filter('widget_update_callback',static function($instance,$new,$old,$widget) {
   if ($instance===false || !current_user_can('edit_theme_options') || !isset($new['_vmm_language'])) return $instance;
   $language=sanitize_text_field($new['_vmm_language']);
   if(!in_array($language,array_merge(['both'],array_keys(\VMM\Multilingual\Site\Languages::all())),true)) return false;
   $settings=Alternatives::get();
   // Preserve former variant languages before removing group behaviour.
   foreach(Alternatives::groups('widgets') as $g) foreach($g['members'] as $locale=>$id) $settings['visibility'][$id]=$locale;
   $settings['widgets']=[]; $settings['groups']['widgets']=[];
   $settings['visibility'][$widget->id]=$language;
   update_option('vmm_alternatives',$settings,false);
   unset($instance['_vmm_language'],$instance['_vmm_alternative']); return $instance;
  },20,4);
  add_action('admin_footer-widgets.php',static function():void {
   global $wp_registered_widgets;
   $labels=[]; foreach($wp_registered_widgets as $id=>$widget) $labels[$id]=(Alternatives::widgetLanguage($id)==='both'?'Alle':strtoupper(\VMM\Multilingual\Site\Languages::all()[Alternatives::widgetLanguage($id)]['slug']??''));
   echo '<script>document.querySelectorAll(".widgets-holder-wrap .widget").forEach(function(w){const i=w.querySelector("input.widget-id"),h=w.querySelector(".widget-title h3"),labels='.wp_json_encode($labels).';if(i&&h&&labels[i.value]){const b=document.createElement("small");b.textContent=" · "+labels[i.value];h.append(b);}});</script>';
  });
 }
}
