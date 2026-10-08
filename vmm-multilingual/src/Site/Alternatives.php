<?php
declare(strict_types=1);
namespace VMM\Multilingual\Site;

final class Alternatives
{
    private static int $builderWidgetDepth = 0;
    public static function withBuilderWidget(callable $render): mixed
    {
        self::$builderWidgetDepth++;
        try { return $render(); } finally { self::$builderWidgetDepth--; }
    }
    private bool $resolving = false;
    public static function widgetLanguage(string $id): string
    {
        $g = self::group('widgets',$id);
        if ($g) foreach ($g['members'] as $language=>$member) if ((string)$member === $id) return $language;
        return self::get()['visibility'][$id] ?? 'both';
    }
    public static function get(): array { return array_merge(['menus'=>[], 'widgets'=>[], 'visibility'=>[], 'groups'=>['menus'=>[], 'widgets'=>[]]], (array)get_option('vmm_alternatives', [])); }
    public static function groups(string $type): array
    {
        $settings = self::get(); $groups = $settings['groups'][$type] ?? [];
        $assigned = []; foreach ($groups as $g) foreach ($g['members'] as $id) $assigned[(string)$id] = true;
        foreach ($settings[$type] as $source=>$target) if (!isset($assigned[(string)$source],$assigned[(string)$target])) {
            if (isset($assigned[(string)$source]) || isset($assigned[(string)$target])) continue;
            $groups[] = ['source'=>'de_DE','members'=>['de_DE'=>(string)$source,'en_GB'=>(string)$target]];
            $assigned[(string)$source] = $assigned[(string)$target] = true;
        }
        return $groups;
    }
    public static function group(string $type, string $id): ?array
    {
        foreach (self::groups($type) as $g) if (in_array($id, array_map('strval',$g['members']),true)) return $g;
        return null;
    }
    public static function assign(string $type, string $id, string $language, string $target): void
    {
        if (!in_array($type,['menus','widgets'],true) || !in_array($language,['both','de_DE','en_GB'],true)) throw new \InvalidArgumentException('Ungültige Sprache.');
        $settings = self::get(); $groups = self::groups($type); $old = self::group($type,$id);
        if ($language === 'both') $target = '';
        if ($target === $id) throw new \InvalidArgumentException('Eine Variante darf nicht auf sich selbst zeigen.');
        $next = [];
        foreach ($groups as $g) {
            $g['members'] = array_filter($g['members'],static fn($member) => (string)$member !== $id && ($target === '' || (string)$member !== $target));
            if ($g['members']) { if (!isset($g['members'][$g['source']])) $g['source'] = array_key_first($g['members']); $next[] = $g; }
        }
        if ($language !== 'both') {
            $members = [$language=>$id];
            if ($target !== '') $members[$language === 'de_DE' ? 'en_GB' : 'de_DE'] = $target;
            $source = $old && isset($members[$old['source']]) ? $old['source'] : $language;
            $next[] = ['source'=>$source,'members'=>$members];
        }
        $settings['groups'][$type] = array_values($next);
        $settings[$type] = []; // Legacy assignments have now been migrated.
        if ($type === 'widgets') { unset($settings['visibility'][$id]); if ($target !== '') unset($settings['visibility'][$target]); }
        update_option('vmm_alternatives',$settings,false);
    }
    public static function resolve(string $type, string $id, string $locale): string
    {
        $g = self::group($type,$id);
        return $g ? (string)($g['members'][$locale] ?? $g['members'][$g['source']]) : $id;
    }
    public function register(LanguageUrls $urls): void
    {
        add_filter('sidebars_widgets', static function (array $sidebars) use ($urls): array {
            // An explicitly selected builder widget controls its own language via its overlay.
            if (is_admin() || self::$builderWidgetDepth > 0) return $sidebars;
            foreach ($sidebars as $position=>&$ids) {
                if (!is_array($ids) || $position === 'wp_inactive_widgets') continue;
                $ids = array_values(array_filter($ids,static fn($id) => in_array(self::widgetLanguage($id),['both',$urls->locale()],true)));
            }
            unset($ids); return $sidebars;
        },30);
    }
}

