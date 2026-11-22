<?php

declare(strict_types=1);

namespace SlimKiller\Visits;

/** The kind of device, from the browser's description of itself. */
enum Device: string
{
    case Phone = 'phone';
    case Tablet = 'tablet';
    case Computer = 'computer';

    public static function fromUserAgent(string $userAgent): self
    {
        if (preg_match('/iPad|Tablet|Kindle|Silk|Android(?!.*Mobile)/i', $userAgent) === 1) {
            return self::Tablet;
        }
        if (preg_match('/Mobi|iPhone|iPod|Android|Windows Phone/i', $userAgent) === 1) {
            return self::Phone;
        }

        return self::Computer;
    }
}
