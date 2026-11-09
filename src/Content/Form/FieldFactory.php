<?php

declare(strict_types=1);

namespace SlimKiller\Content\Form;

use SlimKiller\Content\Labels;

/**
 * Builds a form from a collection's JSON Schema (what `astro sync` writes, spike S3).
 *
 * A field's label is its schema `title`, its help text its `description`, and its editor
 * its `input` hint. Without a hint the editor is chosen from the field's type and name:
 * names ending in "image", "src" or "photo" get an image picker, "description", "lead",
 * "body" and similar get a box for longer text. The site's schema is the only place
 * fields are defined (wiki: Astro site rules, rule 6).
 */
final class FieldFactory
{
    /**
     * @param array<mixed> $schema
     * @return list<Field>
     */
    public function fields(array $schema): array
    {
        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];
        if (! is_array($properties)) {
            return [];
        }
        $required = is_array($required) ? $required : [];

        $fields = [];
        foreach ($properties as $name => $property) {
            $name = (string) $name;
            if ($name === '$schema' || ! is_array($property)) {
                continue;
            }
            $fields[] = $this->field($name, $property, in_array($name, $required, true));
        }

        return $fields;
    }

    /** @param array<mixed> $property */
    private function field(string $name, array $property, bool $required): Field
    {
        $title = $property['title'] ?? null;
        $help = $property['description'] ?? null;
        $label = is_string($title) && $title !== '' ? $title : Labels::fromName($name);
        $help = is_string($help) ? $help : '';
        $default = $property['default'] ?? null;
        $type = $property['type'] ?? 'string';
        $items = $property['items'] ?? null;

        if ($type === 'object') {
            return new Field($name, $label, Editor::Group, $required, $help, $default, children: $this->fields($property));
        }
        if ($type === 'array' && is_array($items) && ($items['type'] ?? null) === 'object') {
            return new Field($name, $label, Editor::List, $required, $help, $default, children: $this->fields($items));
        }
        if ($type === 'array') {
            return new Field($name, $label, Editor::Tags, $required, $help, $default);
        }

        $options = [];
        foreach (is_array($property['enum'] ?? null) ? $property['enum'] : [] as $option) {
            if (is_scalar($option)) {
                $options[] = (string) $option;
            }
        }

        $hint = Editor::tryFrom(is_string($property['input'] ?? null) ? $property['input'] : '');
        $editor = $hint ?? $this->guess($name, is_string($type) ? $type : 'string', $options !== []);

        return new Field($name, $label, $editor, $required, $help, $default, $options, hinted: $hint !== null);
    }

    private function guess(string $name, string $type, bool $hasOptions): Editor
    {
        return match (true) {
            $hasOptions => Editor::Select,
            $type === 'boolean' => Editor::Toggle,
            $type === 'number', $type === 'integer' => Editor::Number,
            preg_match('/alt$/i', $name) === 1 => Editor::Text,
            preg_match('/(image|src|photo|logo|icon)$/i', $name) === 1 => Editor::Image,
            preg_match('/^(body|lead|intro|bio|answer|description|detail|text|summary)$/i', $name) === 1 => Editor::Textarea,
            default => Editor::Text,
        };
    }
}
