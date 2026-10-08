<?php
declare(strict_types=1);
namespace VMM\Multilingual\Updates;

/** Public GitHub release assets are the update service; no access token is shipped. */
final class GitHubUpdater
{
    public const REPOSITORY='https://github.com/TentacleGuy/VMM-Multilingual';
    public const ENDPOINT=self::REPOSITORY.'/releases/latest/download/update.json';
    private const CACHE='vmm_release_manifest_v1';
    public function __construct(private readonly string $file) {}
    public function register(): void {
        add_filter('update_plugins_github.com',[$this,'update'],10,3);
        add_filter('plugins_api',[$this,'information'],10,3);
        add_action('upgrader_process_complete',static function($upgrader,$options){
            if(($options['type']??'')==='plugin')delete_site_transient(self::CACHE);
        },10,2);
    }
    public function update(mixed $update,array $pluginData,string $pluginFile): mixed {
        if($pluginFile!==plugin_basename($this->file))return $update;
        $release=$this->release();if(!$release)return false;
        return ['id'=>self::REPOSITORY,'slug'=>'vmm-multilingual','version'=>$release['version'],
            'url'=>self::REPOSITORY.'/releases/tag/v'.$release['version'],'package'=>$release['download_url'],
            'requires'=>$release['requires'],'requires_php'=>$release['requires_php'],'tested'=>$release['tested']];
    }
    public function information(mixed $result,string $action,mixed $args): mixed {
        if($action!=='plugin_information'||($args->slug??'')!=='vmm-multilingual')return $result;
        $release=$this->release();if(!$release)return $result;
        return (object)['name'=>'VMM Multilingual','slug'=>'vmm-multilingual','version'=>$release['version'],
            'author'=>'TentacleGuy','homepage'=>self::REPOSITORY,'requires'=>$release['requires'],
            'requires_php'=>$release['requires_php'],'tested'=>$release['tested'],
            'download_link'=>$release['download_url'],'last_updated'=>$release['last_updated']??'',
            'sections'=>['description'=>'Mehrsprachige Inhalte für WordPress und YOOtheme Pro.',
                'changelog'=>wp_kses_post($release['changelog']??'')]];
    }
    public function release(): ?array {
        // WordPress's explicit "Check again" also refreshes the remote manifest.
        $force=is_admin()&&current_user_can('update_plugins')&&isset($_GET['force-check']);
        $cached=$force?false:get_site_transient(self::CACHE);
        if($cached!==false)return is_array($cached)?$cached:null;
        $response=wp_safe_remote_get(self::ENDPOINT,['timeout'=>10,'redirection'=>5,'limit_response_size'=>65536,
            'headers'=>['Accept'=>'application/json','User-Agent'=>'VMM-Multilingual-WordPress']]);
        if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==200){set_site_transient(self::CACHE,'unavailable',15*MINUTE_IN_SECONDS);return null;}
        $data=json_decode(wp_remote_retrieve_body($response),true);$release=self::validate($data);
        set_site_transient(self::CACHE,$release??'unavailable',$release?6*HOUR_IN_SECONDS:15*MINUTE_IN_SECONDS);
        return $release;
    }
    public static function validate(mixed $data): ?array {
        if(!is_array($data)||!is_string($data['version']??null)||!preg_match('/^\d+\.\d+\.\d+$/D',$data['version']))return null;
        foreach(['requires','requires_php','tested'] as $key)if(!is_string($data[$key]??null)||!preg_match('/^\d+\.\d+(?:\.\d+)?$/D',$data[$key]))return null;
        $expected=self::REPOSITORY.'/releases/download/v'.$data['version'].'/vmm-multilingual-'.$data['version'].'.zip';
        if(($data['download_url']??'')!==$expected)return null;
        if(!is_string($data['changelog']??'')||strlen($data['changelog']??'')>30000)return null;
        return $data;
    }
}
