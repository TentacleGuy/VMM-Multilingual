<?php
declare(strict_types=1);
namespace VMM\Multilingual\Site;

final class Settings
{
    public static function get(): array
    {
        $settings = array_merge(['url_mode'=>'query','en_host'=>'','display'=>'names','placement'=>'none','sidebar'=>'','hide_current'=>false,'floating_position'=>'bottom-right'], (array) get_option('vmm_site_settings', []));
        return array_merge($settings,self::appearance($settings));
    }
    public static function appearance(array $input): array
    {
        $legacy = $input['display'] ?? 'names';
        $components = $input['components'] ?? match($legacy) {'codes'=>['code'],'flags'=>['flag'],'flags_names'=>['flag','name'],default=>['name']};
        $layout = $input['layout'] ?? ($legacy === 'dropdown' ? 'dropdown' : 'horizontal');
        if (!is_array($components) || !$components || array_diff($components,['name','flag','code']) || !in_array($layout,['dropdown','horizontal','vertical'],true)) throw new \InvalidArgumentException('Bitte mindestens einen Bestandteil und eine gültige Anordnung wählen.');
        return ['components'=>array_values(array_unique($components)),'layout'=>$layout];
    }

    public static function sanitize(array $input): array
    {
        $mode = $input['url_mode'] ?? 'query';
        if (!in_array($mode, ['query','directory','subdomain'], true)) throw new \InvalidArgumentException('Ungültiges URL-Format.');
        $host = strtolower(trim((string) ($input['en_host'] ?? ''))) ?: Languages::host('en_GB');
        if ($host !== '' && !preg_match('/^(?=.{1,253}$)[a-z0-9]+(?:[.-][a-z0-9]+)*(?::[0-9]{1,5})?$/D', $host)) throw new \InvalidArgumentException('Bitte nur einen Hostnamen mit optionalem Port eingeben.');
        $base = wp_parse_url(home_url());
        $baseHost = ($base['host'] ?? '').(isset($base['port']) ? ':'.$base['port'] : '');
        if ($mode === 'subdomain' && Languages::source() !== 'en_GB' && isset(Languages::all()['en_GB']) && ($host === '' || $host === strtolower($baseHost))) throw new \InvalidArgumentException('Für English ist ein eigener Hostname erforderlich.');
        if ($mode === 'subdomain') foreach (Languages::all() as $locale=>$language) {
            if ($locale !== Languages::source() && $locale !== 'en_GB' && Languages::host($locale) === '') throw new \InvalidArgumentException('Bitte unter VMM → Sprachen einen Hostnamen für '.$language['name'].' eintragen.');
        }
        $display = $input['display'] ?? 'names';
        $appearance = self::appearance(isset($input['components_present']) ? array_merge($input,['components'=>$input['components'] ?? []]) : $input);
        $floating = $input['floating_position'] ?? 'bottom-right';
        if (!in_array($floating,['top-left','top-right','bottom-left','bottom-right','middle-left','middle-right'],true)) throw new \InvalidArgumentException('Ungültige schwebende Position.');
        $placement = $input['placement'] ?? 'none';
        if (!in_array($display, ['names','codes','dropdown','flags','flags_names'], true) || !in_array($placement, ['none','floating','sidebar'], true)) throw new \InvalidArgumentException('Ungültige Switcher-Einstellung.');
        global $wp_registered_sidebars;
        $sidebar = sanitize_key($input['sidebar'] ?? '');
        if ($placement === 'sidebar' && !isset($wp_registered_sidebars[$sidebar])) throw new \InvalidArgumentException('Bitte eine vorhandene Widgetposition wählen.');
        return array_merge(['url_mode'=>$mode,'en_host'=>$host,'display'=>$display,'placement'=>$placement,'sidebar'=>$sidebar,'hide_current'=>!empty($input['hide_current']),'floating_position'=>$floating],$appearance);
    }
}
