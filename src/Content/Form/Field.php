<?php

declare(strict_types=1);

namespace SlimKiller\Content\Form;

/**
 * One field of an editing form, made from one property of a collection's schema.
 * A group has children; a list has the fields of one item as children.
 */
final readonly class Field
{
    /**
     * @param list<string> $options The choices of a select.
     * @param list<Field> $children
     */
    public function __construct(
        public string $name,
        public string $label,
        public Editor $kind,
        public bool $required,
        public string $help = '',
        public mixed $default = null,
        public array $options = [],
        public array $children = [],
        public bool $hinted = false,
    ) {}

    /** The editor's name, for templates. */
    public function editor(): string
    {
        return $this->kind->value;
    }
}
