<?php
declare(strict_types=1);
namespace VMM\Multilingual\Admin;
use VMM\Multilingual\Site\{ContentTranslations as Content,Languages};

final class ContentScreen
{
    public function register(): void {
        add_action('add_meta_boxes',function(){foreach(Content::types() as $type)add_meta_box('vmm-native','VMM · Übersetzungen',[$this,'box'],$type,'side','high');});
        foreach(['post_row_actions','page_row_actions'] as $hook)add_filter($hook,static function($actions,$post){if(in_array($post->post_type,Content::types(),true)&&current_user_can('edit_post',$post->ID))$actions['vmm-content']='<a href="'.esc_url(admin_url('admin.php?page=vmm-content&object_id='.$post->ID)).'">Übersetzen</a>';return $actions;},10,2);
        add_action('rest_api_init',function(){register_rest_route('vmm-multilingual/v1','/content/(?P<id>\d+)',[
            'methods'=>'PUT','permission_callback'=>static fn($r)=>in_array(get_post_type((int)$r['id']),Content::types(),true)&&current_user_can('edit_post',(int)$r['id']),
            'callback'=>static function($r){try {if(strlen($r->get_body())>5*1024*1024)throw new \InvalidArgumentException('Inhalt zu groß.');$data=$r->get_json_params();if(!is_array($data))throw new \InvalidArgumentException('Ungültige Eingabe.');return Content::save((int)$r['id'],$data);}catch(\Throwable $e){return new \WP_Error('vmm_content',$e->getMessage(),['status'=>409]);}}
        ]);});
        add_action('admin_enqueue_scripts',function(){if(($_GET['page']??'')!=='vmm-content'||empty($_GET['object_id']))return;
            $id=absint($_GET['object_id']);if(!current_user_can('edit_post',$id))return;
            wp_enqueue_media();wp_enqueue_editor();
            $root=dirname(__DIR__,2).'/vmm-multilingual.php';
            wp_enqueue_style('vmm-content',plugins_url('assets/content.css',$root),['wp-components','wp-block-library','wp-block-editor'],'0.11.0');
            wp_enqueue_script('vmm-content',plugins_url('assets/content.js',$root),['wp-api-fetch','wp-element','wp-blocks','wp-block-editor','wp-block-library','wp-components','wp-format-library','wp-editor'],(string)filemtime(dirname(__DIR__,2).'/assets/content.js'),true);
            $locale=sanitize_text_field($_GET['locale']??'');if(!isset(Languages::all()[$locale])||$locale===Languages::source())$locale=array_values(array_diff(array_keys(Languages::all()),[Languages::source()]))[0]??Languages::source();
            wp_add_inline_script('vmm-content','window.vmmContent='.wp_json_encode(['document'=>Content::document($id,$locale),'languages'=>Languages::names(),'nonce'=>wp_create_nonce('wp_rest'),'endpoint'=>'/vmm-multilingual/v1/content/'.$id,'base'=>admin_url('admin.php?page=vmm-content&object_id='.$id)],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';','before');
        });
    }
    public function box(\WP_Post $post): void {
        echo '<p><a class="button" href="'.esc_url(add_query_arg('vmm_visual','1',get_permalink($post->ID))).'">Visuell im Frontend übersetzen</a></p>';
        echo '<p>Ausgangssprache: <strong>'.esc_html(Languages::names()[Languages::source()]).'</strong>. Erst Änderungen im WordPress-Editor speichern, anschließend die Übersetzung öffnen.</p>';
        foreach(Languages::names() as $locale=>$name)if($locale!==Languages::source())echo '<p><a class="button" href="'.esc_url(admin_url('admin.php?page=vmm-content&object_id='.$post->ID.'&locale='.$locale)).'">'.esc_html($name).' übersetzen</a></p>';
    }
    public function render(): void {
        $id=absint($_GET['object_id']??0);echo '<div class="wrap"><h1>Inhalte übersetzen</h1>';
        if(!$id){echo '<p>Ein Inhalt, mehrere Sprachversionen. Die Ausgangssprache bleibt im gewohnten Block- oder Classic Editor bearbeitbar.</p><table class="widefat striped"><thead><tr><th>Inhalt</th><th>Typ</th><th>Übersetzungen</th></tr></thead><tbody>';
            foreach(get_posts(['post_type'=>Content::types(),'post_status'=>['publish','draft','private','pending','future'],'numberposts'=>100,'orderby'=>'modified']) as $post){if(!current_user_can('edit_post',$post->ID))continue;echo '<tr><td><a href="'.esc_url(admin_url('admin.php?page=vmm-content&object_id='.$post->ID)).'">'.esc_html($post->post_title?:'(Ohne Titel)').'</a></td><td>'.esc_html(get_post_type_object($post->post_type)->labels->singular_name).'</td><td>';
                foreach(Languages::names() as $locale=>$name)if($locale!==Languages::source()){$doc=Content::document($post->ID,$locale);echo esc_html($name.': '.self::status($doc['status'])).' · ';}echo '</td></tr>';}
            echo '</tbody></table><p>Zuletzt geänderte 100 Inhalte. Weitere Inhalte können direkt über „Übersetzen“ in der WordPress-Beitragsliste geöffnet werden.</p></div>';return;}
        if(!in_array(get_post_type($id),Content::types(),true)||!current_user_can('edit_post',$id))wp_die('Keine Berechtigung.');
        echo '<p><a href="'.esc_url(get_edit_post_link($id,'raw')).'">Ausgangssprache im WordPress-Editor bearbeiten</a> · <a href="'.esc_url(admin_url('admin.php?page=vmm-content')).'">Alle Inhalte</a></p><div id="vmm-content-root"></div></div>';
    }
    private static function status(string $status): string {return ['inherited'=>'Übernommen','draft'=>'In Bearbeitung','translated'=>'Übersetzt','outdated'=>'Ausgangssprache geändert'][$status]??$status;}
}
