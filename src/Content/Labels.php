<?php

declare(strict_types=1);

namespace SlimKiller\Content;

/**
 * Words for people, made from names in code, for when a schema gives no title:
 * "heroImageAlt" becomes "Hero image alt", "tour-categories" becomes "Tour categories".
 */
final class Labels
{
    public static function fromName(string $name): string
    {
        $words = (string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])|[-_]+/', ' ', $name);

        return ucfirst(strtolower(trim($words)));
    }
}
