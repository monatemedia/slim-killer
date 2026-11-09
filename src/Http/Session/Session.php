<?php

declare(strict_types=1);

namespace SlimKiller\Http\Session;

use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * One visitor's session: a few values, kept in the database between requests. The
 * cookie holds a random token; the database holds only its hash.
 *
 * Created empty for every visitor, but only saved (and only given a cookie) once
 * something is put in it.
 */
final class Session
{
    public const string USER = 'user_id';

    private bool $changed = false;

    private bool $destroyed = false;

    private ?string $previousToken = null;

    /** @param array<string, scalar> $data */
    private function __construct(
        private string $token,
        private array $data,
        private readonly bool $new,
        public readonly int $createdAt,
    ) {}

    public static function start(int $now): self
    {
        return new self(self::newToken(), [], true, $now);
    }

    /** @param array<string, scalar> $data */
    public static function resume(string $token, array $data, int $createdAt): self
    {
        return new self($token, $data, false, $createdAt);
    }

    public static function of(ServerRequestInterface $request): self
    {
        $session = $request->getAttribute(self::class);
        if (! $session instanceof self) {
            throw new LogicException('No session on this request. Is SessionMiddleware added?');
        }

        return $session;
    }

    public function get(string $key): string|int|float|bool|null
    {
        return $this->data[$key] ?? null;
    }

    public function int(string $key): ?int
    {
        $value = $this->data[$key] ?? null;

        return is_int($value) ? $value : null;
    }

    public function string(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    public function set(string $key, string|int|float|bool $value): void
    {
        $this->data[$key] = $value;
        $this->changed = true;
    }

    public function remove(string ...$keys): void
    {
        foreach ($keys as $key) {
            unset($this->data[$key]);
        }
        $this->changed = true;
    }

    /** The signed-in user's id, or null. */
    public function userId(): ?int
    {
        return $this->int(self::USER);
    }

    /**
     * A new token for the same session. Call it whenever someone's access changes
     * (password accepted, signed in), so a token seen earlier is worth nothing now.
     */
    public function regenerate(): void
    {
        $this->previousToken ??= $this->new ? null : $this->token;
        $this->token = self::newToken();
        $this->changed = true;
    }

    public function destroy(): void
    {
        $this->data = [];
        $this->destroyed = true;
    }

    /** The token every form sends back, to prove it came from this panel. */
    public function csrfToken(): string
    {
        $token = $this->string('_csrf');
        if ($token === null) {
            $token = bin2hex(random_bytes(16));
            $this->set('_csrf', $token);
        }

        return $token;
    }

    public function token(): string
    {
        return $this->token;
    }

    public function previousToken(): ?string
    {
        return $this->previousToken;
    }

    /** @return array<string, scalar> */
    public function data(): array
    {
        return $this->data;
    }

    public function isNew(): bool
    {
        return $this->new;
    }

    public function isEmpty(): bool
    {
        return $this->data === [];
    }

    public function hasChanged(): bool
    {
        return $this->changed;
    }

    public function isDestroyed(): bool
    {
        return $this->destroyed;
    }

    private static function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
