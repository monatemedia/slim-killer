<?php

namespace App\Infrastructure\Commands\Hammer;

use App\Infrastructure\Commands\CommandInterface;
use App\Utils\NameParser;

class MakeViewCommand implements CommandInterface {
    public function getName(): string { return 'make:view'; }
    public function getDescription(): string { return 'Create a new pristine Blade UI template view file'; }

    public function handle(array $argv): void {
        if (!isset($argv[2])) { exit("\e[31m[ERROR] Missing view template name.\e[0m\n"); }
        
        // 1. Isolate flags cleanly
        $useMainLayout = in_array('--main', $argv) || in_array('-m', $argv);
        
        // 2. Filter out flags from the raw arguments list
        $cleanArgv = array_values(array_filter($argv, function($arg) {
            return strpos($arg, '-') !== 0;
        }));

        // 🐛 BUG FIX: Convert forward slashes to spaces so NameParser splits subfolders uniformly
        if (isset($cleanArgv[2])) {
            $cleanArgv[2] = str_replace('/', ' ', $cleanArgv[2]);
            // Re-flatten the array array tokens if the slash expansion introduced nested segments
            $expandedTokens = explode(' ', implode(' ', array_slice($cleanArgv, 2)));
            $cleanArgv = array_merge(array_slice($cleanArgv, 0, 2), $expandedTokens);
        }

        $parsed = NameParser::parse($cleanArgv, '');
        
        // Setup target resources/views directory resolution properties
        $subFolder = $parsed['relativeDir'] ? strtolower($parsed['relativeDir']) : '';
        $targetDir = dirname(__DIR__, 3) . "/resources/views" . $subFolder;
        
        // Ensure the full recursive directory tree is physically built out before file stream operations
        if (!is_dir($targetDir)) { mkdir($targetDir, 0755, true); }
        
        $filepath = "{$targetDir}/{$parsed['snakeName']}.blade.php";
        if (file_exists($filepath)) { exit("\e[31m[ERROR] Blade view layout template already exists.\e[0m\n"); }

        $friendlyTitle = ucwords(str_replace('_', ' ', $parsed['snakeName']));

        // 🤡 SLIM KILLER CORE UNCOUPLED BOUNDARIES (Tailwind Completely Purged)
        if ($useMainLayout) {
            // App UI Template scaffolding using pure semantic structure tags
            $template = "@extends('layouts.main')\n\n"
                . "@section('title', '{$friendlyTitle}')\n\n"
                . "@section('content')\n"
                . "<main>\n"
                . "    <h1>{$friendlyTitle}</h1>\n"
                . "</main>\n"
                . "@endsection\n";
        } else {
            // Pure, modular framework view layout template context payload
            $template = "\n"
                . "<div id=\"{$parsed['snakeName']}-component\">\n"
                . "    <h2>{$friendlyTitle}</h2>\n"
                . "</div>\n";
        }

        file_put_contents($filepath, $template);
        echo "\e[32m[SLIM KILLER CLI] Created Blade View:\e[0m {$filepath}\n";
    }
}