<?php
declare(strict_types=1);
namespace VMM\Multilingual\Site;
final class SwitcherWidget extends \WP_Widget
{
 public function __construct(){parent::__construct('vmm_language_switcher','VMM Sprachswitcher',['description'=>'Sprachauswahl für eine beliebige YOOtheme-/WordPress-Widgetposition.']);}
 public function widget($args,$instance):void {
  $html=(new Switcher(new LanguageUrls()))->render($instance);if($html==='')return;
  echo $args['before_widget'];if(!empty($instance['title']))echo $args['before_title'].esc_html($instance['title']).$args['after_title'];echo $html.$args['after_widget'];
 }
 public function form($instance):void {
  echo '<p><label>Titel <input class="widefat" name="'.esc_attr($this->get_field_name('title')).'" value="'.esc_attr($instance['title']??'').'"></label></p>';
  $custom=!empty($instance['custom'])||!empty($instance['display']);$appearance=$custom?Settings::appearance($instance):Settings::get();
  echo '<p><label><input type="checkbox" name="'.esc_attr($this->get_field_name('custom')).'" value="1" '.checked($custom,true,false).'> Eigene Darstellung statt globaler Einstellung</label></p><p><strong>Sichtbare Bestandteile</strong><br>';
  foreach(['name'=>'Name','flag'=>'Flagge','code'=>'Sprachcode']as$value=>$label)echo '<label><input type="checkbox" name="'.esc_attr($this->get_field_name('components')).'[]" value="'.$value.'" '.checked(in_array($value,$appearance['components'],true),true,false).'>'.$label.'</label> ';
  echo '</p><p><label>Anordnung <select name="'.esc_attr($this->get_field_name('layout')).'">';
  foreach(['dropdown'=>'Dropdown','horizontal'=>'Nebeneinander','vertical'=>'Übereinander']as$value=>$label)echo '<option value="'.$value.'" '.selected($appearance['layout'],$value,false).'>'.$label.'</option>';
  echo '</select></label></p><p>Die Position bestimmst du durch den Widgetbereich.</p>';
 }
 public function update($new,$old):array {
  $result=['title'=>sanitize_text_field($new['title']??'')];if(empty($new['custom']))return $result;
  try{return array_merge($result,['custom'=>true],Settings::appearance(['components'=>$new['components']??[],'layout'=>$new['layout']??'horizontal']));}catch(\InvalidArgumentException){return $old;}
 }
}
