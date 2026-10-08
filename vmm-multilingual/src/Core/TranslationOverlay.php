<?php

declare(strict_types=1);

namespace VMM\Multilingual\Core;

use InvalidArgumentException;

/** Pure translation engine. Persistence and permissions belong to the adapter. */
final class TranslationOverlay
{
    public function __construct(private readonly array $fields)
    {
    }

    public static function sourceHash(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Returns a detached tree; never mutates the authoritative master. */
    public function apply(array $master, array $records): array
    {
        $this->index($master);
        return $this->walk($master, function (array $node) use ($records): array {
            foreach ($this->fields[$node['type']] ?? [] as $field) {
                if ($this->isDynamic($node, $field)) {
                    continue;
                }
                $record = $records[$node['vmm_id']][$field] ?? null;
                if ($record === null || ($record['mode'] ?? '') === 'inherit') {
                    continue;
                }
                if (!in_array($record['mode'] ?? '', ['translate', 'replace'], true) || !array_key_exists('value', $record)) {
                    throw new InvalidArgumentException('Invalid override.');
                }
                if (!is_string($record['value'])) {
                    throw new InvalidArgumentException('Phase 1 only supports string content.');
                }
                $node['props'][$field] = $record['value'];
            }
            return $node;
        });
    }

    /**
     * Split a translated editor submission into a global master and delta records.
     * Caller must validate permissions, sanitize fields and atomically check revisions.
     * Node identities preserve translations across moves, insertions and copies.
     */
    public function split(array $master, array $base, array $edited, array $records): array
    {
        $masterIndex = $this->index($master);
        $baseIndex = $this->index($base);
        $this->index($edited);
        if ($this->structure($master) !== $this->structure($base)) {
            throw new InvalidArgumentException('Editor base does not match the master.');
        }
        $nextMaster = $this->walk($edited, function (array $node) use ($masterIndex, $baseIndex, &$records): array {
            $id = $node['vmm_id'];
            $origin = $node['vmm_origin_id'] ?? $id;
            if (!is_string($origin) || ($origin !== $id && (!isset($masterIndex[$origin]) || isset($masterIndex[$id])))) {
                throw new InvalidArgumentException('Invalid copy origin.');
            }
            $original = $masterIndex[$origin] ?? $node;
            $previous = $baseIndex[$origin] ?? $node;
            if ($original['type'] !== $node['type']) {
                throw new InvalidArgumentException('An existing identity cannot change element type.');
            }
            if ($origin !== $id && isset($records[$origin])) {
                $records[$id] = $records[$origin];
            }
            if (($node['source'] ?? null) !== ($original['source'] ?? null)) {
                throw new InvalidArgumentException('Dynamic bindings require the master editing mode.');
            }
            foreach ($this->fields[$node['type']] ?? [] as $field) {
                if ($this->isDynamic($original, $field)) {
                    if ($this->value($node, $field) !== $this->value($original, $field)) {
                        throw new InvalidArgumentException('Cannot overwrite dynamic content.');
                    }
                    continue;
                }
                $value = $this->value($node, $field);
                if ($value !== $this->value($previous, $field)) {
                    if (!is_string($value)) {
                        throw new InvalidArgumentException('Phase 1 only supports string content.');
                    }
                    $records[$id][$field] = [
                        'mode' => 'translate',
                        'value' => $value,
                        'source_hash' => self::sourceHash($this->value($original, $field)),
                    ];
                }
                if (array_key_exists($field, $original['props'] ?? [])) {
                    $node['props'][$field] = $original['props'][$field];
                } else {
                    unset($node['props'][$field]);
                }
            }
            return $node;
        });
        return ['master' => $nextMaster, 'records' => $records];
    }

    public function status(mixed $master, ?array $record): string
    {
        if ($record === null) {
            return 'missing';
        }
        if (($record['mode'] ?? '') === 'inherit') {
            return 'inherited';
        }
        return hash_equals(self::sourceHash($master), (string) ($record['source_hash'] ?? '')) ? 'translated' : 'outdated';
    }

    private function value(array $node, string $field): mixed
    {
        return $node['props'][$field] ?? '';
    }

    private function isDynamic(array $node, string $field): bool
    {
        // Verified source.props binding format in YOOtheme 5.0.46.
        return array_key_exists($field, $node['source']['props'] ?? []);
    }

    private function structure(array $node): array
    {
        return [$node['vmm_id'], $node['type'], array_map($this->structure(...), $node['children'] ?? [])];
    }

    private function index(array $tree): array
    {
        $index = [];
        $this->walk($tree, function (array $node) use (&$index): array {
            $id = $node['vmm_id'] ?? '';
            if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/D', $id) || isset($index[$id])) {
                throw new InvalidArgumentException('Missing, invalid or duplicate persistent node identity.');
            }
            if (!isset($node['type']) || !is_string($node['type'])) {
                throw new InvalidArgumentException('Invalid node type.');
            }
            $index[$id] = $node;
            return $node;
        });
        return $index;
    }

    private function walk(array $node, callable $visitor, int $depth = 0): array
    {
        if ($depth > 100) {
            throw new InvalidArgumentException('Builder nesting limit exceeded.');
        }
        $node = $visitor($node);
        if (isset($node['children'])) {
            foreach ($node['children'] as &$child) {
                $child = $this->walk($child, $visitor, $depth + 1);
            }
            unset($child);
        }
        return $node;
    }
}
