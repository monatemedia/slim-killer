<?php

declare(strict_types=1);

namespace SlimKiller\Support;

/**
 * Reads PHP ini size values such as "512M", "1G" or "-1" (unlimited).
 */
final class Bytes
{
    public const int UNLIMITED = -1;

    public static function fromIni(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return self::UNLIMITED;
        }

        $number = (int) $value;
        $unit = strtolower(substr($value, -1));

        return match ($unit) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    public static function human(int $bytes): string
    {
        if ($bytes === self::UNLIMITED) {
            return 'unlimited';
        }

        return match (true) {
            $bytes >= 1024 ** 3 => round($bytes / 1024 ** 3, 1) . ' GB',
            $bytes >= 1024 ** 2 => round($bytes / 1024 ** 2) . ' MB',
            $bytes >= 1024 => round($bytes / 1024) . ' KB',
            default => $bytes . ' bytes',
        };
    }
}
