<?php

declare(strict_types=1);

namespace SlimKiller\Content\Form;

/**
 * Reads a posted editing form against its fields (from the collection's schema).
 *
 * Text is trimmed, and text from a box keeps its lines with Unix line endings.
 * An empty field is left out, so the site's default applies. Numbers become numbers,
 * a toggle becomes yes or no, tags become a list, and a list keeps its items in the
 * order they were posted. Problems are described in the client's words.
 */
final class FormReader
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @param list<Field> $fields */
    public function read(array $fields, mixed $posted): Submission
    {
        $this->errors = [];
        $values = $this->group($fields, is_array($posted) ? $posted : [], '');

        return new Submission($values, $this->errors);
    }

    /**
     * @param list<Field> $fields
     * @param array<mixed> $posted
     * @return array<string, mixed>
     */
    private function group(array $fields, array $posted, string $prefix): array
    {
        $values = [];
        foreach ($fields as $field) {
            $value = $this->value($field, $posted[$field->name] ?? null, $prefix . $field->name);
            if ($value !== null) {
                $values[$field->name] = $value;
            }
        }

        return $values;
    }

    private function value(Field $field, mixed $posted, string $path): mixed
    {
        return match ($field->kind) {
            Editor::Group => $this->groupValue($field, $posted, $path),
            Editor::List => $this->listValue($field, $posted, $path),
            Editor::Toggle => $posted !== null && $posted !== '' && $posted !== '0',
            Editor::Tags => $this->tags($field, $posted, $path),
            Editor::Number => $this->number($field, $posted, $path),
            Editor::Select => $this->choice($field, $posted, $path),
            Editor::Textarea, Editor::Markdown => $this->text($field, $posted, $path, lines: true),
            default => $this->text($field, $posted, $path, lines: false),
        };
    }

    /** @return array<string, mixed>|null */
    private function groupValue(Field $field, mixed $posted, string $path): ?array
    {
        // An optional group left completely empty is not there: its required parts
        // only matter once the client starts filling it in.
        if (! $field->required && (! is_array($posted) || $this->isBlank($posted))) {
            return null;
        }
        $values = $this->group($field->children, is_array($posted) ? $posted : [], $path . '.');
        if ($values === [] && ! $field->required) {
            return null;
        }

        return $values;
    }

    /** @return list<array<string, mixed>>|null */
    private function listValue(Field $field, mixed $posted, string $path): ?array
    {
        $items = [];
        foreach (is_array($posted) ? array_values($posted) : [] as $item) {
            // An item with nothing in it is left out: a blank row the client did not fill in.
            if (! is_array($item) || $this->isBlank($item)) {
                continue;
            }
            $items[] = $this->group($field->children, $item, $path . '.' . count($items) . '.');
        }
        if ($items === [] && ! $field->required) {
            return null;
        }

        return $items;
    }

    /** @param array<mixed> $item */
    private function isBlank(array $item): bool
    {
        foreach ($item as $value) {
            if (is_array($value) ? ! $this->isBlank($value) : trim(is_scalar($value) ? (string) $value : '') !== '') {
                return false;
            }
        }

        return true;
    }

    private function text(Field $field, mixed $posted, string $path, bool $lines): ?string
    {
        // Line endings from the browser are Windows ones; a title may hold a line break too.
        $text = str_replace("\r\n", "\n", is_scalar($posted) ? (string) $posted : '');
        $text = $lines ? rtrim($text) : trim($text);
        if ($text === '') {
            return $this->missing($field, $path);
        }
        $length = mb_strlen($text);
        if ($field->maxLength !== null && $length > $field->maxLength) {
            $this->errors[$path] = sprintf(
                '%s can have up to %d characters. This has %d.',
                $field->label,
                $field->maxLength,
                $length,
            );
        }

        return $text;
    }

    private function choice(Field $field, mixed $posted, string $path): ?string
    {
        $choice = is_scalar($posted) ? trim((string) $posted) : '';
        if ($choice === '') {
            return $this->missing($field, $path);
        }
        if (! in_array($choice, $field->options, true)) {
            $this->errors[$path] = sprintf('Choose one of the options for %s.', $field->label);
        }

        return $choice;
    }

    private function number(Field $field, mixed $posted, string $path): int|float|null
    {
        $text = is_scalar($posted) ? trim((string) $posted) : '';
        if ($text === '') {
            return $this->missing($field, $path);
        }
        $pattern = $field->integer ? '/^-?\d+$/' : '/^-?\d+(\.\d+)?$/';
        if (preg_match($pattern, $text) !== 1) {
            $this->errors[$path] = sprintf($field->integer ? '%s must be a whole number.' : '%s must be a number.', $field->label);

            return null;
        }

        return str_contains($text, '.') ? (float) $text : (int) $text;
    }

    /** @return list<string>|null */
    private function tags(Field $field, mixed $posted, string $path): ?array
    {
        $tags = array_values(array_filter(
            array_map('trim', explode(',', is_scalar($posted) ? (string) $posted : '')),
            static fn(string $tag): bool => $tag !== '',
        ));
        if ($tags === []) {
            return $this->missing($field, $path);
        }

        return $tags;
    }

    private function missing(Field $field, string $path): null
    {
        if ($field->required) {
            $this->errors[$path] = sprintf('%s is needed.', $field->label);
        }

        return null;
    }
}
