<?php
declare(strict_types=1);
namespace VMM\Multilingual\Site;

/** Explicit catalogs: gettext discovery plus adapters for database-backed content. */
final class PluginTranslations
{
    public const OPTION='vmm_plugin_translations';
    private array $catalogs=[];
    private static ?string $context=null;
    private static ?string $contextArea=null;
    /** Integrations can bind background mail/PDF generation to the recipient locale. */
    public static function run(string $locale,callable $operation,?string $area=null): mixed {
        if(!isset(Languages::all()[$locale]))throw new \InvalidArgumentException('Unbekannte Sprache.');
        if($area!==null&&!in_array($area,['website','email','pdf','shared','system'],true))throw new \InvalidArgumentException('Unbekannter Bereich.');
        $old=self::$context;$oldArea=self::$contextArea;self::$context=$locale;self::$contextArea=$area;try{return $operation();}finally{self::$context=$old;self::$contextArea=$oldArea;}
    }
    public static function area(): string {return self::$contextArea??(self::$context!==null?'shared':(is_admin()?'system':'website'));}
    public static function locale(): string {return self::runtimeLocale(new LanguageUrls());}
    private static function runtimeLocale(LanguageUrls $urls): string {
        if(self::$context!==null)return self::$context;
        $locale=(isset($GLOBALS['wp_locale_switcher'])&&function_exists('is_locale_switched')&&is_locale_switched())?get_locale():(is_admin()?get_user_locale():((defined('DOING_CRON')&&DOING_CRON)?get_locale():$urls->locale()));
        return isset(Languages::all()[$locale])?$locale:Languages::source();
    }
    public static function plugins(): array {require_once ABSPATH.'wp-admin/includes/plugin.php';return get_plugins();}
    public static function key(string $domain,string $context,string $text,int $form=0): string {return hash('sha256',wp_json_encode([$domain,$context,$text,$form]));}
    public static function row(string $key,string $label,string $value,string $type='text',array $extra=[]): array {return array_merge(['key'=>$key,'label'=>$label,'source'=>$value,'type'=>$type],$extra);}
    public function catalog(string $plugin,bool $refresh=false): array {
        $plugins=self::plugins();if(!isset($plugins[$plugin]))throw new \InvalidArgumentException('Unbekanntes Plugin.');
        if(isset($this->catalogs[$plugin])&&!$refresh)return $this->catalogs[$plugin];
        $cache='vmm_catalog_'.md5('all-v2'.$plugin.($plugins[$plugin]['Version']??''));$rows=$refresh?false:get_transient($cache);
        if(!is_array($rows)) {
            $rows=[];$root=dirname(WP_PLUGIN_DIR.'/'.$plugin);$single=dirname($plugin)==='.';
            $files=$single?[new \SplFileInfo(WP_PLUGIN_DIR.'/'.$plugin)]:new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS));
            foreach($files as $file){if(!$file->isFile()||!in_array(strtolower($file->getExtension()),['php','js'],true))continue;$relative=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));
                if(preg_match('~(^|/)(vendor|lib|node_modules|tests)(/|\.)~i',$relative)||$file->getSize()>2000000)continue;
                $source=(string)file_get_contents($file->getPathname());$entries=$file->getExtension()==='js'?self::extractJs($source):self::extract($source);
                foreach($entries as $entry){foreach(range(0,$entry['plural']!==null?3:0) as $form){$key=self::key($entry['domain'],$entry['context'],$entry['text'],$form);$rows[$key]=self::row($key,$relative.($entry['plural']!==null?' · Pluralform '.($form+1):''),$form===0?$entry['text']:$entry['plural'],'text',array_merge($entry,['form'=>$form,'area'=>'system']));}}
            }
            set_transient($cache,$rows,DAY_IN_SECONDS);
        }
        foreach($rows as &$row)$row['origins']=['file'];unset($row);
        foreach((array)(get_option(PluginDiscovery::OPTION,[])[$plugin]??[]) as $key=>$row){$row['origins']=array_values(array_unique(array_merge($rows[$key]['origins']??[],['runtime'])));$rows[$key]=$row;}
        foreach(PluginContent::catalog($plugin) as $key=>$row){$binding=$rows[$key]??[];if(!empty($binding['visual']))$row+=array_intersect_key($binding,array_flip(['visual','element','slot']));$row['origins']=array_values(array_unique(array_merge($binding['origins']??[],['integration'])));$rows[$key]=$row;}
        $rows=apply_filters('vmm_plugin_catalog',$rows,$plugin);
        foreach($rows as $key=>&$row)$row['source']=self::value($plugin,$key,$row['source'],Languages::source());unset($row);
        return $this->catalogs[$plugin]=$rows;
    }
    /** Read literal gettext arguments, never execute plugin source. */
    public static function extract(string $php): array {
        $tokens=token_get_all($php);$out=[];$functions=['__'=>[0,1,null,null],'_e'=>[0,1,null,null],'esc_html__'=>[0,1,null,null],'esc_attr__'=>[0,1,null,null],'esc_html_e'=>[0,1,null,null],'esc_attr_e'=>[0,1,null,null],'_x'=>[0,2,1,null],'_ex'=>[0,2,1,null],'esc_html_x'=>[0,2,1,null],'esc_attr_x'=>[0,2,1,null],'_n'=>[0,3,null,1],'_nx'=>[0,4,3,1]];
        for($i=0,$count=count($tokens);$i<$count;$i++) {
            $t=$tokens[$i];if(!is_array($t)||$t[0]!==T_STRING||!isset($functions[$t[1]]))continue;
            $j=$i+1;while(isset($tokens[$j])&&is_array($tokens[$j])&&in_array($tokens[$j][0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true))$j++;
            if(($tokens[$j]??null)!=='(')continue;
            $args=[];$arg=[];$depth=0;
            for($j++;$j<$count;$j++){$v=$tokens[$j];if($v==='('||$v==='[')$depth++;if(($v===')'||$v===']')&&$depth>0)$depth--;elseif($v===')'&&$depth===0){$args[]=$arg;break;}if($v===','&&$depth===0){$args[]=$arg;$arg=[];}else if(!(is_array($v)&&in_array($v[0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true)))$arg[]=$v;}
            $literal=static function($a):?string{if(count($a)!==1||!is_array($a[0])||$a[0][0]!==T_CONSTANT_ENCAPSED_STRING)return null;$s=$a[0][1];return $s[0]==="'"?str_replace(["\\'","\\\\"],["'","\\"],substr($s,1,-1)):stripcslashes(substr($s,1,-1));};
            [$textIndex,$domainIndex,$contextIndex,$pluralIndex]=$functions[$t[1]];
            $text=$literal($args[$textIndex]??[]);$domain=$literal($args[$domainIndex]??[])??'default';$context=$contextIndex!==null?$literal($args[$contextIndex]??[]):'';$plural=$pluralIndex!==null?$literal($args[$pluralIndex]??[]):null;
            if($text!==null&&$text!=='')$out[]=['text'=>$text,'domain'=>$domain,'context'=>$context??'','plural'=>$plural];
        }return $out;
    }
    public static function value(string $plugin,string $key,string $source,string $locale): string {
        $data=(array)get_option(self::OPTION,[]);foreach(array_unique([$locale,Languages::source()]) as $language)if(isset($data[$plugin]['locales'][$language][$key]['value']))return $data[$plugin]['locales'][$language][$key]['value'];return $source;
    }
    public static function extractJs(string $source): array {
        // Literal __/_x calls; dynamic strings and minifier-renamed functions require an adapter.
        preg_match_all('/\b(__|_x)\s*\(\s*((?:"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\')(?:\s*,\s*(?:"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\')){1,2})\s*\)/s',$source,$matches,PREG_SET_ORDER);
        $rows=[];foreach($matches as $match)$rows=array_merge($rows,self::extract('<?php '.$match[1].'('.$match[2].');'));return $rows;
    }
    public static function placeholders(string $text): array {preg_match_all('/\{[a-zA-Z_][a-zA-Z0-9_]*\}|(?<![0-9])%(?:\d+\$)?[-+0\' #]*\d*(?:\.\d+)?[bcdeEfFgGosuxX]/',$text,$matches);$tokens=$matches[0];sort($tokens);return $tokens;}
    public static function pluralIndex(string $locale,int $n): int {
        $lang=substr($locale,0,2);
        if(in_array($lang,['ja','ko','zh'],true))return 0;
        if($lang==='fr')return $n>1?1:0;
        if(in_array($lang,['ru','uk','hr'],true))return $n%10===1&&$n%100!==11?0:($n%10>=2&&$n%10<=4&&($n%100<12||$n%100>14)?1:2);
        if($lang==='pl')return $n===1?0:($n%10>=2&&$n%10<=4&&($n%100<12||$n%100>14)?1:2);
        if(in_array($lang,['cs','sk'],true))return $n===1?0:($n>=2&&$n<=4?1:2);
        if($lang==='sl')return $n%100===1?0:($n%100===2?1:(in_array($n%100,[3,4],true)?2:3));
        if($lang==='ro')return $n===1?0:($n===0||($n%100>0&&$n%100<20)?1:2);
        return $n===1?0:1;
    }
    public static function pluralCount(string $locale): int {return max(array_map(static fn($n)=>self::pluralIndex($locale,$n),range(0,200)))+1;}
    public function save(string $plugin,string $locale,array $input,int $revision,string $sourceHash): void {
        if(!isset(Languages::all()[$locale])||$locale===Languages::source())throw new \InvalidArgumentException('Ungültige Übersetzungssprache.');
        $rows=$this->catalog($plugin,true);$source=array_map(static fn($r)=>$r['source'],$rows);
        global $wpdb;$lock='vmm_strings_'.get_current_blog_id();if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,5)',$lock))!==1)throw new \RuntimeException('Übersetzungen werden gerade gespeichert.');
        try{wp_cache_delete(self::OPTION,'options');$data=(array)get_option(self::OPTION,[]);$doc=$data[$plugin]??[];
            if((int)($doc['revision']??0)!==$revision||ContentTranslations::hash($source)!==$sourceHash)throw new \RuntimeException('Plugin-Inhalte wurden inzwischen geändert. Bitte neu laden.');
            $records=$doc['locales'][$locale]??[];
            foreach($input as $key=>$entry){if(!isset($rows[$key])||!is_array($entry))throw new \InvalidArgumentException('Unbekannter Plugin-Inhalt.');if(($entry['mode']??'')==='inherit'){unset($records[$key]);continue;}if(($entry['mode']??'')!=='custom'||!is_string($entry['value']??null))throw new \InvalidArgumentException('Ungültiger Plugin-Inhalt.');$value=$entry['value'];
                // Untouched records keep their source hash and cannot block unrelated edits.
                if(isset($records[$key]['value'])&&$records[$key]['value']===$value)continue;
                if(self::placeholders($source[$key])!==self::placeholders($value))throw new \InvalidArgumentException('Platzhalter müssen erhalten bleiben: '.$rows[$key]['label']);
                $value=apply_filters('vmm_plugin_validate_value',$value,$plugin,$key,$locale);
                $value=in_array($rows[$key]['type'],['url','image'],true)?esc_url_raw($value,['http','https','mailto','tel']):wp_kses_post($value);
                $records[$key]=['value'=>$value,'source_hash'=>ContentTranslations::hash([$source[$key]])];
                if(isset($rows[$key]['domain']))$records[$key]['gettext']=array_intersect_key($rows[$key],array_flip(['domain','context','text','plural','form']));
            }
            $doc['locales'][$locale]=$records;$doc['revision']=$revision+1;$data[$plugin]=$doc;update_option(self::OPTION,$data,false);
        }finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    }
    public function register(LanguageUrls $urls): void {
        $translate=static function($translated,$text,$domain,$context='')use($urls){$data=(array)get_option(self::OPTION,[]);$locale=self::runtimeLocale($urls);$key=self::key($domain,$context,$text);foreach($data as $plugin=>$doc){foreach(array_unique([$locale,Languages::source()]) as $language)if(isset($doc['locales'][$language][$key]['value']))return $doc['locales'][$language][$key]['value'];}return $translated;};
        add_filter('gettext',static fn($translated,$text,$domain)=>$translate($translated,$text,$domain),30,3);
        add_filter('gettext_with_context',static fn($translated,$text,$context,$domain)=>$translate($translated,$text,$domain,$context),30,4);
        $plural=static function($translated,$single,$multiple,$number,$domain,$context='')use($urls){$locale=self::runtimeLocale($urls);$key=self::key($domain,$context,$single,self::pluralIndex($locale,(int)$number));foreach((array)get_option(self::OPTION,[]) as $doc)foreach(array_unique([$locale,Languages::source()]) as $language)if(isset($doc['locales'][$language][$key]['value']))return $doc['locales'][$language][$key]['value'];return $translated;};
        add_filter('ngettext',static fn($value,$single,$multiple,$number,$domain)=>$plural($value,$single,$multiple,$number,$domain),30,5);
        add_filter('ngettext_with_context',static fn($value,$single,$multiple,$number,$context,$domain)=>$plural($value,$single,$multiple,$number,$domain,$context),30,6);
        $scripts=static function()use($urls){$domains=[];$locale=self::runtimeLocale($urls);foreach((array)get_option(self::OPTION,[]) as $doc)foreach(array_replace($doc['locales'][Languages::source()]??[],$doc['locales'][$locale]??[]) as $record){$entry=$record['gettext']??null;if(!$entry||$entry['plural']!==null)continue;$key=($entry['context']!==''?$entry['context']."\x04":'').$entry['text'];$domains[$entry['domain']][$key]=[$record['value']];}
            if(!$domains)return;global $wp_scripts;foreach($wp_scripts->queue as $handle){$script=$wp_scripts->registered[$handle]??null;if(!$script||!in_array('wp-i18n',$script->deps,true))continue;foreach($domains as $domain=>$entries)wp_add_inline_script($handle,'wp.i18n.setLocaleData(Object.assign({},wp.i18n.getLocaleData('.wp_json_encode($domain).'),'.wp_json_encode($entries,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'),'.wp_json_encode($domain).');','before');}
        };
        add_action('wp_enqueue_scripts',$scripts,999);add_action('admin_enqueue_scripts',$scripts,999);
    }
}
