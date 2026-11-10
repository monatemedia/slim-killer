<?php

declare(strict_types=1);

namespace SlimKiller\Content;

use SlimKiller\Content\Form\Editor;
use SlimKiller\Content\Form\Field;

/**
 * Writes a checked form back into its content file, changing only what the client
 * changed (PRD: "saves change only what the person changed").
 *
 * The form's values are merged into the file's own values field by field. A value that
 * means the same as before keeps the file's original, so FrontMatter keeps its lines byte
 * for byte: text that differs only in a trailing line break or surrounding spaces, an
 * empty optional field the file never had, a toggle left off. Fields the schema does not
 * know are kept, never dropped. The body is kept as it was unless its text changed.
 */
final class ContentWriter
{
    /**
     * @param list<Field> $fields
     * @param array<string, mixed> $values the checked form (FormReader)
     * @param string|null $body the posted body, or null to keep the file's
     */
    public function write(string $raw, array $fields, array $values, ?string $body): string
    {
        $file = ContentFile::parse($raw);
        $frontMatter = FrontMatter::parse($file->yaml);
        $old = $frontMatter->data();
        $merged = $this->merge($old, $values, $fields);
        $oldBody = $file->body();
        $newBody = $body === null ? $oldBody : self::body($oldBody, $body);

        if ($merged === $old && $newBody === $oldBody) {
            return $raw;
        }

        return $file->with($frontMatter->with($merged), $newBody);
    }

    /**
     * @param array<mixed> $old
     * @param array<string, mixed> $new
     * @param list<Field> $fields
     * @return array<mixed>
     */
    private function merge(array $old, array $new, array $fields): array
    {
        $result = $old;
        foreach ($fields as $field) {
            $key = $field->name;
            $hadIt = array_key_exists($key, $old);
            if (! array_key_exists($key, $new)) {
                // Cleared by the client, unless it was empty in the file anyway.
                if ($hadIt && ! self::isEmpty($old[$key])) {
                    unset($result[$key]);
                }
                continue;
            }
            $value = $new[$key];
            if ($hadIt) {
                $result[$key] = $this->value($field, $old[$key], $value);
            } elseif (! $this->isDefault($field, $value)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /** The new value, or the old one when both mean the same. */
    private function value(Field $field, mixed $old, mixed $new): mixed
    {
        if ($field->kind === Editor::Group && is_array($old) && is_array($new)) {
            /** @var array<string, mixed> $new */
            return $this->merge($old, $new, $field->children);
        }
        if ($field->kind === Editor::List && is_array($old) && is_array($new)) {
            $items = [];
            foreach (array_values($new) as $index => $item) {
                $was = array_values($old)[$index] ?? null;
                /** @var array<string, mixed> $item */
                $items[] = is_array($was) ? $this->merge($was, $item, $field->children) : $item;
            }

            return $items === array_values($old) ? $old : $items;
        }
        if (is_string($old) && is_string($new)) {
            $lines = $field->kind === Editor::Textarea || $field->kind === Editor::Markdown;

            return self::sameText($old, $new, $lines) ? $old : $new;
        }
        if (is_numeric($old) && is_numeric($new) && $old == $new) {
            return $old;
        }

        return $old === $new ? $old : $new;
    }

    /** A value the file did not have that adds nothing: "no", or the schema's default. */
    private function isDefault(Field $field, mixed $value): bool
    {
        if ($field->kind === Editor::Toggle && $value === false && ($field->default === null || $field->default === false)) {
            return true;
        }

        return $field->default !== null && $value === $field->default;
    }

    /**
     * The new body, or the old one when only blank lines at the start or end, or line
     * endings, differ. A changed body keeps the file's blank lines under the front matter.
     */
    private static function body(string $old, string $new): string
    {
        $new = str_replace("\r\n", "\n", $new);
        if (trim($old, "\n") === trim($new, "\n")) {
            return $old;
        }
        preg_match('/^\n*/', $old, $lead);

        return ($lead[0] ?? '') . trim($new, "\n") . "\n";
    }

    private static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [] || $value === false;
    }

    private static function sameText(string $old, string $new, bool $lines): bool
    {
        $old = str_replace("\r\n", "\n", $old);
        $new = str_replace("\r\n", "\n", $new);

        return $lines ? rtrim($old) === rtrim($new) : trim($old) === trim($new);
    }
}
