<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * A QR code as inline SVG, drawn on the server: the two-factor secret never goes to
 * a third-party QR service, and the Content Security Policy needs no exception.
 */
final class QrCode
{
    public static function svg(string $text, int $size = 220): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, 2), new SvgImageBackEnd())))->writeString($text);

        return trim((string) preg_replace('/^<\?xml.*?\?>/s', '', $svg));
    }
}
