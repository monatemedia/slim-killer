<?php

declare(strict_types=1);

namespace SlimKiller\SiteCheck;

use SlimKiller\Doctor\Report;
use SlimKiller\Doctor\Section;
use SlimKiller\SiteCheck\Rules\BuildRule;
use SlimKiller\SiteCheck\Rules\ContentRule;
use SlimKiller\SiteCheck\Rules\LayoutRule;
use SlimKiller\SiteCheck\Rules\PagesRule;
use SlimKiller\SiteCheck\Rules\SchemasRule;
use SlimKiller\SiteCheck\Rules\SearchRule;

/**
 * Checks a client site against the wiki's Astro site rules, the way `hammer doctor`
 * checks a host: one section for each group of rules, each line PASS, INFO, WARN or FAIL.
 */
final readonly class SiteChecker
{
    /** @param list<SiteRule> $rules */
    public function __construct(
        private array $rules,
    ) {}

    public static function standard(): self
    {
        return new self([new LayoutRule(), new ContentRule(), new SchemasRule(), new BuildRule(), new SearchRule(), new PagesRule()]);
    }

    public function check(Site $site): Report
    {
        return new Report(array_map(
            static fn(SiteRule $rule): Section => new Section($rule->heading(), $rule->check($site)),
            $this->rules,
        ));
    }
}
