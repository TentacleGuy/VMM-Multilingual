<?php
declare(strict_types=1);
namespace VMM\Multilingual\Admin;

/** Presentation metadata; it never changes translation keys or values. */
final class PluginList
{
    public const KINDS=[''=>'Alle Inhalte','labels'=>'Beschriftungen','content'=>'Inhalte & Hinweise','help'=>'Hilfetexte & Eingabehinweise','messages'=>'Meldungen','templates'=>'Vorlagen','resources'=>'Bilder & Links'];
    public const ORIGINS=[''=>'Alle Herkünfte','file'=>'Automatisch aus Dateien erkannt','runtime'=>'Beim Durchsehen erfasst','integration'=>'Über eine Anbindung bereitgestellt'];
    public static function decorate(array $row): array {
        $kind=$row['kind']??'';$path=$row['path']??$row['key'];
        if(!isset(self::KINDS[$kind])||$kind===''){
            $kind=match(true){in_array($row['type'],['image','url'],true)=>'resources',str_starts_with($row['key'],'templates:')||in_array($row['area']??'',['email','pdf'],true)=>'templates',preg_match('~/(help|placeholder)(/|$)~',$path)===1=>'help',preg_match('~/(label|options|calendar_labels)(/|$)~',$path)===1=>'labels',str_ends_with($path,'/content')||str_starts_with($row['key'],'settings:')=>'content',default=>'messages'};
        }
        $row['kind']=$kind;
        $row['group']=$row['group']??($row['context']??'');
        if($row['group']==='')$row['group']=isset($row['domain'])?'Systemtexte · '.$row['domain']:(in_array($row['area']??'',['email','pdf'],true)?'Vorlagen':'Weitere Inhalte');
        $row['origins']=$row['origins']??[$row['origin']??'integration'];return $row;
    }
    public static function filters(array $input): array {
        return ['area'=>sanitize_key($input['area']??''),'kind'=>isset(self::KINDS[$input['kind']??''])?($input['kind']??''):'','origin'=>isset(self::ORIGINS[$input['origin']??''])?($input['origin']??''):'','empty'=>empty($input['empty'])?'0':'1','search'=>sanitize_text_field(wp_unslash($input['search']??''))];
    }
    public static function matches(array $row,array $filters,?array $record=null,bool $ignoreKind=false): bool {
        if($filters['area']!==''&&($row['area']??'system')!==$filters['area'])return false;
        if(!$ignoreKind&&$filters['kind']!==''&&$row['kind']!==$filters['kind'])return false;
        if($filters['origin']!==''&&!in_array($filters['origin'],$row['origins'],true))return false;
        if($filters['empty']!=='1'&&trim($row['source'])===''&&$record===null)return false;
        return $filters['search']===''||stripos(implode(' ',[$row['source'],$row['label'],$row['path']??'',$row['key'],$row['group']]),$filters['search'])!==false;
    }
}
