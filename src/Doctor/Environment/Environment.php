<?php

declare(strict_types=1);

namespace SlimKiller\Doctor\Environment;

/**
 * The facts about one PHP runtime that the doctor checks. The command line is read
 * directly (RuntimeEnvironment); the website is read through a probe file and arrives
 * as a SnapshotEnvironment. Checks never know which one they are looking at.
 */
interface Environment
{
    /** "Command line" or "Website", for headings and hints. */
    public function label(): string;

    public function isCommandLine(): bool;

    public function phpVersion(): string;

    public function sapi(): string;

    public function phpBinary(): string;

    public function hasExtension(string $name): bool;

    public function ini(string $key): ?string;

    /** @return list<string> */
    public function disabledFunctions(): array;

    /** Can GD actually write a WebP image (not just claim to support it)? */
    public function canWriteWebp(): bool;

    public function supportsAvif(): bool;
}
