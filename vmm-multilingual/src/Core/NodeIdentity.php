<?php

declare(strict_types=1);

namespace VMM\Multilingual\Core;

use InvalidArgumentException;

/** Assign only at an explicit master save/migration, never during frontend rendering. */
final class NodeIdentity
{
    public function initialize(array $tree): array
    {
        $seen = [];
        return $this->walk($tree, $seen);
    }

    private function walk(array $node, array &$seen, int $depth = 0): array
    {
        if ($depth > 100) {
            throw new InvalidArgumentException('Builder nesting limit exceeded.');
        }
        $id = $node['vmm_id'] ?? null;
        if ($id === null) {
            do {
                $id = bin2hex(random_bytes(16));
            } while (isset($seen[$id]));
            $node['vmm_id'] = $id;
        }
        // Do not guess which node is the original after a browser copy operation.
        if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/D', $id) || isset($seen[$id])) {
            throw new InvalidArgumentException('Invalid or duplicate identity; resolve at the explicit copy operation.');
        }
        $seen[$id] = true;
        if (isset($node['children'])) {
            foreach ($node['children'] as &$child) {
                $child = $this->walk($child, $seen, $depth + 1);
            }
            unset($child);
        }
        return $node;
    }
}
