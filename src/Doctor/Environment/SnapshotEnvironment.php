<?php

declare(strict_types=1);

namespace SlimKiller\Doctor\Environment;

/**
 * A PHP runtime described by data: the website's PHP as reported by the probe file, or
 * any environment a test wants to describe.
 */
final readonly class SnapshotEnvironment implements Environment
{
    /**
     * @param list<string> $extensions
     * @param array<string, string> $ini
     * @param list<string> $disabledFunctions
     */
    public function __construct(
        private string $label,
        private string $phpVersion,
        private string $sapi,
        private string $phpBinary = '',
        private array $extensions = [],
        private array $ini = [],
        private array $disabledFunctions = [],
        private bool $canWriteJpeg = false,
        private bool $readsWebp = false,
    ) {}

    /**
     * Builds a snapshot from the probe file's JSON report.
     *
     * @param array<mixed> $data
     */
    public static function fromProbe(array $data, string $label = 'Website'): self
    {
        $strings = static fn(mixed $list): array => array_values(array_filter(
            is_array($list) ? $list : [],
            'is_string',
        ));

        $ini = [];
        foreach (is_array($data['ini'] ?? null) ? $data['ini'] : [] as $key => $value) {
            if (is_string($key) && (is_string($value) || is_int($value))) {
                $ini[$key] = (string) $value;
            }
        }

        return new self(
            label: $label,
            phpVersion: is_string($data['php_version'] ?? null) ? $data['php_version'] : '0.0.0',
            sapi: is_string($data['sapi'] ?? null) ? $data['sapi'] : 'unknown',
            phpBinary: is_string($data['php_binary'] ?? null) ? $data['php_binary'] : '',
            extensions: array_map('strtolower', $strings($data['extensions'] ?? [])),
            ini: $ini,
            disabledFunctions: $strings($data['disabled_functions'] ?? []),
            canWriteJpeg: ($data['jpeg_write'] ?? false) === true,
            readsWebp: ($data['webp_read'] ?? false) === true,
        );
    }

    public function label(): string
    {
        return $this->label;
    }

    public function isCommandLine(): bool
    {
        return $this->sapi === 'cli';
    }

    public function phpVersion(): string
    {
        return $this->phpVersion;
    }

    public function sapi(): string
    {
        return $this->sapi;
    }

    public function phpBinary(): string
    {
        return $this->phpBinary;
    }

    public function hasExtension(string $name): bool
    {
        return in_array(strtolower($name), $this->extensions, true);
    }

    public function ini(string $key): ?string
    {
        return $this->ini[$key] ?? null;
    }

    public function disabledFunctions(): array
    {
        return $this->disabledFunctions;
    }

    public function canWriteJpeg(): bool
    {
        return $this->canWriteJpeg;
    }

    public function readsWebp(): bool
    {
        return $this->readsWebp;
    }
}
