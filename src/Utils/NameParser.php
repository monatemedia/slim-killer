<?php

namespace App\Utils;

class NameParser {
    /**
     * Parse input tokens into structured, framework-compliant naming parameters.
     */
    public static function parse(array $argv, string $suffix, int $sliceOffset = 2): array {
        // 1. Ingest raw arguments
        $rawInput = implode(' ', array_slice($argv, $sliceOffset));

        // 2. Normalize and preserve directory structures before splitting strings
        $rawInput = str_replace('\\', '/', $rawInput);
        
        // Isolate the directory segments from the core class name targeting
        $dirParts = explode('/', $rawInput);
        $targetClassInput = array_pop($dirParts); // Extract the last item as the file target

        // Process directory segments if they exist
        $subNamespace = '';
        $relativeDir = '';
        if (!empty($dirParts)) {
            $cleanDirs = [];
            foreach ($dirParts as $dir) {
                // Keep your custom token separator parsing intact for directory strings too
                $cDir = preg_replace('/(?<!^)[A-Z]/', ' $0', $dir);
                $cDir = str_replace(['.', '-', '_'], ' ', $cDir);
                $tokens = array_filter(explode(' ', $cDir));
                if (!empty($tokens)) {
                    $cleanDirs[] = implode('', array_map('ucfirst', array_map('strtolower', $tokens)));
                }
            }
            if (!empty($cleanDirs)) {
                $subNamespace = '\\' . implode('\\', $cleanDirs);
                $relativeDir = '/' . implode('/', $cleanDirs);
            }
        }

        // 3. Keep your complete original token cleanup logic for the target class name
        $targetClassInput = preg_replace('/(?<!^)(' . preg_quote($suffix, '/') . ')$/i', ' $1', $targetClassInput);
        $cleanInput = preg_replace('/(?<!^)[A-Z]/', ' $0', $targetClassInput);
        $cleanInput = str_replace(['.', '-', '_'], ' ', $cleanInput);

        $parts = array_values(array_filter(explode(' ', $cleanInput)));
        $parts = array_map('strtolower', $parts);

        // Fallback guard
        if (empty($parts)) {
            return [
                'className' => 'Example' . ucfirst($suffix),
                'snakeName' => 'example',
                'subNamespace' => $subNamespace,
                'relativeDir' => $relativeDir
            ];
        }

        // Reassemble core tokens back into pristine PascalCase
        $className = implode('', array_map('ucfirst', $parts));

        // Enforce rigid naming constraints
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