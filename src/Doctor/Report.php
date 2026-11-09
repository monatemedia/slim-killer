<?php

declare(strict_types=1);

namespace SlimKiller\Doctor;

final readonly class Report
{
    /** @param list<Section> $sections */
    public function __construct(
        public array $sections,
    ) {}

    public function count(Status $status): int
    {
        $count = 0;
        foreach ($this->sections as $section) {
            foreach ($section->results as $result) {
                if ($result->status === $status) {
                    $count++;
                }
            }
        }

        return $count;
    }

    public function passed(): bool
    {
        return $this->count(Status::Fail) === 0;
    }
}
