<?php

declare(strict_types=1);

namespace SlimKiller\Backups;

use SlimKiller\Support\Hammer;

/**
 * Locks a backup before it leaves this server, so the copy on Google Drive is unreadable
 * without the site's BACKUP_KEY: the backups hold password hashes and two-factor
 * secrets, and more than one person can open that Drive.
 *
 * AES-256-GCM, with PHP's openssl, on every host. sodium is missing on Hostinger, and one
 * format everywhere means a backup made on one host always opens on another.
 *
 * A locked file is: "SKB1", the first 8 bytes of the key's SHA-256 (so a wrong key is
 * named as that, not as damage), a 12-byte nonce, the 16-byte tag, then the ciphertext.
 * The tag proves the file is whole as well as that the key is right.
 *
 * The copies kept on this server are not locked: the key is in this server's .env.
 */
final readonly class BackupEncryption
{
    public const string SUFFIX = '.enc';

    private const string MAGIC = 'SKB1';

    private const string CIPHER = 'aes-256-gcm';

    private const int FINGERPRINT = 8;

    private const int NONCE = 12;

    private const int TAG = 16;

    /** @param string $key BACKUP_KEY: 64 hexadecimal characters (32 bytes) */
    public function __construct(
        private string $key,
    ) {}

    /** Is BACKUP_KEY set, and the right length? */
    public function isSetUp(): bool
    {
        return preg_match('/^[0-9a-f]{64}$/i', $this->key) === 1;
    }

    /** A new key for .env, to keep in a password manager as well. */
    public static function newKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    /** @throws BackupFailed */
    public function lock(string $plain): string
    {
        $key = $this->bytes();
        $nonce = random_bytes(self::NONCE);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, self::MAGIC, self::TAG);
        if ($cipher === false) {
            throw new BackupFailed('openssl could not encrypt the backup.');
        }

        return self::MAGIC . $this->fingerprint($key) . $nonce . $tag . $cipher;
    }

    /** @throws BackupFailed */
    public function unlock(string $locked): string
    {
        $key = $this->bytes();
        $head = strlen(self::MAGIC);
        if (! str_starts_with($locked, self::MAGIC) || strlen($locked) < $head + self::FINGERPRINT + self::NONCE + self::TAG) {
            throw new BackupFailed('That file is not an encrypted Slim Killer backup.');
        }
        if (substr($locked, $head, self::FINGERPRINT) !== $this->fingerprint($key)) {
            throw new BackupFailed('That backup was encrypted with a different BACKUP_KEY. Put the key it was made with in .env (it is in the password manager).');
        }

        $at = $head + self::FINGERPRINT;
        $nonce = substr($locked, $at, self::NONCE);
        $tag = substr($locked, $at + self::NONCE, self::TAG);
        $plain = openssl_decrypt(substr($locked, $at + self::NONCE + self::TAG), self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, self::MAGIC);
        if ($plain === false) {
            throw new BackupFailed('The encrypted backup is damaged: it does not match its own check.');
        }

        return $plain;
    }

    private function bytes(): string
    {
        if (! $this->isSetUp()) {
            throw new BackupFailed('BACKUP_KEY in .env is empty or not 64 hexadecimal characters. Make one with: ' . Hammer::command('backup:key'));
        }

        return (string) hex2bin($this->key);
    }

    private function fingerprint(string $key): string
    {
        return substr(hash('sha256', $key, true), 0, self::FINGERPRINT);
    }
}
