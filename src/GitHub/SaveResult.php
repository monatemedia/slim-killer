<?php

declare(strict_types=1);

namespace SlimKiller\GitHub;

/**
 * How a save to GitHub ended: saved (with its commit), a conflict (someone saved the
 * same page after the client opened it), or failed (GitHub could not be reached or
 * refused). The message is in the client's words.
 */
final readonly class SaveResult
{
    private function __construct(
        public string $outcome,
        public string $message,
        public ?string $commit = null,
        public ?string $version = null,
        public ?string $current = null,
    ) {}

    /** @param string $version the file's new version (its Git blob hash) */
    public static function saved(string $commit, string $version): self
    {
        return new self('saved', 'Saved.', $commit, $version);
    }

    /** @param string|null $current the page as it now is on GitHub, for the working copy */
    public static function conflict(?string $current = null): self
    {
        return new self(
            'conflict',
            'Someone saved this page after you opened it. Your changes are still in the form: '
            . 'copy anything you want to keep, then press Discard changes to see the newer version.',
            current: $current,
        );
    }

    public static function failed(string $message): self
    {
        return new self('failed', $message);
    }

    public function isSaved(): bool
    {
        return $this->outcome === 'saved';
    }

    public function isConflict(): bool
    {
        return $this->outcome === 'conflict';
    }
}
