<?php

declare(strict_types=1);

use SlimKiller\Cron\JobFailed;
use SlimKiller\Cron\Jobs\VisitReportJob;
use SlimKiller\Cron\Jobs\VisitsJob;
use SlimKiller\Mail\LetterWriter;
use SlimKiller\Support\AppConfig;
use SlimKiller\Visits\SiteTime;
use SlimKiller\Visits\VisitCounter;
use SlimKiller\Visits\VisitLog;
use SlimKiller\Visits\VisitorCodes;
use SlimKiller\Visits\VisitReport;
use Tests\Fakes\FakeMailer;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

function mailTwig(string $siteName): Environment
{
    $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 3) . '/templates'), [
        'strict_variables' => true,
        'autoescape' => 'name',
    ]);
    $twig->addGlobal('site_name', $siteName);

    return $twig;
}

/** @return array{VisitReportJob, FakeMailer, VisitCounter, PDO} */
function visitReportJob(string $to = 'owner@example.com', string $from = 'website@example.com', bool $accepts = true): array
{
    $pdo = migratedDatabase();
    $log = new VisitLog($pdo);
    $codes = new VisitorCodes($pdo);
    $time = SiteTime::named('Africa/Johannesburg');
    $config = new AppConfig('https://example.com', 'Thabo & Sons <Plumbing>', timezone: 'Africa/Johannesburg', mailFrom: $from, visitReportTo: $to);
    $mailer = new FakeMailer($accepts);
    $job = new VisitReportJob($pdo, new VisitReport($log), new LetterWriter(mailTwig($config->name), $config), $mailer, $time, $config);

    // Two visitors on Saturday 21 November, one from Google; one visitor the Saturday before.
    $counter = new VisitCounter($log, $codes, $time);
    $send = static function (array $data, array $server, string $at) use ($counter): void {
        $counter->count(
            ['REQUEST_METHOD' => 'POST', 'HTTP_HOST' => 'example.com', 'HTTP_ORIGIN' => 'https://example.com', 'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone) Mobile Safari', 'REMOTE_ADDR' => '198.51.100.7', ...$server],
            (string) json_encode(['k' => 'page', 'p' => '/', ...$data]),
            new DateTimeImmutable($at),
        );
    };
    $send([], [], '2026-11-14T08:00:00Z');
    $send(['r' => 'https://www.google.com/'], [], '2026-11-21T08:00:00Z');
    $send(['k' => 'tap', 'l' => 'WhatsApp: WhatsApp us'], [], '2026-11-21T08:01:00Z');
    $send(['p' => '/services/'], ['REMOTE_ADDR' => '203.0.113.9'], '2026-11-21T09:00:00Z');
    (new VisitsJob($pdo, $log, $codes, $time, $config))->run(new DateTimeImmutable('2026-11-22T00:30:00Z'));

    return [$job, $mailer, $counter, $pdo];
}

it('emails yesterday next to the week before, once, from the shared layout', function (): void {
    [$job, $mailer] = visitReportJob();

    // 06:30 in Johannesburg on Sunday 22 November.
    expect($job->run(new DateTimeImmutable('2026-11-22T04:30:00Z')))->toBe('Sent the visit report for 2026-11-21 to owner@example.com.')
        ->and($job->run(new DateTimeImmutable('2026-11-22T05:30:00Z')))->toBe('Already sent the visit report for 2026-11-21.')
        ->and($mailer->sent)->toHaveCount(1);

    $letter = $mailer->sent[0];
    expect($letter->to)->toBe('owner@example.com')
        ->and($letter->from)->toBe('website@example.com')
        ->and($letter->subject)->toBe('Visits to Thabo & Sons <Plumbing>, 21 November: 2 visitors')
        ->and($letter->text)->toContain('Thabo & Sons <Plumbing>')
        ->and($letter->text)->toContain('Visits on Saturday 21 November 2026')
        ->and($letter->text)->toMatch('/Visitors\s+2\s+0\.1/')
        ->and($letter->text)->toContain('google / search')
        ->and($letter->text)->toContain('WhatsApp: WhatsApp us')
        ->and($letter->text)->toContain('https://example.com')
        ->and($letter->text)->not->toContain('&amp;')
        ->and($letter->html)->toContain('Thabo &amp; Sons &lt;Plumbing&gt;')
        ->and($letter->html)->toContain('Visits on Saturday 21 November 2026')
        ->and($letter->html)->toContain('google / search')
        ->and($letter->html)->toContain('VISIT_REPORT_TO');
});

it('waits until six in the morning, site time', function (): void {
    [$job, $mailer] = visitReportJob();

    expect($job->run(new DateTimeImmutable('2026-11-22T03:30:00Z')))->toBe('The visit report goes after 06:00.')
        ->and($mailer->sent)->toBe([]);
});

it('sends nothing until both addresses are set', function (string $to, string $from, string $said): void {
    [$job, $mailer] = visitReportJob($to, $from);

    expect($job->run(new DateTimeImmutable('2026-11-22T04:30:00Z')))->toBe($said)
        ->and($mailer->sent)->toBe([]);
})->with([
    'no reader' => ['', 'website@example.com', 'No visit report: nobody chose it in Preferences, and VISIT_REPORT_TO is empty.'],
    'no sender' => ['owner@example.com', '', 'No visit report: MAIL_FROM is empty.'],
]);

it('tries again next time when the host does not send it', function (): void {
    [$job, , , $pdo] = visitReportJob(accepts: false);

    expect(fn() => $job->run(new DateTimeImmutable('2026-11-22T04:30:00Z')))->toThrow(JobFailed::class)
        ->and($pdo->query('SELECT COUNT(*) FROM visit_reports')->fetchColumn())->toBe(0);
});

it('says so on a day nobody was counted', function (): void {
    [$job, $mailer] = visitReportJob();

    $job->run(new DateTimeImmutable('2026-11-24T04:30:00Z')); // for Monday 23 November

    expect($mailer->sent[0]->subject)->toEndWith(': 0 visitors')
        ->and($mailer->sent[0]->text)->toContain('Nobody was counted that day.');
});
