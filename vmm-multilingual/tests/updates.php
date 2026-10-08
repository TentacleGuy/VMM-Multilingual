<?php
declare(strict_types=1);
require __DIR__.'/../src/Updates/GitHubUpdater.php';
use VMM\Multilingual\Updates\GitHubUpdater as U;
$checks=0;$ok=function($condition)use(&$checks){if(!$condition)throw new RuntimeException('Failed check '.($checks+1));$checks++;};
$data=['version'=>'0.15.0','requires'=>'6.1','requires_php'=>'8.1','tested'=>'7.1.3','download_url'=>U::REPOSITORY.'/releases/download/v0.15.0/vmm-multilingual-0.15.0.zip','changelog'=>'<p>Update</p>'];
$ok(U::validate($data)===$data);
foreach(['https://evil.example/plugin.zip',U::REPOSITORY.'/archive/refs/tags/v0.15.0.zip',U::REPOSITORY.'/releases/download/v0.14.0/vmm-multilingual-0.14.0.zip'] as $url){$bad=$data;$bad['download_url']=$url;$ok(U::validate($bad)===null);}
foreach(['0.15.0-beta','garbage','0.15'] as $version){$bad=$data;$bad['version']=$version;$ok(U::validate($bad)===null);}
$ok(U::validate(null)===null);$bad=$data;unset($bad['requires_php']);$ok(U::validate($bad)===null);
$u=new U(__FILE__);function plugin_basename($file){return 'vmm-multilingual/vmm-multilingual.php';}
$ok($u->update('other',[],'other/plugin.php')==='other');$ok($u->information('other','query_plugins',(object)['slug'=>'vmm-multilingual'])==='other');$ok($u->information('other','plugin_information',(object)['slug'=>'other'])==='other');
echo "PASS $checks update checks\n";
