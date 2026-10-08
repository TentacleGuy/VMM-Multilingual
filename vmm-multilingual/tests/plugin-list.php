<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Admin/PluginList.php';
use VMM\Multilingual\Admin\PluginList as L;
$count=0;$ok=function($v)use(&$count){if(!$v)throw new RuntimeException('Check '.($count+1));$count++;};
$row=fn($key,$type='text',$source='Text',$extra=[])=>L::decorate(['key'=>$key,'type'=>$type,'source'=>$source,'label'=>'Feld']+$extra);
$ok($row('layout:consent/label')['kind']==='labels');
$ok($row('layout:consent/appearance/help')['kind']==='help');
$ok($row('layout:calendar/content')['kind']==='content');
$ok($row('templates:request_body','html')['kind']==='templates');
$ok($row('pdf:logo','image')['kind']==='resources');
$ok($row('hash')['kind']==='messages');
$ok($row('custom','text','Text',['kind'=>'labels','group'=>'Element'])['group']==='Element');
$f=['area'=>'','kind'=>'','origin'=>'','empty'=>'0','search'=>''];$empty=$row('layout:consent/appearance/help','text','');
$ok(!L::matches($empty,$f));$ok(L::matches($empty,$f,['value'=>'English help']));$f['empty']='1';$ok(L::matches($empty,$f));
$f['origin']='file';$both=$row('hash','text','Text',['origins'=>['file','runtime']]);$ok(L::matches($both,$f));$f['origin']='runtime';$ok(L::matches($both,$f));$f['origin']='integration';$ok(!L::matches($both,$f));
$f['origin']='';$f['kind']='help';$ok(!L::matches($both,$f));$ok(L::matches($both,$f,null,true));
echo "PASS $count plugin-list checks\n";
