<?php

declare(strict_types=1);

namespace SlimKiller\Auth;

use SlimKiller\Support\Clock;

/**
 * Time-based one-time codes (RFC 6238), the six digits an authenticator app shows:
 * HMAC-SHA1, 30-second steps, and one step of leeway either side for a slow phone.
 */
final class Totp
{
    public const int DIGITS = 6;

    public const int PERIOD = 30;

    public const int LEEWAY = 1;

    public function __construct(
        private readonly Clock $clock,
    ) {}

    /** A new random secret: 160 bits, as RFC 4226 recommends. */
    public static function newSecret(): string
    {
        return Base32::encode(random_bytes(20));
    }

    /** The address an authenticator app reads from the QR code. */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account)
            . '?' . http_build_query([
                'secret' => $secret,
                'issuer' => $issuer,
                'algorithm' => 'SHA1',
                'digits' => self::DIGITS,
                'period' => self::PERIOD,
            ], '', '&', PHP_QUERY_RFC3986);
    }

    public function codeAt(string $secret, int $timestamp): string
    {
        return $this->codeForCounter($secret, intdiv($timestamp, self::PERIOD));
    }

    /**
     * Checks a code against now, give or take one step.
     *
     * @return int|null The time step the code belongs to, or null if it does not match.
     *                   Store it, and refuse codes from the same step or earlier, so a
     *                   code that has been seen cannot be used again.
     */
    public function match(string $secret, string $code, ?int $after = null): ?int
    {
        $code = (string) preg_replace('/\D/', '', $code);
        if (strlen($code) !== self::DIGITS) {
            return null;
        }

        $now = intdiv($this->clock->now()->getTimestamp(), self::PERIOD);
        for ($counter = $now - self::LEEWAY; $counter <= $now + self::LEEWAY; $counter++) {
            if ($after !== null && $counter <= $after) {
                continue;
            }
            if (hash_equals($this->codeForCounter($secret, $counter), $code)) {
                return $counter;
            }
        }

        return null;
    }

    private function codeForCounter(string $secret, int $counter): string
    {
        $hash = hash_hmac('sha1', pack('J', $counter), Base32::decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }
}
