<?php
declare(strict_types=1);
namespace VMM\Multilingual\Site;

/** Language overlays for native posts. The WordPress post remains the physical baseline. */
final class ContentTranslations
{
    public const KEY = '_vmm_content';
    private bool $reading = false;
    private static int $rawDepth = 0;
    public static function types(): array { return array_values(array_diff(get_post_types(['public'=>true]), ['attachment'])); }
    public static function builder(\WP_Post $post): bool {
        return function_exists('YOOtheme\\app') && (bool)\YOOtheme\Builder\Wordpress\PostHelper::matchContent($post->post_content);
    }
    public static function fields(int $id): array {
        self::$rawDepth++;
        try {
        $post = get_post($id);
        $fields = ['title'=>['label'=>'Titel','type'=>'text','value'=>$post->post_title], 'excerpt'=>['label'=>'Auszug','type'=>'textarea','value'=>$post->post_excerpt], 'thumbnail'=>['label'=>'Beitragsbild','type'=>'image','value'=>(string)get_post_thumbnail_id($id)]];
        $image=(int)$fields['thumbnail']['value'];
        $fields['image_alt']=['label'=>'Beitragsbild · Alternativtext','type'=>'text','value'=>$image?(string)get_post_meta($image,'_wp_attachment_image_alt',true):''];
        $fields['image_caption']=['label'=>'Beitragsbild · Bildunterschrift','type'=>'textarea','value'=>$image?(string)(get_post($image)?->post_excerpt??''):''];
        if (!self::builder($post)) $fields['content'] = ['label'=>'Inhalt','type'=>'content','value'=>$post->post_content];
        foreach (PageMetadata::FIELDS as $key=>$label) {
            if ($key === 'title') continue;
            $native = match($key) {'slug'=>$post->post_name,'seo_title'=>get_post_meta($id,'_yoast_wpseo_title',true),'description'=>get_post_meta($id,'_yoast_wpseo_metadesc',true),'social_title'=>get_post_meta($id,'_yoast_wpseo_opengraph-title',true),'social_description'=>get_post_meta($id,'_yoast_wpseo_opengraph-description',true),default=>''};
            $fields[$key] = ['label'=>$label,'type'=>str_contains($key,'description')?'textarea':'text','value'=>(string)$native];
        }
        $fields['social_image']=['label'=>'Social-Media-Bild','type'=>'url','value'=>(string)get_post_meta($id,'_yoast_wpseo_opengraph-image',true)];
        $rules=(array)get_option('vmm_field_rules',[]);
        foreach (get_post_meta($id) as $key=>$values) {
            $value=get_post_meta($id,$key,true);
            $rule=$rules[$post->post_type][$key]??(!is_protected_meta($key,'post')&&!is_numeric($value)?'text':'shared');
            if ($rule==='shared' || str_starts_with($key,'_vmm_') || str_starts_with($key,'_yoast_')) continue;
            if(count($values)!==1)continue;
            if(is_array($value)){
                $walk=function(array $array,array $path=[])use(&$walk,&$fields,$key,$rule):void{foreach($array as $part=>$leaf){$next=[...$path,$part];if(is_array($leaf))$walk($leaf,$next);elseif(is_string($leaf)&&!is_numeric($leaf)){$field='meta-json:'.rtrim(strtr(base64_encode(wp_json_encode([$key,$next])),'+/','-_'),'=');$fields[$field]=['label'=>'Custom Field: '.$key.' / '.implode(' / ',$next),'type'=>$rule==='url'?'url':'textarea','value'=>$leaf];}}};$walk($value);continue;
            }
            if (!is_scalar($value)) continue;
            $fields['meta:'.$key]=['label'=>'Custom Field: '.$key,'type'=>in_array($rule,['image','url','textarea'],true)?$rule:'text','value'=>(string)$value];
        }
        return apply_filters('vmm_content_fields',$fields,$id);
        } finally {self::$rawDepth--;}
    }
    /** Consolidate legacy metadata without overwriting explicit native translations. */
    public static function migrateMetadata(): void {
        global $wpdb;
        $ids=$wpdb->get_col($wpdb->prepare("SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key=%s",PageMetadata::KEY));
        foreach($ids as $id){$id=(int)$id;$lock='vmm_'.get_current_blog_id().'_'.$id;
            if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,5)',$lock))!==1)continue;
            try {
                wp_cache_delete($id,'post_meta');
                $legacy=(array)get_post_meta($id,PageMetadata::KEY,true);if(empty($legacy['locales']))continue;
                $data=(array)get_post_meta($id,self::KEY,true);$source=self::effective($id,Languages::source());
                foreach($legacy['locales'] as $locale=>$fields)foreach($fields as $key=>$value){
                    if($value===''||!isset(PageMetadata::FIELDS[$key])||isset($data['locales'][$locale]['fields'][$key]))continue;
                    $data['locales'][$locale]['fields'][$key]=['value'=>(string)$value,'source_hash'=>self::hash([(string)($source[$key]??'')])];
                }
                $data['revision']=(int)($data['revision']??0)+1;
                if(!metadata_exists('post',$id,'_vmm_page_metadata_backup'))update_post_meta($id,'_vmm_page_metadata_backup',wp_slash($legacy));
                update_post_meta($id,self::KEY,wp_slash($data));
                delete_post_meta($id,PageMetadata::KEY);
            }finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
        }
    }
    public static function effective(int $id,string $locale): array {
        $values=array_map(static fn($f)=>(string)$f['value'],self::fields($id));
        $metadata=(array)get_post_meta($id,PageMetadata::KEY,true);
        $data=(array)get_post_meta($id,self::KEY,true);
        foreach (array_unique([Languages::source(),$locale]) as $language) {
            foreach ($metadata['locales'][$language]??[] as $key=>$value) if ($value!=='' && isset($values[$key])) $values[$key]=(string)$value;
            foreach ($data['locales'][$language]['fields']??[] as $key=>$record) if (isset($values[$key]) && isset($record['value'])) $values[$key]=(string)$record['value'];
        }
        return $values;
    }
    /** Ignore the harmless paragraph wrappers introduced by the visual rich-text editor. */
    public static function sameValue(string $a,string $b): bool { $normalize=static fn($v)=>trim(preg_replace('/\s+/u',' ',html_entity_decode(preg_replace('/<\/?p(?:\s[^>]*)?>|<br\s*\/?>/i',"\n",$v),ENT_QUOTES|ENT_HTML5,'UTF-8')));return $normalize($a)===$normalize($b); }
    public static function hash(array $values): string { return hash('sha256',wp_json_encode($values)); }
    public static function document(int $id,string $locale): array {
        $post=get_post($id);$data=(array)get_post_meta($id,self::KEY,true);$source=self::effective($id,Languages::source());
        $records=$data['locales'][$locale]['fields']??[];$status=$data['locales'][$locale]['status']??'inherited';
        foreach($records as $key=>$record) if(isset($source[$key]) && ($record['source_hash']??'')!==self::hash([$source[$key]])) {$status='outdated';break;}
        $fields=self::fields($id);foreach($fields as $key=>&$field){$field['source_hash']=self::hash([$source[$key]]);if($key==='content')$field['preview']=wp_kses_post(wpautop($source[$key]));if($field['type']==='image'){$field['source_url']=wp_get_attachment_image_url((int)$source[$key],'medium')?:'';$field['image_url']=wp_get_attachment_image_url((int)(self::effective($id,$locale)[$key]??0),'medium')?:'';}}unset($field);
        return ['id'=>$id,'locale'=>$locale,'source_locale'=>Languages::source(),'revision'=>(int)($data['revision']??0),'source_hash'=>self::hash($source),'source'=>$source,'values'=>self::effective($id,$locale),'records'=>$records,'fields'=>$fields,'status'=>$status,'enabled'=>$data['locales'][$locale]['enabled']??true,'block_editor'=>has_blocks($source['content']??'')||use_block_editor_for_post($post),'builder'=>self::builder($post)];
    }
    public static function available(int $id,string $locale): bool { $data=get_post_meta($id,self::KEY,true);return $locale===Languages::source() || ($data['locales'][$locale]['enabled']??true); }
    public static function save(int $id,array $input): array {
        $locale=$input['locale']??'';
        if (!is_string($locale)||!isset(Languages::all()[$locale])||$locale===Languages::source()) throw new \InvalidArgumentException('Bitte eine aktive Übersetzungssprache auswählen.');
        global $wpdb;$lock='vmm_'.get_current_blog_id().'_'.$id;
        if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,5)',$lock))!==1)throw new \RuntimeException('Inhalt wird gerade gespeichert.');
        try {
            wp_cache_delete($id,'post_meta');wp_cache_delete('vmm_source_language','options');$doc=self::document($id,$locale);
            if(($input['source_locale']??'')!==$doc['source_locale'] || ($input['source_hash']??'')!==$doc['source_hash'] || ($input['revision']??-1)!==$doc['revision'])throw new \RuntimeException('Inhalt wurde inzwischen geändert. Bitte neu laden.');
            $fields=$input['fields']??null;if(!is_array($fields))throw new \InvalidArgumentException('Ungültige Felder.');
            $records=[];
            foreach($fields as $key=>$entry) {
                if(!isset($doc['fields'][$key])||!is_array($entry)||!in_array($entry['mode']??'', ['inherit','custom'],true))throw new \InvalidArgumentException('Ungültiges Feld.');
                if($entry['mode']==='inherit')continue;
                $value=$entry['value']??null;if(!is_string($value))throw new \InvalidArgumentException('Ungültiger Feldwert.');
                $type=$doc['fields'][$key]['type'];
                $value=match($type){'content','textarea'=>wp_kses_post($value),'url'=>esc_url_raw($value,['http','https','mailto','tel']),'image'=>(string)absint($value),default=>sanitize_text_field($value)};
                if($type==='image' && $value!=='0' && (!wp_attachment_is_image((int)$value)))throw new \InvalidArgumentException('Bitte ein Bild aus der Mediathek auswählen.');
                if($key==='slug')$value=sanitize_title($value);
                if($key==='slug'&&($value===''||in_array($value,array_merge(array_column(Languages::all(),'slug'),['wp-admin','wp-json','feed','wp-login.php']),true)))throw new \InvalidArgumentException('Bitte einen nicht reservierten Slug eingeben.');
                $records[$key]=['value'=>$value,'confirmed_same'=>!empty($entry['confirmed_same'])&&self::sameValue($value,$doc['source'][$key]),'source_hash'=>self::hash([$doc['source'][$key]])];
            }
            if(isset($records['slug'])) {
                $urls=new LanguageUrls();$current=$urls->path($id,$locale);
                if($current!==''){$prefix=str_contains($current,'/')?substr($current,0,strrpos($current,'/')+1):'';$candidate=$prefix.$records['slug']['value'];
                    foreach(get_posts(['post_type'=>self::types(),'post_status'=>['publish','private','draft','pending','future'],'numberposts'=>-1]) as $other)if($other->ID!==$id&&$urls->path($other->ID,$locale)===$candidate)throw new \InvalidArgumentException('Dieser Sprach-Slug ist bereits vergeben.');
                }
            }
            // Preserve fields currently hidden by a rule or plugin deactivation.
            $old=(array)get_post_meta($id,self::KEY,true);
            foreach($old['locales'][$locale]['fields']??[] as $key=>$record)if(!isset($doc['fields'][$key]))$records[$key]=$record;
            $status=$input['status']??'draft';if(!in_array($status,['draft','translated','inherited'],true))throw new \InvalidArgumentException('Ungültiger Status.');
            $old['locales'][$locale]=['fields'=>$records,'status'=>$records?$status:'inherited','enabled'=>(bool)($input['enabled']??true)];$old['revision']=$doc['revision']+1;
            update_post_meta($id,self::KEY,wp_slash($old));
            return self::document($id,$locale);
        } finally {$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    }
    public function register(LanguageUrls $urls): void {
        foreach(['title_edit_pre'=>'title','content_edit_pre'=>'content','excerpt_edit_pre'=>'excerpt'] as $hook=>$field)add_filter($hook,static function($value,$id)use($field){if(!$id||!in_array(get_post_type($id),self::types(),true))return $value;return self::effective((int)$id,Languages::source())[$field]??$value;},30,2);
        add_action('init',static function(){foreach(self::types() as $type){
            add_filter('rest_prepare_'.$type,static function($response,$post,$request){if($request['context']!=='edit'||!current_user_can('edit_post',$post->ID))return $response;$values=self::effective($post->ID,Languages::source());$data=$response->get_data();foreach(['title','content','excerpt'] as $field)if(isset($values[$field],$data[$field]['raw']))$data[$field]['raw']=$values[$field];if(isset($data['featured_media']))$data['featured_media']=(int)$values['thumbnail'];foreach($data['meta']??[] as $key=>$native)if(isset($values['meta:'.$key]))$data['meta'][$key]=is_int($native)?(int)$values['meta:'.$key]:$values['meta:'.$key];$response->set_data($data);return $response;},30,3);
            add_action('rest_after_insert_'.$type,static fn($post)=>self::nativeSaved($post),100);
        }},30);
        add_action('save_post',static function($id,$post){if(is_admin()&&!wp_doing_ajax()&&($_POST['action']??'')==='editpost')self::nativeSaved($post);},100,2);
        foreach(['added_post_meta','updated_post_meta','deleted_post_meta'] as $hook)add_action($hook,static function($metaId,$id,$key){if(!str_starts_with($key,'_vmm_')&&current_user_can('edit_post',$id)&&(($_POST['action']??'')==='editpost'||(defined('REST_REQUEST')&&REST_REQUEST&&str_contains((string)($_SERVER['REQUEST_URI']??''),'/wp/v2/'))))self::nativeMetaSaved((int)$id,$key);},100,3);
        $front=static fn()=>!is_admin()&&!(defined('REST_REQUEST')&&REST_REQUEST);
        add_filter('the_title',static fn($value,$id)=>$front()&&$id&&in_array(get_post_type($id),self::types(),true)?(self::effective((int)$id,$urls->locale())['title']??$value):$value,35,2);
        foreach(['the_content'=>'content','get_the_excerpt'=>'excerpt'] as $hook=>$field)add_filter($hook,static function($value)use($front,$urls,$field){$id=get_the_ID();return $front()&&$id&&in_array(get_post_type($id),self::types(),true)?(self::effective($id,$urls->locale())[$field]??$value):$value;},8);
        add_filter('get_post_metadata',function($value,$id,$key,$single)use($front,$urls){
            $editing=is_admin()&&!wp_doing_ajax()&&($GLOBALS['pagenow']??'')==='post.php'&&(int)$id===absint($_GET['post']??0)&&($_GET['action']??'')==='edit'&&current_user_can('edit_post',$id);
            if($this->reading||self::$rawDepth>0||(!$front()&&!$editing)||$key===''||str_starts_with($key,'_vmm_')||!in_array(get_post_type($id),self::types(),true))return $value;
            $map=['_thumbnail_id'=>'thumbnail','_yoast_wpseo_title'=>'seo_title','_yoast_wpseo_metadesc'=>'description','_yoast_wpseo_opengraph-title'=>'social_title','_yoast_wpseo_opengraph-description'=>'social_description','_yoast_wpseo_opengraph-image'=>'social_image'];$field=$map[$key]??'meta:'.$key;
            $this->reading=true;try{$doc=self::effective((int)$id,$editing?Languages::source():$urls->locale());if(array_key_exists($field,$doc))return $single?$doc[$field]:[$doc[$field]];
                $array=null;foreach($doc as $name=>$translated)if(str_starts_with($name,'meta-json:')){$path=json_decode(base64_decode(strtr(substr($name,10),'-_','+/')),true);if(($path[0]??null)!==$key)continue;if($array===null)$array=get_post_meta($id,$key,true);$cursor=&$array;foreach($path[1] as $part)$cursor=&$cursor[$part];$cursor=$translated;unset($cursor);}return $array!==null?[$array]:$value;
            }finally{$this->reading=false;}
        },30,4);
        foreach(['wpseo_title'=>'seo_title','wpseo_metadesc'=>'description','wpseo_opengraph_title'=>'social_title','wpseo_twitter_title'=>'social_title','wpseo_opengraph_desc'=>'social_description','wpseo_twitter_description'=>'social_description','wpseo_opengraph_image'=>'social_image','wpseo_twitter_image'=>'social_image','pre_get_document_title'=>'seo_title'] as $hook=>$field)add_filter($hook,static function($value)use($front,$urls,$field){if(!$front()||!is_singular(self::types()))return $value;$doc=self::effective(get_queried_object_id(),$urls->locale());return ($doc[$field]??'')?:$value;},40);
        add_action('template_redirect',static function()use($urls){if(is_singular(self::types())&&!self::available(get_queried_object_id(),$urls->locale())){global $wp_query;$wp_query->set_404();status_header(404);nocache_headers();}},1);
        add_filter('wp_get_attachment_image_attributes',static function($attributes,$attachment)use($front,$urls){$id=get_the_ID();if($front()&&$id&&in_array(get_post_type($id),self::types(),true)){$doc=self::effective($id,$urls->locale());if((int)$doc['thumbnail']===$attachment->ID)$attributes['alt']=$doc['image_alt'];}return $attributes;},30,2);
        add_filter('get_the_post_thumbnail_caption',static function($caption,$id)use($front,$urls){return $front()&&in_array(get_post_type($id),self::types(),true)?self::effective((int)$id,$urls->locale())['image_caption']:$caption;},30,2);
    }
    private static function nativeSaved(\WP_Post $post): void {
        if(wp_is_post_revision($post->ID)||wp_is_post_autosave($post->ID)||!in_array($post->post_type,self::types(),true))return;
        $data=(array)get_post_meta($post->ID,self::KEY,true);
        // Only synchronize source overlays once one exists; ordinary WordPress saves stay ordinary.
        if(empty($data['locales'][Languages::source()]['fields']))return;
        foreach(['title'=>$post->post_title,'excerpt'=>$post->post_excerpt]+(!self::builder($post)?['content'=>$post->post_content]:[]) as $field=>$value)$data['locales'][Languages::source()]['fields'][$field]=['value'=>$value,'source_hash'=>self::hash([$value])];
        $data['revision']=(int)($data['revision']??0)+1;update_post_meta($post->ID,self::KEY,wp_slash($data));
    }
    private static function nativeMetaSaved(int $id,string $key): void {
        if(!in_array(get_post_type($id),self::types(),true))return;$data=(array)get_post_meta($id,self::KEY,true);$source=Languages::source();if(empty($data['locales'][$source]['fields']))return;
        $map=['thumbnail'=>'_thumbnail_id','seo_title'=>'_yoast_wpseo_title','description'=>'_yoast_wpseo_metadesc','social_title'=>'_yoast_wpseo_opengraph-title','social_description'=>'_yoast_wpseo_opengraph-description','social_image'=>'_yoast_wpseo_opengraph-image'];$changed=false;
        foreach(self::fields($id) as $field=>$schema){$meta=$map[$field]??(str_starts_with($field,'meta:')?substr($field,5):null);if(str_starts_with($field,'meta-json:'))$meta=json_decode(base64_decode(strtr(substr($field,10),'-_','+/')),true)[0]??null;if($meta!==$key)continue;$value=(string)$schema['value'];$data['locales'][$source]['fields'][$field]=['value'=>$value,'source_hash'=>self::hash([$value])];$changed=true;}
        if($changed){$data['revision']=(int)($data['revision']??0)+1;update_post_meta($id,self::KEY,wp_slash($data));}
    }
}
