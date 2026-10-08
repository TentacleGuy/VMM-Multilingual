<?php
declare(strict_types=1);
namespace VMM\Multilingual\Site;

/** Common catalog contract for every plugin, independent of its storage or renderer. */
final class PluginContent
{
    private static array $fields=[];
    private static array $providers=[];
    public static function provider(string $plugin,callable $catalog): void {self::$providers[$plugin][]=$catalog;}
    public static function register(string $plugin,string $id,callable $read,array $options=[]): void {
        if($id===''||strlen($id)>200||str_contains($plugin,'..'))throw new \InvalidArgumentException('Ungültiger Plugin-Inhalt.');
        $type=$options['type']??'text';$area=$options['area']??'shared';
        if(!in_array($type,['text','html','url','image'],true)||!in_array($area,['website','email','pdf','shared','system'],true))throw new \InvalidArgumentException('Ungültiger Inhaltstyp oder Bereich.');
        self::$fields[$plugin][$id]=['read'=>$read,'options'=>$options+['label'=>$id,'type'=>$type,'area'=>$area]];
    }
    public static function catalog(string $plugin): array {
        $rows=[];foreach(self::$providers[$plugin]??[] as $provider)$rows=array_replace($rows,$provider());
        foreach(self::$fields[$plugin]??[] as $id=>$field){$value=($field['read'])();if(!is_string($value))continue;$key='registered:'.$id;$o=$field['options'];$rows[$key]=PluginTranslations::row($key,$o['label'],$value,$o['type'],$o);}
        return $rows;
    }
    public static function value(string $plugin,string $id,?string $locale=null): string {
        $field=self::$fields[$plugin][$id]??null;if(!$field)throw new \InvalidArgumentException('Inhalt ist nicht registriert.');
        $source=($field['read'])();if(!is_string($source))return '';
        return PluginTranslations::value($plugin,'registered:'.$id,$source,$locale??PluginTranslations::locale());
    }
}
