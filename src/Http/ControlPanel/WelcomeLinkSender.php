<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel;

use Psr\Http\Message\ResponseInterface;
use SlimKiller\Auth\AccountMail;
use SlimKiller\Auth\User;
use SlimKiller\Auth\WelcomeLinks;
use SlimKiller\Support\AppConfig;

/**
 * After someone is added or reset on the People page: a new welcome link, emailed to
 * them, and shown once on the page to copy, in case the email doesn't arrive.
 */
final readonly class WelcomeLinkSender
{
    public function __construct(
        private WelcomeLinks $welcomeLinks,
        private AccountMail $mail,
        private AppConfig $config,
        private View $view,
    ) {}

    public function send(ResponseInterface $response, User $person, User $by, bool $reset): ResponseInterface
    {
        $link = $this->config->link(WelcomeLinks::pathFor($this->welcomeLinks->issue($person->id)));
        $emailed = $this->mail->welcomeLink($person, $by, $link, $reset);

        return $this->view->render($response, 'cp/person-link.twig', [
            'name' => $person->name,
            'email' => $person->email,
            'link' => $link,
            'emailed' => $emailed,
            'reset' => $reset,
            'hours' => intdiv(WelcomeLinks::LIFETIME_SECONDS, 3600),
        ]);
    }
}
