<?php

declare(strict_types=1);

namespace SlimKiller\Content;

/**
 * A Markdown file taken apart into the pieces a save must put back exactly: an optional
 * byte-order mark, the line endings, the front matter's YAML text, and everything after
 * the closing --- line (its line break, then the body).
 */
final readonly class ContentFile
{
    private const string BOM = "\xEF\xBB\xBF";

    private function __construct(
        public string $bom,
        public string $eol,
        public string $yaml,
        private string $rest,
    ) {}

    public static function parse(string $raw): self
    {
        $bom = str_starts_with($raw, self::BOM) ? self::BOM : '';
        $text = substr($raw, strlen($bom));
        $eol = str_contains($text, "\r\n") ? "\r\n" : "\n";
        $text = str_replace("\r\n", "\n", $text);

        if (preg_match('/^---\n(.*?)\n---(\n|$)/s', $text, $match) !== 1) {
            return new self($bom, $eol, '', "\n" . $text);
        }

        return new self($bom, $eol, $match[1], $match[2] . substr($text, strlen($match[0])));
    }

    public function hasFrontMatter(): bool
    {
        return $this->yaml !== '';
    }

    /** The Markdown below the front matter. */
    public function body(): string
    {
        return str_starts_with($this->rest, "\n") ? substr($this->rest, 1) : $this->rest;
    }

    /** The file again, with new front matter and body, in the file's own encoding. */
    public function with(string $yaml, string $body): string
    {
        $separator = $this->rest === '' && $body === '' ? '' : "\n";

        return $this->bom . str_replace("\n", $this->eol, "---\n{$yaml}\n---{$separator}{$body}");
    }
}
