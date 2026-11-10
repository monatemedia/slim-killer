<?php

declare(strict_types=1);

namespace SlimKiller\Content\Form;

/**
 * One change to a list in an editing form: add an item, remove one, or move one up
 * or down. It comes from the button the client pressed ("add:sections",
 * "remove:sections.2", "up:gallery.images.1") and is applied to the form's values
 * before the form is shown again. Nothing is saved until the client presses Save.
 */
final readonly class ListChange
{
    private const array KINDS = ['add', 'remove', 'up', 'down'];

    /**
     * @param list<string> $list the list's path
     * @param int|null $item the item's place in the list; null for "add"
     */
    private function __construct(
        public string $kind,
        public array $list,
        public ?int $item,
    ) {}

    /** The change a button stands for, or null when its value is not one. */
    public static function from(mixed $button): ?self
    {
        if (! is_string($button) || preg_match('/^([a-z]+):([A-Za-z0-9_.]+)$/', $button, $match) !== 1) {
            return null;
        }
        if (! in_array($match[1], self::KINDS, true)) {
            return null;
        }
        $path = explode('.', $match[2]);
        if ($match[1] === 'add') {
            return new self('add', $path, null);
        }
        $item = array_pop($path);
        if (! ctype_digit($item) || $path === []) {
            return null;
        }

        return new self($match[1], $path, (int) $item);
    }

    /**
     * Applies the change to the form's values.
     *
     * @param array<string, mixed> $values
     * @return array{0: array<string, mixed>, 1: string|null} the new values, and the path
     *         of the item to put the cursor in (the new one, after "add")
     */
    public function apply(array $values): array
    {
        $listPath = $this->list;
        $list = self::get($values, $listPath);
        $list = is_array($list) ? array_values($list) : [];
        $index = $this->item ?? count($list);
        $focus = null;

        switch ($this->kind) {
            case 'add':
                $list[] = [];
                $focus = implode('.', [...$listPath, (string) $index]);
                break;
            case 'remove':
                array_splice($list, $index, 1);
                break;
            case 'up':
            case 'down':
                $other = $this->kind === 'up' ? $index - 1 : $index + 1;
                if (isset($list[$index], $list[$other])) {
                    [$list[$index], $list[$other]] = [$list[$other], $list[$index]];
                    $focus = implode('.', [...$listPath, (string) $other]);
                }
                break;
        }

        /** @var array<string, mixed> $changed */
        $changed = self::set($values, $listPath, $list);

        return [$changed, $focus];
    }

    /** @param list<string> $path */
    private static function get(mixed $values, array $path): mixed
    {
        foreach ($path as $key) {
            if (! is_array($values) || ! array_key_exists($key, $values)) {
                return null;
            }
            $values = $values[$key];
        }

        return $values;
    }

    /**
     * @param array<mixed> $values
     * @param list<string> $path
     * @return array<mixed>
     */
    private static function set(array $values, array $path, mixed $value): array
    {
        $key = array_shift($path);
        if ($key === null) {
            return is_array($value) ? $value : $values;
        }
        $values[$key] = $path === [] ? $value : self::set(is_array($values[$key] ?? null) ? $values[$key] : [], $path, $value);

        return $values;
    }
}
