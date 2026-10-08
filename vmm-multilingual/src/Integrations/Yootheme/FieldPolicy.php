<?php
declare(strict_types=1);

namespace VMM\Multilingual\Integrations\Yootheme;

/** Content-tab text fields from the actual, resolved element schema. */
final class FieldPolicy
{
    private ?array $map = null;
    private ?array $resourceMap = null;

    public function all(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }
        $map = [];
        foreach (\YOOtheme\app(\YOOtheme\Builder::class)->getTypes() as $name => $type) {
            $schema = $type->jsonSerialize();
            $content = [];
            $collect = function (array $group) use (&$collect, &$content): void {
                foreach ($group as $entry) {
                    if (is_string($entry)) {
                        $content[] = $entry;
                    } elseif (is_array($entry)) {
                        $collect($entry['fields'] ?? []);
                    }
                }
            };
            foreach ($schema['fieldset']['default']['fields'] ?? [] as $tab) {
                if (is_array($tab) && ($tab['title'] ?? '') === 'Content') {
                    $collect($tab['fields'] ?? []);
                }
            }
            foreach ($schema['fields'] ?? [] as $field=>$definition) {
                if (in_array($definition['type'] ?? '', ['link','image','select-widget'], true) || (preg_match('/(?:aria|alt|title|placeholder|caption)/i',$field)&&in_array($definition['type']??'text',['text','textarea'],true))) $content[] = $field;
            }
            foreach (array_unique($content) as $field) {
                $definition = $schema['fields'][$field] ?? null;
                if (!is_array($definition)) {
                    continue;
                }
                $kind = $definition['type'] ?? 'text';
                $allowed = in_array($kind, ['text', 'textarea', 'editor', 'link', 'image', 'select-widget'], true)
                    && !in_array($field, ['id', 'class', 'attributes', 'tags'], true);
                if (apply_filters('vmm_multilingual_translatable_field', $allowed, $field, $name, $definition)) {
                    $map[$name][] = $field;
                }
            }
        }
        // Preserve Phase-1 metadata support when alt fields are outside the Content tab.
        foreach (PhaseOneFields::all() as $name => $fields) {
            $map[$name] = array_values(array_unique(array_merge($map[$name] ?? [], $fields)));
        }
        return $this->map = $map;
    }

    /** Resource fields offer explicit inheritance switches in the editor. */
    public function resources(): array
    {
        if ($this->resourceMap !== null) return $this->resourceMap;
        $result = [];
        foreach (\YOOtheme\app(\YOOtheme\Builder::class)->getTypes() as $name => $type) {
            foreach ($type->jsonSerialize()['fields'] ?? [] as $field => $definition) {
                $kind = $definition['type'] ?? '';
                if (in_array($kind, ['image','link','select-widget'], true) && in_array($field, $this->all()[$name] ?? [], true)) $result[$name][$field] = $kind;
            }
        }
        return $this->resourceMap = $result;
    }

    public function sanitize(array $node, int $depth = 0): array
    {
        if ($depth > 90 || !isset($node['type']) || !is_string($node['type']) || !\YOOtheme\app(\YOOtheme\Builder::class)->getType($node['type'])) {
            throw new \InvalidArgumentException('Ungültiger oder zu tief verschachtelter Builder-Baum.');
        }
        foreach ($this->all()[$node['type']] ?? [] as $field) {
            if (array_key_exists($field, $node['props'] ?? [])) {
                if (!is_string($node['props'][$field])) {
                    throw new \InvalidArgumentException('Inhaltsfelder müssen Text enthalten.');
                }
                $node['props'][$field] = ($field === 'link' || str_ends_with($field, '_link'))
                    ? esc_url_raw($node['props'][$field], ['http','https','mailto','tel'])
                    : (str_contains($field, 'alt') || str_contains($field, 'aria')
                    ? sanitize_text_field($node['props'][$field])
                    : (current_user_can('unfiltered_html') ? $node['props'][$field] : wp_kses_post($node['props'][$field])));
            }
        }
        foreach ($node['children'] ?? [] as $index => $child) {
            if (!is_array($child)) {
                throw new \InvalidArgumentException('Ungültiges Builder-Element.');
            }
            $node['children'][$index] = $this->sanitize($child, $depth + 1);
        }
        return $node;
    }
}
