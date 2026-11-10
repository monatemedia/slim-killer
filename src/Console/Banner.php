<?php

declare(strict_types=1);

namespace SlimKiller\Console;

/**
 * hammer's logo, shown above the list of commands. Carried over from the first
 * Slim Killer.
 */
final class Banner
{
    /** @return list<string> */
    public static function lines(): array
    {
        return [
            '  _   _                                      ',
            ' | | | | __ _ _ __ ___  _ __ ___   ___ _ __ ',
            " | |_| |/ _` | '_ ` _ \\| '_ ` _ \\ / _ \\ '__|",
            ' |  _  | (_| | | | | | | | | | | |  __/ |   ',
            ' |_| |_|\\__,_|_| |_| |_|_| |_| |_|\\___|_|   ',
            '        SLIM-KILLER CLI RUNTIME KERNEL     ',
        ];
    }
}
