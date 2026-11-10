<?php

declare(strict_types=1);

namespace SlimKiller\Console;

/**
 * The command line, split into a command name, arguments and --options.
 *
 *   hammer doctor --url=https://example.com --webroot ~/public_html -v
 *
 * `script` is the name it was started with: "hammer", or "artisan" for Laravel habits.
 */
final readonly class Input
{
    /**
     * @param list<string> $arguments
     * @param array<string, string|true> $options
     */
    public function __construct(
        public string $command,
        public array $arguments = [],
        public array $options = [],
        public string $script = 'hammer',
    ) {}

    /** @param list<string> $argv The raw $argv, script name first. */
    public static function fromArgv(array $argv): self
    {
        $tokens = array_slice($argv, 1);
        $command = '';
        $arguments = [];
        $options = [];

        for ($i = 0, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];

            if (str_starts_with($token, '--')) {
                $option = substr($token, 2);
                if (str_contains($option, '=')) {
                    [$name, $value] = explode('=', $option, 2);
                    $options[$name] = $value;
                } elseif (isset($tokens[$i + 1]) && ! str_starts_with($tokens[$i + 1], '-')) {
                    $options[$option] = $tokens[++$i];
                } else {
                    $options[$option] = true;
                }
            } elseif (str_starts_with($token, '-') && strlen($token) > 1) {
                foreach (str_split(substr($token, 1)) as $flag) {
                    $options[$flag] = true;
                }
            } elseif ($command === '') {
                $command = $token;
            } else {
                $arguments[] = $token;
            }
        }

        // basename() only knows "/" on Linux, so Windows paths are turned round first.
        $script = pathinfo(basename(str_replace('\\', '/', $argv[0] ?? 'hammer')), PATHINFO_FILENAME);

        return new self($command, $arguments, $options, $script === '' ? 'hammer' : $script);
    }

    public function option(string $name): ?string
    {
        $value = $this->options[$name] ?? null;

        return is_string($value) ? $value : null;
    }

    public function flag(string $name): bool
    {
        return isset($this->options[$name]);
    }
}
