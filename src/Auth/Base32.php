<?php

declare(strict_types=1);

namespace SlimKiller\Auth;

/**
 * RFC 4648 base32, without padding: the alphabet authenticator apps use for secrets.
 */
final class Base32
{
    private const string ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::ALPHABET[(int) bindec(str_pad($chunk, 5, '0'))];
        }

        return $bytes === '' ? '' : $encoded;
    }

    /** Ignores spaces, dashes, padding and case, as people type them. */
    public static function decode(string $encoded): string
    {
        $clean = strtoupper((string) preg_replace('/[\s=-]/', '', $encoded));

        $bits = '';
        foreach (str_split($clean) as $character) {
            $value = strpos(self::ALPHABET, $character);
            if ($value === false) {
                return '';
            }
            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr((int) bindec($chunk));
            }
        }

        return $clean === '' ? '' : $bytes;
    }
}
