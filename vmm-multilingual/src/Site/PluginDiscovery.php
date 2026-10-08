<?php
declare(strict_types=1);
namespace VMM\Multilingual\Site;

/** Bounded, administrator-initiated discovery of actual WordPress translation calls. */
final class PluginDiscovery
{
    public const OPTION='vmm_discovered_strings';
    private static bool $enabled=false;
    private static bool $reading=false;
    private static array $pending=[];
    private static int $count=0;
    private static ?array $owners=null;
    public static function active(): bool {return self::$enabled;}
    /** Shared sink for renderers; never capture visitor-entered form values. */
    public static function record(string $plugin,array $row,bool $explicitEditor=false): void {
        if((!self::$enabled&&!$explicitEditor)||self::$count>=500||strlen($row['source'])>4000||(trim($row['source'])===''&&!$explicitEditor))return;
        self::$count++;self::$pending[$plugin][$row['key']]=$row+['origin'=>'runtime'];
    }
    public static function register(): void {
        add_action('init',static function(){self::$enabled=current_user_can('manage_options')&&(bool)get_transient('vmm_discovery_'.get_current_user_id());},20);
        add_action('shutdown',[self::class,'flush']);
        add_filter('gettext',static function($value,$text,$domain){self::observe($text,$domain);return $value;},5,3);
        add_filter('gettext_with_context',static function($value,$text,$context,$domain){self::observe($text,$domain,$context);return $value;},5,4);
        add_filter('ngettext',static function($value,$single,$plural,$number,$domain){self::observe($single,$domain,'',$plural);return $value;},5,5);
        add_filter('ngettext_with_context',static function($value,$single,$plural,$number,$context,$domain){self::observe($single,$domain,$context,$plural);return $value;},5,6);
    }
    public static function observe(string $text,string $domain,string $context='',?string $plural=null): void {
        if(!self::$enabled||self::$reading||$text===''||strlen($text)>4000||self::$count>=500)return;
        self::$reading=true;
        try{
            if(self::$owners===null){self::$owners=[];foreach(PluginTranslations::plugins() as $file=>$info){if(str_starts_with($file,'vmm-multilingual/'))continue;$root=dirname($file)==='.'?$file:dirname($file).'/';self::$owners[$root]=$file;}}
            $plugin=null;$path='';$root=trailingslashit(wp_normalize_path(WP_PLUGIN_DIR));
            foreach(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,30) as $frame){if(!in_array($frame['function']??'',['__','_e','_x','_ex','esc_html__','esc_attr__','esc_html_e','esc_attr_e','esc_html_x','esc_attr_x','_n','_nx'],true))continue;$file=wp_normalize_path($frame['file']??'');if(!str_starts_with($file,$root))continue;$relative=substr($file,strlen($root));foreach(self::$owners as $prefix=>$owner)if($relative===$prefix||(str_ends_with($prefix,'/')&&str_starts_with($relative,$prefix))){$plugin=$owner;$path=$relative;break 2;}}
            if(!$plugin)return;
            self::$count++;$area=PluginTranslations::area();
            foreach(range(0,$plural!==null?3:0) as $form){$key=PluginTranslations::key($domain,$context,$text,$form);self::$pending[$plugin][$key]=PluginTranslations::row($key,'Erfasst · '.$path,$form===0?$text:(string)$plural,'text',['domain'=>$domain,'context'=>$context,'text'=>$text,'plural'=>$plural,'form'=>$form,'area'=>$area,'origin'=>'runtime']);}
        }finally{self::$reading=false;}
    }
    public static function flush(): void {
        if(!self::$pending)return;global $wpdb;$lock='vmm_discovery_'.get_current_blog_id();
        if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,2)',$lock))!==1)return;
        try{wp_cache_delete(self::OPTION,'options');$saved=(array)get_option(self::OPTION,[]);foreach(self::$pending as $plugin=>$rows){$saved[$plugin]=array_replace($saved[$plugin]??[],$rows);if(count($saved[$plugin])>3000)$saved[$plugin]=array_slice($saved[$plugin],-3000,null,true);}update_option(self::OPTION,$saved,false);self::$pending=[];}finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    }
}
