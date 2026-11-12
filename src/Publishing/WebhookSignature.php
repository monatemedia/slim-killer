<?php

declare(strict_types=1);

namespace SlimKiller\Publishing;

/**
 * "A build is ready", signed by the site's workflow with the secret both sides share:
 * an HMAC-SHA256 over the time and the body, so a call cannot be forged, and an old
 * one cannot be sent again later.
 *
 *   X-Slim-Killer-Timestamp: 1762819200
 *   X-Slim-Killer-Signature: sha256=<hex of hmac(timestamp + "." + body)>
 */
final class WebhookSignature
{
    public const string TIMESTAMP_HEADER = 'X-Slim-Killer-Timestamp';

    public const string SIGNATURE_HEADER = 'X-Slim-Killer-Signature';

    /** How far the call's time may be from the host's clock. */
    public const int WINDOW_SECONDS = 300;

    public static function sign(string $secret, int $timestamp, string $body): string
    {
        return 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }

    /** Why the call is refused, or null when it is genuine. */
    public static function problem(string $secret, string $timestamp, string $signature, string $body, int $now): ?string
    {
        if (preg_match('/^\d{9,12}$/', $timestamp) !== 1) {
            return 'The call has no timestamp.';
        }
        if (abs($now - (int) $timestamp) > self::WINDOW_SECONDS) {
            return 'The call is too old, or the clocks disagree by more than five minutes.';
        }
        if (! hash_equals(self::sign($secret, (int) $timestamp, $body), $signature)) {
            return 'The signature does not match.';
        }

        return null;
    }
}
