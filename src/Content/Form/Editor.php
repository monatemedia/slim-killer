<?php

declare(strict_types=1);

namespace SlimKiller\Content\Form;

/**
 * Which editor a field gets. A schema field asks for one with `.meta({ input: '...' })`
 * (an editor hint); otherwise it is chosen from the field's type and name.
 */
enum Editor: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Markdown = 'markdown';
    case Image = 'image';
    case Select = 'select';
    case Toggle = 'toggle';
    case Number = 'number';
    case Group = 'group';
    case List = 'list';
    case Tags = 'tags';
}
