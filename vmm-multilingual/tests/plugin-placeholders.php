<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Site/PluginTranslations.php';
use VMM\Multilingual\Site\PluginTranslations as S;
$cases=[['Behinderung ab 50 %. Bitte lesen.',[]],['Disability of 50% or more.',[]],['Hallo {name}: %1$s / %02d',['%02d','%1$s','{name}']],['{arrival} {arrival}',['{arrival}','{arrival}']]];
foreach($cases as [$text,$expected])if(S::placeholders($text)!==$expected)throw new RuntimeException($text);
echo "PASS: 4 placeholder checks\n";
