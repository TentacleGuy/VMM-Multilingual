<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Core/TranslationOverlay.php';
require dirname(__DIR__) . '/src/Core/NodeIdentity.php';
require dirname(__DIR__) . '/src/Integrations/Yootheme/PhaseOneFields.php';

use VMM\Multilingual\Core\TranslationOverlay;
use VMM\Multilingual\Core\NodeIdentity;
use VMM\Multilingual\Integrations\Yootheme\PhaseOneFields;

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
}
function rejects(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (InvalidArgumentException) {
        check(true, $message);
        return;
    }
    check(false, $message);
}
function node(string $type, int $id, array $props = [], array $children = []): array
{
    return ['type' => $type, 'vmm_id' => str_pad(dechex($id), 32, '0', STR_PAD_LEFT), 'props' => $props, 'children' => $children];
}

$engine = new TranslationOverlay(PhaseOneFields::all());
$identity = new NodeIdentity();
$raw = ['type' => 'layout', 'children' => [['type' => 'text', 'props' => ['content' => 'Master']]]];
$identified = $identity->initialize($raw);
check(!isset($raw['vmm_id']), 'Identity initialization does not mutate source');
check($identity->initialize($identified) === $identified, 'Identity initialization idempotent');
check($identified['vmm_id'] !== $identified['children'][0]['vmm_id'], 'Distinct node identities');
$master = node('layout', 1, [], [node('headline', 2, ['content' => 'Ferienwohnung Maya', 'title_style' => 'h1']), node('text', 3, ['content' => 'Willkommen']), node('button', 4, [], [node('button_item', 5, ['content' => 'Jetzt anfragen'])]), node('image', 6, ['image' => 'hero.jpg', 'image_alt' => 'Wohnung'])]);
$id = $master['children'][0]['vmm_id'];
$allRecords = [];
foreach ([$master['children'][1], $master['children'][2]['children'][0], $master['children'][3]] as $n) {
    $field = $n['type'] === 'image' ? 'image_alt' : 'content';
    $allRecords[$n['vmm_id']][$field] = ['mode' => 'translate', 'value' => 'English'];
}
$all = $engine->apply($master, $allRecords);
check($all['children'][1]['props']['content'] === 'English', 'Text overlay');
check($all['children'][2]['children'][0]['props']['content'] === 'English', 'Button item overlay');
check($all['children'][3]['props']['image_alt'] === 'English', 'Image Alt overlay');
check($all['children'][3]['props']['image'] === 'hero.jpg', 'Alt overlay retains resource');
$records = [$id => ['content' => ['mode' => 'translate', 'value' => 'Holiday Apartment Maya', 'source_hash' => TranslationOverlay::sourceHash('Ferienwohnung Maya')]]];
$en = $engine->apply($master, $records);
check($en['children'][0]['props']['content'] === 'Holiday Apartment Maya', 'English overlay');
check($master['children'][0]['props']['content'] === 'Ferienwohnung Maya', 'Master isolation');
check($en['children'][1]['props']['content'] === 'Willkommen', 'Missing fallback');
$edited = $en;
$edited['children'][0]['props']['content'] = 'Modern Holiday Apartment Maya';
$edited['children'][0]['props']['title_style'] = 'h2';
$split = $engine->split($master, $en, $edited, $records);
check($split['master']['children'][0]['props']['content'] === 'Ferienwohnung Maya', 'Save protects master');
check($split['master']['children'][0]['props']['title_style'] === 'h2', 'Layout edits global');
check($split['records'][$id]['content']['value'] === 'Modern Holiday Apartment Maya', 'Save translation');
check($engine->split($master, $en, $en, $records)['records'] === $records, 'Unchanged overrides preserved');
$records[$id]['content']['value'] = '';
check($engine->apply($master, $records)['children'][0]['props']['content'] === '', 'Explicit empty override');
$records[$id]['content']['mode'] = 'inherit';
check($engine->apply($master, $records)['children'][0]['props']['content'] === 'Ferienwohnung Maya', 'Inherit');
check($engine->status('x', null) === 'missing', 'Missing status');
check($engine->status('x', ['mode' => 'inherit']) === 'inherited', 'Inherited status');
check($engine->status('x', ['mode' => 'translate', 'source_hash' => TranslationOverlay::sourceHash('x')]) === 'translated', 'Translated status');
check($engine->status('y', ['mode' => 'translate', 'source_hash' => TranslationOverlay::sourceHash('x')]) === 'outdated', 'Outdated status');
$moved = $master;
$moved['children'] = array_reverse($moved['children']);
$records[$id]['content']['mode'] = 'translate';
$records[$id]['content']['value'] = 'English';
check($engine->apply($moved, $records)['children'][3]['props']['content'] === 'English', 'Identity survives reordering');
$reordered = $en;
$reordered['children'] = array_reverse($reordered['children']);
$moveSplit = $engine->split($master, $en, $reordered, $records);
check($moveSplit['master']['children'][3]['props']['content'] === 'Ferienwohnung Maya', 'Moved translated node retains master');
$copy = $en;
$copied = $copy['children'][0];
$copied['vmm_origin_id'] = $copied['vmm_id'];
$copied['vmm_id'] = bin2hex(random_bytes(16));
$copy['children'][] = $copied;
$copySplit = $engine->split($master, $en, $copy, $records);
check($copySplit['master']['children'][4]['props']['content'] === 'Ferienwohnung Maya', 'Copy restores German master');
check($copySplit['records'][$copied['vmm_id']] === $records[$id], 'Copy retains detached translation');
$removed = $en;
array_shift($removed['children']);
$removeSplit = $engine->split($master, $en, $removed, $records);
check(count($removeSplit['master']['children']) === 3, 'Removal is global');
check(isset($removeSplit['records'][$id]), 'Removal retains recoverable translation records');
$duplicate = $master;
$duplicate['children'][] = $duplicate['children'][0];
rejects(fn() => $engine->apply($duplicate, []), 'Duplicate identity rejected');
$dynamic = $master;
$dynamic['children'][0]['source'] = ['props' => ['content' => ['name' => 'post.title']]];
check($engine->apply($dynamic, $records)['children'][0]['props']['content'] === 'Ferienwohnung Maya', 'Dynamic binding protected');
$dynamicEdit = $dynamic;
$dynamicEdit['children'][0]['props']['content'] = 'Static replacement';
rejects(fn() => $engine->split($dynamic, $dynamic, $dynamicEdit, []), 'Dynamic save protected');

// Contract tests against the supplied, unmodified YOOtheme code. No WordPress boot.
$theme = dirname(__DIR__, 2) . '/Beispielseite/wp-content/themes/yootheme';
require $theme . '/vendor/autoload.php';
YOOtheme\Url::setBase('https://vmm.test/');
require $theme . '/packages/builder/src/Builder.php';
require $theme . '/packages/builder/src/Builder/ElementType.php';
require $theme . '/packages/builder/src/Builder/OptimizeTransform.php';
require $theme . '/packages/builder/src/Builder/IndexTransform.php';
require $theme . '/packages/builder-wordpress/src/PostHelper.php';
require $theme . '/packages/utils/src/Arr.php';
$builder = new YOOtheme\Builder(fn($file) => require $file, fn() => '');
foreach (['layout', 'headline', 'text', 'button', 'button_item', 'image'] as $type) {
    $builder->addType($type, "$theme/packages/builder/elements/$type/element.php");
}
foreach (PhaseOneFields::all() as $type => $fields) {
    foreach ($fields as $field) {
        check(array_key_exists($field, $builder->getType($type)->fields), "Real field $type.$field");
    }
}
$builder->addTransform('presave', new YOOtheme\Builder\OptimizeTransform());
$saved = $builder->load(json_encode($master, JSON_THROW_ON_ERROR), ['context' => 'save']);
check($saved->children[0]->vmm_id === $id, 'YOOtheme save transform retains custom identity');
$serialized = json_encode($saved, JSON_THROW_ON_ERROR);
$extracted = YOOtheme\Builder\Wordpress\PostHelper::matchContent("<h1>Master</h1>\n<!--more-->\n<!-- $serialized -->");
check($extracted === $serialized, 'Actual PostHelper serialization roundtrip');
echo "PASS: $checks checks. No live WordPress/editor tests executed.\n";
