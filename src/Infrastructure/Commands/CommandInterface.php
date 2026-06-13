<?php
// app/Commands/CommandInterface.php
namespace App\Infrastructure\Commands;

interface CommandInterface {
    public function getName(): string;
    public function getDescription(): string;
    public function handle(array $argv): void;
}