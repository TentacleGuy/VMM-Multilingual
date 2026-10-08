<?php
declare(strict_types=1);
namespace VMM\Multilingual\Integrations;
use VMM\Multilingual\Site\{ContentTranslations as Content,Languages,LanguageUrls};
final class SeoAdapter
{
    private array $sitemapPosts=[];
    public function register(LanguageUrls $urls): void {
        add_filter('document_title_parts',static function($parts)use($urls){if(is_singular(Content::types()))$parts['title']=Content::effective(get_queried_object_id(),$urls->locale())['title'];return $parts;},40);
        add_filter('wpseo_title',static function($title)use($urls){if(!is_singular(Content::types()))return $title;$id=get_queried_object_id();$values=Content::effective($id,$urls->locale());return $values['seo_title']?:str_replace(get_post($id)->post_title,$values['title'],(string)$title);},50);
        foreach(['wpseo_canonical','wpseo_opengraph_url'] as $hook)add_filter($hook,static fn($value)=>is_singular(Content::types())?$urls->url(get_queried_object_id(),$urls->locale()):$value,40);
        add_filter('get_canonical_url',static fn($value,$post)=>in_array($post->post_type,Content::types(),true)?$urls->url($post->ID,$urls->locale()):$value,40,2);
        add_filter('wpseo_opengraph_locale',static fn($value)=>is_singular(Content::types())?$urls->locale():$value,40);
        foreach(['wpseo_schema_webpage','wpseo_schema_article'] as $hook)add_filter($hook,static function($data)use($urls){if(!is_singular(Content::types()))return $data;$id=get_queried_object_id();$locale=$urls->locale();$values=Content::effective($id,$locale);$url=$urls->url($id,$locale);$data['inLanguage']=str_replace('_','-',$locale);$data['url']=$url;if(isset($data['@id']))$data['@id']=$url.(str_contains($data['@id'],'#')?'#'.explode('#',$data['@id'],2)[1]:'');if(isset($data['name']))$data['name']=$values['seo_title']?:$values['title'];if(isset($data['headline']))$data['headline']=$values['title'];if($values['description']!=='')$data['description']=$values['description'];return $data;},40);
        add_filter('wpseo_sitemap_entry',function($entry,$type,$post){if($type==='post'&&$entry&&$post instanceof \WP_Post)$this->sitemapPosts[$entry['loc']]=$post->ID;return $entry;},40,3);
        add_filter('wpseo_sitemap_url',function($xml,$entry)use($urls){$id=$this->sitemapPosts[$entry['loc']]??0;if(!$id||!in_array(get_post_type($id),Content::types(),true))return $xml;$xml='';foreach(Languages::all() as $locale=>$profile){if(!Content::available($id,$locale))continue;$xml.="\t<url><loc>".esc_xml($urls->url($id,$locale)).'</loc>'.(!empty($entry['mod'])?'<lastmod>'.esc_xml(gmdate('c',strtotime($entry['mod']))).'</lastmod>':'')."</url>\n";}return $xml;},40,2);
    }
}
