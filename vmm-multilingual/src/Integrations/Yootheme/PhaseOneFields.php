<?php

declare(strict_types=1);

namespace VMM\Multilingual\Integrations\Yootheme;

/** Deliberately restricted Phase 1 policy, verified against 5.0.46 element.php files. */
final class PhaseOneFields
{
    public const VERSION = '5.0.46';

    public static function all(): array
    {
        return [
            'headline' => ['content', 'image_alt'],
            'text' => ['content'],
            'button_item' => ['content'],
            'image' => ['image_alt'],
        ];
    }
}
