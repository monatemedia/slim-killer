<?php

declare(strict_types=1);

namespace SlimKiller\Mail;

use SlimKiller\Support\AppConfig;
use Twig\Environment as Twig;

/**
 * Writes every email Slim Killer sends from one layout, so they all look the same:
 * templates/mail/layout.html.twig and its plain-text twin layout.txt.twig.
 *
 * A new email is two templates, mail/<name>.html.twig and mail/<name>.txt.twig, each
 * extending its layout and filling the "content" and "why" blocks. Both are always
 * sent, so an email app that shows no HTML still reads well.
 */
final readonly class LetterWriter
{
    public function __construct(
        private Twig $twig,
        private AppConfig $config,
    ) {}

    /** @param array<string, mixed> $data what the templates show */
    public function write(string $name, string $to, string $subject, array $data = []): Letter
    {
        $data = ['subject' => $subject, 'site_url' => $this->config->url, ...$data];

        return new Letter(
            $to,
            $this->config->mailFrom,
            $subject,
            $this->twig->render("mail/{$name}.txt.twig", $data),
            $this->twig->render("mail/{$name}.html.twig", $data),
        );
    }
}
