<?php
declare(strict_types=1);
namespace VMM\Multilingual\Site;

/** Stable plugin element/field addresses shared by visual and tabular editors. */
final class VisualPlugin
{
 private static array $instances=[];
 public static function render(string $plugin,string $context,string $html,string $area): string {
  $instanceKey=$plugin.'|'.$context;self::$instances[$instanceKey]=(self::$instances[$instanceKey]??0)+1;$context.=' · Instanz '.self::$instances[$instanceKey];
  $editing=VisualEditor::frame()&&current_user_can('manage_options');
  $saved=(array)(get_option(PluginDiscovery::OPTION,[])[$plugin]??[]);
  $records=(array)(get_option(PluginTranslations::OPTION,[])[$plugin]['locales'][PluginTranslations::locale()]??[]);
  $hasTranslation=false;foreach($records as $key=>$record)if(str_starts_with($key,'element:')){$hasTranslation=true;break;}
  if(!$editing&&!PluginDiscovery::active()&&!$hasTranslation)return $html;
  if(!class_exists(\DOMDocument::class)||strlen($html)>2000000)return $html;
  $dom=new \DOMDocument('1.0','UTF-8');$errors=libxml_use_internal_errors(true);
  try {$dom->loadHTML('<?xml encoding="utf-8" ?><div id="vmm-output-root">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD|LIBXML_NONET);}finally{libxml_clear_errors();libxml_use_internal_errors($errors);}
  $root=$dom->getElementById('vmm-output-root');if(!$root)return $html;$changed=false;
  $walk=function(\DOMElement $el,string $path)use(&$walk,$plugin,$context,$area,$editing,$saved,&$changed){
   $tag=strtolower($el->tagName);if(in_array($tag,['script','style','pre','code','svg','noscript'],true)||$el->hasAttribute('contenteditable'))return;
   $element=hash('sha256',$context.'|'.$path);$bindings=[];
   $field=function(string $slot,string $source,string $type,string $kind,callable $write)use($plugin,$context,$path,$area,$element,$el,$editing,&$bindings,&$changed){
    if(strlen($source)>4000)return;
    $key='element:'.hash('sha256',$context.'|'.$path.'|'.$slot);
    $row=PluginTranslations::row($key,$context.' · '.$slot,$source,$type,['area'=>$area,'kind'=>$kind,'group'=>$context.' · '.$path,'context'=>$context,'path'=>$path.' · '.$slot,'origin'=>'runtime','element'=>$element,'slot'=>$slot,'visual'=>true]);
    $binding=apply_filters('vmm_visual_plugin_binding',null,$plugin,$el,$slot,$context);
    if(is_string($binding)){$catalog=PluginContent::catalog($plugin);if(isset($catalog[$binding])){$key=$binding;$row=$catalog[$binding]+['visual'=>true,'element'=>$element,'slot'=>$slot,'origin'=>'runtime'];$source=$row['source'];}}
    if(PluginTranslations::locale()===Languages::source())PluginDiscovery::record($plugin,$row,$editing);
    $bindings[$slot]=$key;if(is_string($binding)&&$binding===$key)return;$value=PluginTranslations::value($plugin,$key,$source,PluginTranslations::locale());
    if($value!==$source){$write($value);$changed=true;}
   };
   $attributes=['title','aria-label','aria-description','placeholder','alt'];if($tag==='img')$attributes[]='src';if($tag==='a')$attributes[]='href';
   foreach($attributes as $attr){
    $optional=($editing||isset($saved['element:'.hash('sha256',$context.'|'.$path.'|'.$attr)]))&&(($tag==='img'&&in_array($attr,['alt','title','aria-label'],true))||(in_array($tag,['a','input','button','textarea','select'],true)&&in_array($attr,['title','aria-label','aria-description'],true))||(in_array($tag,['input','textarea'],true)&&$attr==='placeholder'));
    if(!$el->hasAttribute($attr)&&!$optional)continue;
    $value=$el->getAttribute($attr);if(in_array($attr,['src','href'],true)&&preg_match('~^(?:javascript:|data:|#)~i',$value))continue;
    $type=$attr==='src'?'image':($attr==='href'?'url':'text');$field($attr,$value,$type,$type!=='text'?'resources':($attr==='placeholder'?'help':'labels'),static function($v)use($el,$attr){$el->setAttribute($attr,$v);if($attr==='src'){$el->removeAttribute('srcset');$el->removeAttribute('sizes');}});
   }
   $counts=[];$text=0;foreach(iterator_to_array($el->childNodes) as $child){
    if($child instanceof \DOMText){$slot='text:'.$text++;if($tag!=='textarea'&&trim($child->nodeValue)!==''&&preg_match('/\p{L}/u',$child->nodeValue))$field($slot,$child->nodeValue,'text',in_array($tag,['label','button','option'],true)?'labels':'content',static function($v)use($child){$child->nodeValue=$v;});}
    elseif($child instanceof \DOMElement){$name=strtolower($child->tagName);$counts[$name]=($counts[$name]??0)+1;$walk($child,$path.'/'.$name.'['.$counts[$name].']');}
   }
   if($editing&&$bindings){$el->setAttribute('data-vmm-plugin',$plugin);$el->setAttribute('data-vmm-element',$element);$el->setAttribute('data-vmm-fields',wp_json_encode($bindings));$changed=true;}
  };
  $walk($root,'root');if(!$changed)return $html;
  $result='';foreach($root->childNodes as $child)$result.=$dom->saveHTML($child);return $result;
 }
}
