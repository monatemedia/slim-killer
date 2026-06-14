<?php

declare(strict_types=1);

namespace App\Infrastructure\Commands\Make;

use App\Infrastructure\Commands\CommandInterface;
use App\Utils\NameParser;

class MakeViewCommand implements CommandInterface {
    
    public function getName(): string { return 'make:view'; }
    public function getDescription(): string { return 'Create a new pristine Twig UI template view file'; }

    public function handle(array $argv): void {
        // Intercept flags first
        $useMainLayout = in_array('--main', $argv) || in_array('-m', $argv);
        
        $cleanArgv = array_values(array_filter($argv, function($arg) {
            return strpos($arg, '-') !== 0;
        }));

        // Guard Blueprint Notice for view targeting
        if (!isset($cleanArgv[2]) || empty(trim($cleanArgv[2]))) {
            echo "\e[31m[ERROR] Command 'make:view' requires a template designation argument.\e[0m\n\n";
            echo " \e[33mUsage Structure:\e[0m\n";
            echo "   php hammer make:view [Folder/]<ViewName> [options]\n\n";
            echo " \e[33mOptions:\e[0m\n";
            echo "   -m, --main    Extend the primary view layout skeleton layout engine automatically\n\n";
            echo " \e[33mExamples:\e[0m\n";
            echo "   php hammer make:view pages/about --main\n";
            echo "   php hammer make:view partials/banner\n";
            exit(1);
        }

        $parsed = NameParser::parse($cleanArgv, '');
        $subFolder = $parsed['relativeDir'] ? strtolower($parsed['relativeDir']) : '';
        
        // Target: resources/views/
        $targetDir = dirname(__DIR__, 4) . "/resources/views" . $subFolder;
        if (!is_dir($targetDir)) { mkdir($targetDir, 0755, true); }
        
        $filepath = "{$targetDir}/{$parsed['snakeName']}.twig";
        if (file_exists($filepath)) { exit("\e[31m[ERROR] Twig view template already exists at: {$filepath}\e[0m\n"); }

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
        echo "\e[32mCreated Twig View Template:\e[0m {$filepath}\n";
    }
}