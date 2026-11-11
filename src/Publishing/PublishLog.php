<?php

declare(strict_types=1);

namespace SlimKiller\Publishing;

use DateTimeImmutable;

/**
 * The recent saves and when each one went live, in storage/publishing.json. The panel
 * shows how the newest save is getting on, and estimates from how long the last ones
 * took, never from a fixed promise.
 */
final class PublishLog
{
    private const int KEEP = 20;

    public function __construct(
        private readonly string $file,
    ) {}

    public function saved(string $commit, DateTimeImmutable $at): void
    {
        $saves = array_values(array_filter($this->saves(), static fn(array $save): bool => $save['commit'] !== $commit));
        array_unshift($saves, ['commit' => $commit, 'savedAt' => $at->getTimestamp(), 'liveAt' => null, 'failedAt' => null]);
        $this->write(array_slice($saves, 0, self::KEEP));
    }

    /** @return array{commit: string, savedAt: int, liveAt: int|null, failedAt: int|null}|null */
    public function latest(): ?array
    {
        return $this->saves()[0] ?? null;
    }

    public function markLive(string $commit, DateTimeImmutable $at): void
    {
        $this->mark($commit, 'liveAt', $at);
    }

    public function markFailed(string $commit, DateTimeImmutable $at): void
    {
        $this->mark($commit, 'failedAt', $at);
    }

    /**
     * Seconds from save to live, for the saves that made it, newest first.
     *
     * @return list<int>
     */
    public function durations(): array
    {
        $durations = [];
        foreach ($this->saves() as $save) {
            if ($save['liveAt'] !== null) {
                $durations[] = max(0, $save['liveAt'] - $save['savedAt']);
            }
        }

        return $durations;
    }

    private function mark(string $commit, string $field, DateTimeImmutable $at): void
    {
        $saves = $this->saves();
        foreach ($saves as $index => $save) {
            if ($save['commit'] === $commit && $save[$field] === null) {
                $saves[$index][$field] = $at->getTimestamp();
                $this->write($saves);
            }
        }
    }

    /** @return list<array{commit: string, savedAt: int, liveAt: int|null, failedAt: int|null}> */
    private function saves(): array
    {
        $data = is_file($this->file) ? json_decode((string) file_get_contents($this->file), true) : null;
        $saves = [];
        foreach (is_array($data['saves'] ?? null) ? $data['saves'] : [] as $save) {
            if (! is_array($save) || ! is_string($save['commit'] ?? null) || ! is_int($save['savedAt'] ?? null)) {
                continue;
            }
            $saves[] = [
                'commit' => $save['commit'],
                'savedAt' => $save['savedAt'],
                'liveAt' => is_int($save['liveAt'] ?? null) ? $save['liveAt'] : null,
                'failedAt' => is_int($save['failedAt'] ?? null) ? $save['failedAt'] : null,
            ];
        }

        return $saves;
    }

    /** @param list<array{commit: string, savedAt: int, liveAt: int|null, failedAt: int|null}> $saves */
    private function write(array $saves): void
    {
        if (! is_dir(dirname($this->file))) {
            @mkdir(dirname($this->file), 0755, true);
        }
        $temporary = $this->file . '.writing';
        if (@file_put_contents($temporary, json_encode(['saves' => $saves], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n") !== false) {
            @rename($temporary, $this->file);
        }
    }
}
