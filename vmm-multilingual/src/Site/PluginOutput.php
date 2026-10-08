<?php
declare(strict_types=1);
namespace VMM\Multilingual\Site;

/** Plugin-owned output only: preserve markup, scripts and form values byte for byte. */
final class PluginOutput
{
    public static function owner(mixed $callback): ?string {
        try {
            $r=is_array($callback)?new \ReflectionMethod($callback[0],$callback[1]):(is_string($callback)&&str_contains($callback,'::')?new \ReflectionMethod($callback):new \ReflectionFunction(\Closure::fromCallable($callback)));
            $file=wp_normalize_path((string)$r->getFileName());$root=trailingslashit(wp_normalize_path(WP_PLUGIN_DIR));
            if(!str_starts_with($file,$root))return null;$relative=substr($file,strlen($root));
            foreach(PluginTranslations::plugins() as $plugin=>$info){$dir=dirname($plugin);if(!str_starts_with($plugin,'vmm-multilingual/')&&($relative===$plugin||($dir!=='.'&&str_starts_with($relative,$dir.'/'))))return $plugin;}
        } catch(\Throwable) {} return null;
    }
    public static function register(): void {
        add_filter('do_shortcode_tag',static function($output,$tag){global $shortcode_tags;$plugin=self::owner($shortcode_tags[$tag]??null);return $plugin?self::wrap($plugin,'Shortcode ['.$tag.']',(string)$output):$output;},99,2);
        add_filter('render_block',static function($output,$block){$type=\WP_Block_Type_Registry::get_instance()->get_registered($block['blockName']??'');$plugin=$type?self::owner($type->render_callback):null;return $plugin?self::wrap($plugin,'Block '.$block['blockName'],(string)$output):$output;},99,2);
        add_action('wp_loaded',static function(){global $wp_registered_widgets;
            foreach($wp_registered_widgets as $id=>&$widget){$callback=$widget['callback'];$ownerCallback=is_array($callback)&&is_object($callback[0])&&method_exists($callback[0],'widget')?[$callback[0],'widget']:$callback;$plugin=self::owner($ownerCallback);if(!$plugin)continue;
                $widget['callback']=static function(...$args)use($callback,$plugin,$id){$level=ob_get_level();ob_start();try{call_user_func_array($callback,$args);$html=ob_get_clean();echo self::wrap($plugin,'Widget '.$id,$html);}finally{while(ob_get_level()>$level)ob_end_clean();}};
            }unset($widget);
        },99);
        add_filter('vmm_plugin_output', [self::class,'filter'],10,4);
        add_action('wp_enqueue_scripts',static function(){
            wp_enqueue_script('vmm-plugin-output',plugins_url('assets/plugin-output.js',dirname(__DIR__,2).'/vmm-multilingual.php'),[],(string)filemtime(dirname(__DIR__,2).'/assets/plugin-output.js'),true);
            $map=[];$elements=[];foreach((array)get_option(PluginDiscovery::OPTION,[]) as $plugin=>$rows)foreach($rows as $row){$translated=PluginTranslations::value($plugin,$row['key'],$row['source'],PluginTranslations::locale());if($translated===$row['source'])continue;if(!empty($row['visual']))$elements[$plugin][$row['context']][]=['path'=>explode(' · ',$row['path'])[0],'slot'=>$row['slot'],'source'=>$row['source'],'value'=>$translated];elseif(!empty($row['output']))$map[$plugin][$row['context']][$row['source']]=$translated;}
            wp_localize_script('vmm-plugin-output','vmmPluginOutput',['translations'=>$map,'elements'=>$elements,'capture'=>PluginDiscovery::active()&&PluginTranslations::locale()===Languages::source(),'ajax'=>admin_url('admin-ajax.php'),'nonce'=>PluginDiscovery::active()?wp_create_nonce('vmm_output'):null]);
        });
        add_action('wp_ajax_vmm_output_capture',static function(){
            check_ajax_referer('vmm_output','nonce');if(!current_user_can('manage_options')||!PluginDiscovery::active())wp_send_json_error(null,403);
            $plugin=sanitize_text_field(wp_unslash($_POST['plugin']??''));$context=sanitize_text_field(wp_unslash($_POST['context']??''));
            if(!isset(PluginTranslations::plugins()[$plugin])||!preg_match('/^(Shortcode \[|Widget |Block )/',$context))wp_send_json_error(null,400);
            $rows=json_decode(wp_unslash($_POST['rows']??'[]'),true);if(!is_array($rows)||count($rows)>100)wp_send_json_error(null,400);
            foreach($rows as $row){if(!is_array($row)||!is_string($row['source']??null)||!preg_match('/^[a-z][a-z0-9-]*$/D',$row['tag']??''))continue;
                $attr=$row['attr']??'';if(!in_array($attr,['','placeholder','aria-label','aria-description','title','alt','src','href'],true))continue;
                $type=$attr==='src'?'image':($attr==='href'?'url':'text');$kind=$type!=='text'?'resources':($attr==='placeholder'?'help':(in_array($row['tag'],['label','button','option'],true)||$attr!==''?'labels':'content'));
                PluginTranslations::run(Languages::source(),static fn()=>self::field($plugin,$context.' · '.$row['tag'].($attr!==''?' · '.$attr:''),$row['source'],$type,$kind));
            }PluginDiscovery::flush();wp_send_json_success();
        });
    }
    private static function wrap(string $plugin,string $context,string $html): string {
        $rendered=self::html($plugin,$context,$html);
        return '<div class="vmm-plugin-output" style="display:contents" data-plugin="'.esc_attr($plugin).'" data-context="'.esc_attr($context).'">'.$rendered.'</div>';
    }
    public static function filter(string $html,string $plugin,string $context,string $area='website'): string {return self::html($plugin,$context,$html,$area);}
    public static function field(string $plugin,string $context,string $source,string $type='text',string $kind='content',string $area='website'): string {
        $source=html_entity_decode($source,ENT_QUOTES|ENT_HTML5,'UTF-8');
        if(trim($source)===''||!preg_match('/\p{L}/u',$source)||strlen($source)>4000)return $source;
        $key='output:'.hash('sha256',wp_json_encode([$context,$type,$source]));
        $row=PluginTranslations::row($key,$context,$source,$type,['area'=>$area,'kind'=>$kind,'group'=>$context,'context'=>$context,'path'=>$context,'origin'=>'runtime','output'=>true]);
        // Only the source locale may populate the catalog, otherwise translations become sources.
        if(PluginTranslations::locale()===Languages::source())PluginDiscovery::record($plugin,$row);
        return PluginTranslations::value($plugin,$key,$source,PluginTranslations::locale());
    }
    public static function html(string $plugin,string $context,string $html,string $area='website'): string {
        $html=VisualPlugin::render($plugin,$context,$html,$area);
        if(strlen($html)>2000000)return $html;
        $parts=preg_split('~(<!--.*?-->|<(?:"[^"]*"|\'[^\']*\'|[^\'">])*>)~s',$html,-1,PREG_SPLIT_DELIM_CAPTURE);if($parts===false)return $html;
        $skip=[];$tag='';$parents=[];
        foreach($parts as &$part){
            if(str_starts_with($part,'<')){
                if(preg_match('~^</(script|style|textarea|pre|code|svg|noscript)\b~i',$part)){array_pop($skip);continue;}
                if(preg_match('~^<(script|style|textarea|pre|code|svg|noscript)\b~i',$part,$m)){$skip[]=$m[1];continue;}
                if($skip)continue;
                if(preg_match('~^</([a-z][a-z0-9-]*)\b~i',$part,$m)){while($parents){$closed=array_pop($parents);if($closed===strtolower($m[1]))break;}$tag=end($parents)?:'';continue;}
                if(preg_match('~^<([a-z][a-z0-9-]*)\b~i',$part,$m))$tag=strtolower($m[1]);else continue;
                $part=preg_replace_callback('~\b(placeholder|aria-label|aria-description|title|alt|src|href)\s*=\s*(["\'])(.*?)\2~is',static function($m)use($plugin,$context,$area,$tag){
                    $attr=strtolower($m[1]);if($attr==='src'&&$tag!=='img')return $m[0];
                    if(in_array($attr,['href','src'],true)&&preg_match('~^(?:javascript:|data:|#)~i',$m[3]))return $m[0];
                    $type=$attr==='src'?'image':($attr==='href'?'url':'text');$kind=$type!=='text'?'resources':($attr==='placeholder'?'help':'labels');
                    $translated=self::field($plugin,$context.' · '.$tag.' · '.$attr,$m[3],$type,$kind,$area);
                    if($translated===html_entity_decode($m[3],ENT_QUOTES|ENT_HTML5,'UTF-8'))return $m[0];
                    return $m[1].'='.$m[2].esc_attr($translated).$m[2];
                },$part)??$part;
                if(!in_array($tag,['area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr'],true)&&!str_ends_with($part,'/>'))$parents[]=$tag;
                $tag=end($parents)?:'';continue;
            }
            if($skip||trim($part)==='')continue;
            $translated=self::field($plugin,$context.' · '.$tag,$part,'text',in_array($tag,['label','button','option'],true)?'labels':'content',$area);
            if($translated!==html_entity_decode($part,ENT_QUOTES|ENT_HTML5,'UTF-8'))$part=esc_html($translated);
        }unset($part);return implode('',$parts);
    }
}
