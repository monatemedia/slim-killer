<?php

declare(strict_types=1);

namespace SlimKiller\Support;

/**
 * Where things live, relative to the project root. Built once in the container.
 */
final readonly class Paths
{
    public function __construct(
        private string $root,
    ) {}

    public function root(string $path = ''): string
    {
        return $this->join($this->root, $path);
    }

    public function storage(string $path = ''): string
    {
        return $this->join($this->root . '/storage', $path);
    }

    private function join(string $base, string $path): string
    {
        $base = rtrim($base, '/\\');

        return $path === '' ? $base : $base . '/' . ltrim($path, '/\\');
    }
}
