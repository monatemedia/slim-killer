<?php

declare(strict_types=1);

namespace SlimKiller\Doctor;

use DateTimeImmutable;
use Exception;
use SlimKiller\Support\Clock;
use SlimKiller\Support\Paths;

/**
 * Keeps the doctor's last report in the storage folder, so the control panel's setup
 * page can show it. Some checks only make sense from the command line (which PHP cron
 * should use, whether cron runs), and the website cannot run those itself.
 */
final class ReportStore
{
    public const string FILE = 'doctor-report.json';

    public function __construct(
        private readonly Paths $paths,
        private readonly Clock $clock,
    ) {}

    /** Returns false when the report could not be written. */
    public function save(Report $report): bool
    {
        $file = $this->paths->storage(self::FILE);
        if (! is_dir(dirname($file))) {
            @mkdir(dirname($file), 0755, true);
        }

        $data = [
            'checked_at' => $this->clock->now()->format(DATE_ATOM),
            'sections' => array_map(static fn(Section $section): array => [
                'title' => $section->title,
                'results' => array_map(static fn(CheckResult $result): array => [
                    'status' => $result->status->value,
                    'title' => $result->title,
                    'detail' => $result->detail,
                    'hint' => $result->hint,
                ], $section->results),
            ], $report->sections),
        ];

        return @file_put_contents($file, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) !== false;
    }

    /** The last saved report, or null when there is none or it cannot be read. */
    public function latest(): ?SavedReport
    {
        $file = $this->paths->storage(self::FILE);
        if (! is_file($file)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);
        if (! is_array($data)) {
            return null;
        }

        $checkedAt = $data['checked_at'] ?? null;
        $sectionsData = $data['sections'] ?? null;
        if (! is_string($checkedAt) || ! is_array($sectionsData)) {
            return null;
        }

        try {
            $at = new DateTimeImmutable($checkedAt);
        } catch (Exception) {
            return null;
        }

        $sections = [];
        foreach ($sectionsData as $sectionData) {
            $section = is_array($sectionData) ? $this->section($sectionData) : null;
            if ($section === null) {
                return null;
            }
            $sections[] = $section;
        }

        return new SavedReport(new Report($sections), $at);
    }

    /** @param array<mixed> $data */
    private function section(array $data): ?Section
    {
        $title = $data['title'] ?? null;
        $resultsData = $data['results'] ?? null;
        if (! is_string($title) || ! is_array($resultsData)) {
            return null;
        }

        $results = [];
        foreach ($resultsData as $resultData) {
            $result = is_array($resultData) ? $this->result($resultData) : null;
            if ($result === null) {
                return null;
            }
            $results[] = $result;
        }

        return new Section($title, $results);
    }

    /** @param array<mixed> $data */
    private function result(array $data): ?CheckResult
    {
        $status = $data['status'] ?? null;
        $title = $data['title'] ?? null;
        $detail = $data['detail'] ?? '';
        $hint = $data['hint'] ?? '';

        if (! is_string($status) || ! is_string($title) || ! is_string($detail) || ! is_string($hint)) {
            return null;
        }

        $status = Status::tryFrom($status);

        return $status === null ? null : new CheckResult($status, $title, $detail, $hint);
    }
}
