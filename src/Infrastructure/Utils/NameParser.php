<?php

namespace App\Utils;

class NameParser {
    /**
     * Parse input tokens into structured, framework-compliant naming parameters.
     */
    public static function parse(array $argv, string $suffix, int $sliceOffset = 2): array {
        // Flatten inputs into a single manageable string
        $rawInput = implode(' ', array_slice($argv, $sliceOffset));

        // Inject space before structural keyword if attached to the end of a word
        $rawInput = preg_replace('/(?<!^)(' . preg_quote($suffix, '/') . ')$/i', ' $1', $rawInput);

        // Turn camelCase/PascalCase boundaries and common separators into plain spaces
        $cleanInput = preg_replace('/(?<!^)[A-Z]/', ' $0', $rawInput);
        $cleanInput = str_replace(['.', '-', '_'], ' ', $cleanInput);

        // Break into clean, lowercased alphabetical tokens
        $parts = array_values(array_filter(explode(' ', $cleanInput)));
        $parts = array_map('strtolower', $parts);

        // Fallback if the user typed nothing or whitespace
        if (empty($parts)) {
            return [
                'className' => 'Example' . ucfirst($suffix),
                'snakeName' => 'example',
                'subNamespace' => '',
                'relativeDir' => ''
            ];
        }

        // Context-Aware Slicing: If there are multiple tokens, use the first as a folder grouping
        if (count($parts) >= 2) {
            $category = ucfirst($parts[0]);
            $classTokens = array_slice($parts, 1);
            $subNamespace = "\\" . $category;
            $relativeDir = "/" . $category;
        } else {
            $classTokens = $parts;
            $subNamespace = "";
            $relativeDir = "";
        }

        // Reassemble class tokens back into structured PascalCase
        $className = implode('', array_map('ucfirst', $classTokens));

        // Enforce the rigid structural class naming compliance suffix
        if (!str_ends_with(strtolower($className), strtolower($suffix))) {
            $className .= ucfirst(strtolower($suffix));
        }

        // Generate a clean snake_case conversion for tables or view files
        $snakeName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', str_replace($suffix, '', $className)));
        $snakeName = trim(str_replace('__', '_', $snakeName), '_');

        return [
            'className'    => $className,
            'snakeName'    => $snakeName,
            'subNamespace' => $subNamespace,
            'relativeDir'  => $relativeDir
        ];
    }
}