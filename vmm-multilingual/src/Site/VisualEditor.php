<?php
declare(strict_types=1);
namespace VMM\Multilingual\Site;
use VMM\Multilingual\Integrations\Yootheme\{EditorService,FieldPolicy};

final class VisualEditor
{
 public function __construct(private readonly EditorService $editor){}
 public static function frame(): bool {return isset($_GET['vmm_visual_frame'])&&is_singular(ContentTranslations::types())&&current_user_can('edit_post',get_queried_object_id());}
 public function register(): void { VisualMenu::register();
  add_filter('show_admin_bar',static fn($show)=>self::frame()?false:$show);
  add_action('admin_bar_menu',static function($bar){if(!is_singular(ContentTranslations::types())||!current_user_can('edit_post',get_queried_object_id()))return;$bar->add_node(['id'=>'vmm-visual','title'=>'Visuell übersetzen','href'=>add_query_arg('vmm_visual','1',get_permalink(get_queried_object_id()))]);},90);
  add_action('template_redirect',function(){
   if(!isset($_GET['vmm_visual'])&&!isset($_GET['vmm_visual_frame']))return;
   if(!is_singular(ContentTranslations::types())||!current_user_can('edit_post',get_queried_object_id())){wp_die('Keine Berechtigung.',403);}
   if(!defined('DONOTCACHEPAGE'))define('DONOTCACHEPAGE',true);nocache_headers();
   if(self::frame())return;
   $id=get_queried_object_id();if(ContentTranslations::builder(get_post($id)))$this->editor->open($id,Languages::source());
   $target=sanitize_text_field($_GET['locale']??'');$names=array_diff_key(Languages::names(),[Languages::source()=>true]);if(!isset($names[$target]))$target=array_key_first($names)??'';
   $frame=add_query_arg(['vmm_visual_frame'=>1,'vmm_lang'=>$target],remove_query_arg(['vmm_visual','locale'],get_permalink($id)));
   $file=dirname(__DIR__,2).'/vmm-multilingual.php';wp_enqueue_media();wp_enqueue_editor();
   wp_enqueue_style('vmm-visual',plugins_url('assets/visual-editor.css',$file),[],(string)filemtime(dirname(__DIR__,2).'/assets/visual-editor.css'));
   wp_enqueue_script('vmm-visual',plugins_url('assets/visual-editor.js',$file),['editor'],(string)filemtime(dirname(__DIR__,2).'/assets/visual-editor.js'),true);
   wp_add_inline_script('vmm-visual','window.vmmVisual='.wp_json_encode(['id'=>$id,'locale'=>$target,'source'=>Languages::source(),'languages'=>$names,'sourceName'=>Languages::name(Languages::source()),'widgets'=>\VMM\Multilingual\Integrations\Yootheme\EditorAssets::widgetNames(),'rest'=>rest_url('vmm-multilingual/v1/'),'nonce'=>wp_create_nonce('wp_rest'),'preview'=>get_permalink($id),'plugins'=>current_user_can('manage_options')],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';','before');
   ?><!doctype html><html lang="<?php echo esc_attr(substr(Languages::source(),0,2)); ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Visuell übersetzen</title><?php wp_head(); ?></head><body class="vmm-visual-shell"><header id="vmm-visual-bar"><details id="vmm-visual-languages"><summary aria-label="Übersetzungssprache"><?php echo Languages::flag(Languages::all()[$target]['flag']??''); ?><span><?php echo esc_html($names[$target]??'Sprache'); ?></span><span aria-hidden="true">▾</span></summary><div role="group" aria-label="Sprachen"><?php foreach($names as $locale=>$name)echo '<button type="button" data-vmm-locale="'.esc_attr($locale).'">'.Languages::flag(Languages::all()[$locale]['flag']).'<span>'.esc_html($name).'</span></button>'; ?></div></details><select id="vmm-visual-language" aria-label="Übersetzungssprache" hidden><?php foreach($names as $locale=>$name)echo '<option value="'.esc_attr($locale).'" '.selected($locale,$target,false).'>'.esc_html($name).'</option>'; ?></select><button id="vmm-visual-navigation" type="button" role="switch" aria-checked="false" aria-label="Navigationsmodus" title="Bearbeiten aktiv · zu Navigieren wechseln"><span class="vmm-mode-pencil"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 16l-1 5 5-1L20 8l-4-4L4 16zm10-10l4 4"/></svg></span><span class="vmm-mode-pointer"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3l14 10-7 1-3 7L5 3z"/></svg></span></button><button id="vmm-visual-page" type="button" aria-label="Seitenfelder" title="Seitenfelder"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 3h4l1 3 3 1 3 3v4l-3 1-1 3-3 3h-4l-1-3-3-1-3-3v-4l3-1 1-3 3-3z"/><circle cx="12" cy="12" r="3"/></svg></button><button id="vmm-visual-save" type="button" aria-label="Speichern" title="Speichern" disabled><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 3h14l3 3v15H3V3h1zm3 0v7h10V3M7 21v-8h10v8M14 4v4"/></svg></button><button id="vmm-visual-undo" type="button" aria-label="Einen Schritt zurück" title="Einen Schritt zurück" disabled><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 4L3 10l6 6M3 10h10a7 7 0 0 1 7 7v3"/></svg></button><button id="vmm-visual-redo" type="button" aria-label="Wiederherstellen" title="Wiederherstellen" disabled><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 4l6 6-6 6M21 10H11a7 7 0 0 0-7 7v3"/></svg></button><button id="vmm-visual-session" type="button" aria-label="Änderungen dieser Sitzung verwerfen" title="Änderungen dieser Sitzung verwerfen"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 10a8 8 0 1 1 1 8M4 4v6h6"/></svg></button><button id="vmm-visual-reset" type="button" aria-label="Alle Übersetzungen dieser Seite zurücksetzen" title="Alle Übersetzungen dieser Seite zurücksetzen"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14M10 10v7M14 10v7"/></svg></button><a id="vmm-visual-close" aria-label="Editor schließen" title="Editor schließen" href="<?php echo esc_url(get_permalink($id)); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5l14 14M19 5L5 19"/></svg></a></header>
<main id="vmm-visual-workspace"><iframe id="vmm-visual-frame" title="Website in der Übersetzungssprache" src="<?php echo esc_url($frame); ?>"></iframe><aside id="vmm-visual-panel" aria-label="Übersetzungsfelder"><h2>Element auswählen</h2><p>Die Website zeigt die gewählte Übersetzungssprache. Klicke auf einen Text, ein Bild oder einen Link.</p></aside></main><div id="vmm-visual-status" role="status" aria-live="polite"></div><?php wp_footer(); ?></body></html><?php exit;
  },3);
  add_action('after_setup_theme',static function(){if(!function_exists('YOOtheme\\app'))return;
   \YOOtheme\app()->extend(\YOOtheme\Builder::class,static function($builder){$builder->addTransform('prerender',static function($node,$params){
    if(!self::frame()||empty($node->vmm_id)||empty($params['post']->ID))return;
    $node->attrs['data-vmm-node']=$node->vmm_id;$node->attrs['data-vmm-page']=(string)$params['post']->ID;
    // Item templates sometimes only create a wrapper when custom attributes are present.
    $node->props['attributes']=trim(($node->props['attributes']??'')."\ndata-vmm-node=".$node->vmm_id."\ndata-vmm-page=".$params['post']->ID);
   },5);});
  },35);
  add_filter('the_content',static function($html){$id=get_the_ID();return self::frame()&&$id&&!ContentTranslations::builder(get_post($id))?'<div data-vmm-native="content" data-vmm-page="'.(int)$id.'">'.$html.'</div>':$html;},999);
  add_filter('wp_get_attachment_image_attributes',static function($attrs,$attachment){$id=get_the_ID();if(self::frame()&&$id&&(int)get_post_thumbnail_id($id)===$attachment->ID){$attrs['data-vmm-native']='thumbnail';$attrs['data-vmm-page']=(string)$id;}return $attrs;},99,2);
  $this->routes();
 }
 private function routes(): void {
  add_action('rest_api_init',function(){
   register_rest_route('vmm-multilingual/v1','/visual/page/(?P<id>\d+)',[
    'methods'=>['GET','PUT'],'permission_callback'=>static fn($r)=>in_array(get_post_type((int)$r['id']),ContentTranslations::types(),true)&&current_user_can('edit_post',(int)$r['id']),
    'callback'=>function($r){try{
     $id=(int)$r['id'];$data=$r->get_method()==='GET'?$r->get_params():$r->get_json_params();$locale=$data['locale']??'';
     if(!is_string($locale)||!isset(Languages::all()[$locale])||$locale===Languages::source())throw new \InvalidArgumentException('Übersetzungssprache auswählen.');
     if(strlen((string)$r->get_body())>1024*1024)throw new \InvalidArgumentException('Eingabe zu groß.');
     $native=ContentTranslations::document($id,$locale);$builder=$native['builder']?$this->editor->open($id,$locale):null;
     if($r->get_method()==='PUT'){
      if(($data['source_locale']??'')!==Languages::source())throw new \RuntimeException('Ausgangssprache geändert. Bitte neu laden.');
      if(($data['kind']??'')==='builder'){
       if(!$builder||$data['revision']!==$builder['revision']||($data['master_hash']??'')!==$builder['master_hash'])throw new \RuntimeException('Seite inzwischen geändert. Bitte neu laden.');
       $builder=$this->editor->saveFields($id,$data);
      }elseif(($data['kind']??'')==='native'){
       $fields=[];foreach($native['fields'] as $key=>$field)$fields[$key]=isset($native['records'][$key])?['mode'=>'custom','value'=>$native['values'][$key],'confirmed_same'=>$native['records'][$key]['confirmed_same']??false]:['mode'=>'inherit','value'=>''];
       foreach($data['fields']??[] as $key=>$value)$fields[$key]=$value;
       $native=ContentTranslations::save($id,['locale'=>$locale,'source_locale'=>$data['source_locale'],'revision'=>$data['revision'],'source_hash'=>$data['source_hash'],'status'=>$native['status']==='translated'?'translated':'draft','enabled'=>$native['enabled'],'fields'=>$fields]);
      }else throw new \InvalidArgumentException('Unbekannter Speicherweg.');
     }
     $labels=[];if($builder)foreach(\YOOtheme\app(\YOOtheme\Builder::class)->getTypes() as $name=>$type){foreach($type->jsonSerialize()['fields']??[] as $field=>$schema)$labels[$name][$field]=['label'=>$schema['label']??$field,'type'=>$schema['type']??'text'];}
     return ['native'=>$native,'builder'=>$builder,'labels'=>$labels];
    }catch(\Throwable $e){return new \WP_Error('vmm_visual',$e->getMessage(),['status'=>409]);}}
   ]);
   register_rest_route('vmm-multilingual/v1','/visual/discover',[
    'methods'=>'POST','permission_callback'=>static fn()=>current_user_can('manage_options'),
    'callback'=>static function($r){try{
     $data=$r->get_json_params();$plugin=$data['plugin']??'';$context=$data['context']??'';$path=$data['path']??'';$fields=$data['fields']??[];
     if(($data['source_locale']??'')!==Languages::source()||!is_string($plugin)||!isset(PluginTranslations::plugins()[$plugin])||!is_string($context)||!preg_match('/^(Shortcode \[|Widget |Block ).{1,250} · Instanz [1-9][0-9]*$/uD',$context)||!is_string($path)||!preg_match('~^root(?:/[a-z][a-z0-9-]*\[[1-9][0-9]*\]){1,40}$~D',$path)||!is_array($fields)||count($fields)>30||strlen((string)$r->get_body())>50000)throw new \InvalidArgumentException('Ungültige Elementzuordnung.');
     $bindings=[];$element=hash('sha256',$context.'|'.$path);
     foreach($fields as $slot=>$source){
      if(!is_string($slot)||!preg_match('/^(text:[0-9]+|alt|title|aria-label|aria-description|placeholder|src|href)$/D',$slot)||!is_string($source)||strlen($source)>4000)throw new \InvalidArgumentException('Ungültiges Elementfeld.');
      $key='element:'.hash('sha256',$context.'|'.$path.'|'.$slot);$type=$slot==='src'?'image':($slot==='href'?'url':'text');
      $row=PluginTranslations::row($key,$context.' · '.$slot,$source,$type,['area'=>'website','kind'=>$type!=='text'?'resources':($slot==='placeholder'?'help':'content'),'group'=>$context.' · '.$path,'context'=>$context,'path'=>$path.' · '.$slot,'origin'=>'runtime','element'=>$element,'slot'=>$slot,'visual'=>true]);
      PluginDiscovery::record($plugin,$row,true);$bindings[$slot]=$key;
     }
     PluginDiscovery::flush();return ['bindings'=>$bindings,'element'=>$element];
    }catch(\Throwable $e){return new \WP_Error('vmm_visual_discovery',$e->getMessage(),['status'=>400]);}}
   ]);
   register_rest_route('vmm-multilingual/v1','/visual/plugin',[
    'methods'=>['GET','PUT'],'permission_callback'=>static fn()=>current_user_can('manage_options'),
    'callback'=>static function($r){try{$data=$r->get_method()==='GET'?$r->get_params():$r->get_json_params();$plugin=$data['plugin']??'';$locale=$data['locale']??'';
     if(!is_string($plugin)||!is_string($locale)||!isset(Languages::all()[$locale])||$locale===Languages::source()||strlen((string)$r->get_body())>1024*1024)throw new \InvalidArgumentException('Ungültige Eingabe.');
     $strings=new PluginTranslations();if($r->get_method()==='PUT'){$rows=$strings->catalog($plugin,true);foreach($data['fields']??[] as $key=>$field)if(empty($rows[$key]['visual']))throw new \InvalidArgumentException('Dieses Feld ist nicht elementgenau zugeordnet.');$strings->save($plugin,$locale,$data['fields']??[],$data['revision']??-1,$data['source_hash']??'');}
     $rows=$strings->catalog($plugin,true);$doc=(array)(get_option(PluginTranslations::OPTION,[])[$plugin]??[]);return ['rows'=>$rows,'revision'=>(int)($doc['revision']??0),'source_hash'=>ContentTranslations::hash(array_map(static fn($r)=>$r['source'],$rows)),'records'=>$doc['locales'][$locale]??[]];
    }catch(\Throwable $e){return new \WP_Error('vmm_visual_plugin',$e->getMessage(),['status'=>409]);}}
   ]);
  });
 }
}
