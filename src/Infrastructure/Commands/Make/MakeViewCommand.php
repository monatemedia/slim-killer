<?php

declare(strict_types=1);

namespace App\Infrastructure\Commands\Make;

use App\Infrastructure\Commands\CommandInterface;
use App\Utils\NameParser;

class MakeViewCommand implements CommandInterface {
    public function getName(): string { return 'make:view'; }
    public function getDescription(): string { return 'Create a new pristine Twig UI template view file'; }

    public function handle(array $argv): void {
        if (!isset($argv[2])) { exit("\e[31m[ERROR] Missing view template name.\e[0m\n"); }
        
        $useMainLayout = in_array('--main', $argv) || in_array('-m', $argv);
        
        $cleanArgv = array_values(array_filter($argv, function($arg) {
            return strpos($arg, '-') !== 0;
        }));

        if (isset($cleanArgv[2])) {
            $cleanArgv[2] = str_replace('/', ' ', $cleanArgv[2]);
            $expandedTokens = explode(' ', implode(' ', array_slice($cleanArgv, 2)));
            $cleanArgv = array_merge(array_slice($cleanArgv, 0, 2), $expandedTokens);
        }

        $parsed = NameParser::parse($cleanArgv, '');
        
        $subFolder = $parsed['relativeDir'] ? strtolower($parsed['relativeDir']) : '';
        // Target: resources/views/
        $targetDir = dirname(__DIR__, 3) . "/resources/views" . $subFolder;
        
        if (!is_dir($targetDir)) { mkdir($targetDir, 0755, true); }
        
        $filepath = "{$targetDir}/{$parsed['snakeName']}.twig";
        if (file_exists($filepath)) { exit("\e[31m[ERROR] Twig view layout template already exists.\e[0m\n"); }

        $friendlyTitle = ucwords(str_replace('_', ' ', $parsed['snakeName']));

        if ($useMainLayout) {
            $template = "{% extends 'layouts/main.twig' %}\n\n"
                . "{% block title %}{$friendlyTitle}{% endblock %}\n\n"
                . "{% block content %}\n"
                . "<main>\n"
                . "    <h1>{$friendlyTitle}</h1>\n"
                . "</main>\n"
                . "{% endblock %}\n";
        } else {
            $template = "\n"
                . "<div id=\"{$parsed['snakeName']}-component\">\n"
                . "    <h2>{$friendlyTitle}</h2>\n"
                . "</div>\n";
        }

        file_put_contents($filepath, $template);
        echo "\e[32m[SLIM KILLER CLI] Created Twig View Template:\e[0m {$filepath}\n";
    }
}