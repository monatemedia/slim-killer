<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SlimKiller\Doctor\CheckResult;
use SlimKiller\Doctor\Doctor;
use SlimKiller\Doctor\Environment\Environment;
use SlimKiller\Doctor\Report;
use SlimKiller\Doctor\ReportStore;
use SlimKiller\Doctor\Section;
use SlimKiller\Doctor\Status;
use SlimKiller\Http\ControlPanel\View;
use SlimKiller\Setup\SetupLink;
use SlimKiller\Support\Ago;
use SlimKiller\Support\Clock;

/**
 * /cp/setup?token=...: the host report in a browser, for whoever ran the doctor.
 *
 * The website's own PHP is checked live, on every visit. Everything only the command
 * line can know (which PHP cron should use, whether cron runs, outgoing connections)
 * comes from the doctor's last saved report, with how long ago it ran.
 */
final class SetupAction
{
    public function __construct(
        private readonly View $view,
        private readonly SetupLink $setupLink,
        private readonly Doctor $doctor,
        private readonly Environment $website,
        private readonly ReportStore $reports,
        private readonly Clock $clock,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $token = $request->getQueryParams()['token'] ?? '';
        if (! is_string($token) || ! $this->setupLink->accepts($token)) {
            return $this->view->render($response, 'cp/waiting.twig', ['link_refused' => true], 403);
        }

        $sections = [$this->doctor->examinePhp($this->website)];

        $saved = $this->reports->latest();
        foreach ($saved === null ? [] : $saved->report->sections as $section) {
            if ($section->title !== Doctor::WEBSITE_NOT_CHECKED) {
                $sections[] = $section;
            }
        }

        $report = new Report($sections);

        return $this->view->render($response, 'cp/setup.twig', [
            'host' => $request->getUri()->getHost(),
            'token' => $token,
            'passed' => $report->count(Status::Pass),
            'warnings' => $report->count(Status::Warn),
            'failed' => $report->count(Status::Fail),
            'ready' => $report->passed(),
            'sections' => array_map($this->section(...), $sections),
            'checked_ago' => $saved === null
                ? null
                : Ago::seconds($this->clock->now()->getTimestamp() - $saved->checkedAt->getTimestamp()),
        ]);
    }

    /** @return array{title: string, rows: list<array<string, string>>} */
    private function section(Section $section): array
    {
        return [
            'title' => $section->title,
            'rows' => array_map(static fn(CheckResult $result): array => [
                'status' => $result->status->value,
                'label' => $result->status->label(),
                'title' => $result->title,
                'detail' => $result->detail,
                'hint' => $result->hint,
            ], $section->results),
        ];
    }
}
