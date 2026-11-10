<?php

declare(strict_types=1);

namespace SlimKiller\Content\Form;

/**
 * What a posted editing form holds, read against its fields: the values as the
 * front matter will store them, and the problems to show beside the fields.
 *
 * A field left empty is not in $values at all, so the site's schema can use its
 * default. $errors is keyed by the field's path: "title", "cta.heading",
 * "sections.2.image.alt".
 */
final readonly class Submission
{
    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     */
    public function __construct(
        public array $values,
        public array $errors,
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
