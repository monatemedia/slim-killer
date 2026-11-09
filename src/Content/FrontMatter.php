<?php

declare(strict_types=1);

namespace SlimKiller\Content;

use Symfony\Component\Yaml\Yaml;

/**
 * Front matter, edited the way a person would: only what changed is written again.
 *
 * The YAML is split into top-level fields. A field whose value did not change keeps its
 * original lines byte for byte, with the blank lines and comments after it. A changed or
 * new field is written in house style: double quotes, "|" for text over several lines,
 * two-space indents, "- key: value" for lists of groups. Spike S4 checked this on 42 real
 * files: an unchanged save is identical, and a title edit changes one line.
 */
final class FrontMatter
{
    /** @param list<array{key: ?string, lines: list<string>}> $blocks */
    private function __construct(
        private readonly array $blocks,
    ) {}

    public static function parse(string $yaml): self
    {
        $blocks = [];
        $current = ['key' => null, 'lines' => []];
        foreach (explode("\n", $yaml) as $line) {
            if (preg_match('/^(["\']?)([A-Za-z_$][\w$-]*)\1\s*:(\s|$)/', $line, $match) === 1) {
                $blocks[] = $current;
                $current = ['key' => $match[2], 'lines' => [$line]];
            } else {
                $current['lines'][] = $line;
            }
        }
        $blocks[] = $current;

        return new self($blocks);
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        $data = Yaml::parse($this->text());

        return is_array($data) ? self::stringKeys($data) : [];
    }

    public function text(): string
    {
        $lines = [];
        foreach ($this->blocks as $block) {
            array_push($lines, ...$block['lines']);
        }

        return implode("\n", $lines);
    }

    /**
     * The YAML with $data's values: unchanged fields keep their text, changed and new
     * fields are written in house style, and fields missing from $data are removed.
     *
     * @param array<string, mixed> $data
     */
    public function with(array $data): string
    {
        $old = $this->data();
        $out = [];
        $seen = [];

        foreach ($this->blocks as $block) {
            $key = $block['key'];
            if ($key === null) {
                array_push($out, ...$block['lines']);
                continue;
            }
            $seen[$key] = true;
            [$content, $gap] = self::splitGap($block['lines']);

            if (! array_key_exists($key, $data)) {
                array_push($out, ...$gap);
            } elseif (array_key_exists($key, $old) && $old[$key] === $data[$key]) {
                array_push($out, ...$content, ...$gap);
            } else {
                array_push($out, ...self::emit($key, $data[$key], 0), ...$gap);
            }
        }

        foreach ($data as $key => $value) {
            if (! isset($seen[$key])) {
                array_push($out, ...self::emit($key, $value, 0));
            }
        }

        return implode("\n", $out);
    }

    /**
     * One field in house style, as lines.
     *
     * @return list<string>
     */
    public static function emit(string $key, mixed $value, int $indent): array
    {
        $pad = str_repeat(' ', $indent);

        if (is_array($value) && $value === []) {
            return ["{$pad}{$key}: []"];
        }
        if (is_array($value) && array_is_list($value)) {
            $lines = ["{$pad}{$key}:"];
            foreach ($value as $item) {
                array_push($lines, ...self::listItem($item, $indent + 2));
            }

            return $lines;
        }
        if (is_array($value)) {
            $lines = ["{$pad}{$key}:"];
            foreach ($value as $childKey => $childValue) {
                array_push($lines, ...self::emit((string) $childKey, $childValue, $indent + 2));
            }

            return $lines;
        }
        if (is_string($value) && str_contains(rtrim($value, "\n"), "\n")) {
            $chomp = match (true) {
                str_ends_with($value, "\n\n") => '|+',
                str_ends_with($value, "\n") => '|',
                default => '|-',
            };
            $lines = ["{$pad}{$key}: {$chomp}"];
            foreach (explode("\n", rtrim($value, "\n")) as $line) {
                $lines[] = $line === '' ? '' : "{$pad}  {$line}";
            }

            return $lines;
        }

        return ["{$pad}{$key}: " . self::scalar($value)];
    }

    /** @return list<string> */
    private static function listItem(mixed $item, int $indent): array
    {
        $pad = str_repeat(' ', $indent);

        if (is_array($item) && $item !== [] && ! array_is_list($item)) {
            $lines = [];
            foreach ($item as $key => $value) {
                array_push($lines, ...self::emit((string) $key, $value, $indent + 2));
            }
            $lines[0] = $pad . '- ' . substr($lines[0], $indent + 2);

            return $lines;
        }
        if (is_array($item)) {
            return ["{$pad}- " . ($item === [] ? '[]' : Yaml::dump($item, 0))];
        }

        return ["{$pad}- " . self::scalar($item)];
    }

    private static function scalar(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            is_string($value) => '"' . addcslashes($value, "\"\\\0\t\r\n") . '"',
            default => '""',
        };
    }

    /**
     * @param list<string> $lines
     * @return array{0: list<string>, 1: list<string>} The field's own lines, and the blank or comment lines after it.
     */
    private static function splitGap(array $lines): array
    {
        $gap = [];
        while ($lines !== [] && preg_match('/^\s*(#.*)?$/', $lines[array_key_last($lines)]) === 1) {
            array_unshift($gap, array_pop($lines));
        }

        return [$lines, $gap];
    }

    /**
     * @param array<mixed> $data
     * @return array<string, mixed>
     */
    private static function stringKeys(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }
}
