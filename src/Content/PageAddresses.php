<?php

declare(strict_types=1);

namespace SlimKiller\Content;

/**
 * Where each content page is on the website ("destinations/namibia" → "/destinations/namibia/"),
 * from the addresses.json the site's build writes: the site lists a pattern per content
 * folder in site/slim-killer.json, and its build checks every address exists.
 *
 * On a client's host the file sits beside the working copy (storage/site/addresses.json,
 * replaced by every fetch). For local development, where CONTENT_DIR points at a site's
 * content/ folder, it is read from that site's last build. No file means no addresses:
 * the panel then shows no link rather than guessing one.
 */
final class PageAddresses
{
    /** @var array<string, string>|null */
    private ?array $addresses = null;

    public function __construct(
        private readonly ContentLocation $location,
    ) {}

    /** The page's address on the website, starting with "/", or null when it is not known. */
    public function of(string $collection, string $slug): ?string
    {
        $this->addresses ??= $this->read();

        return $this->addresses[$collection . '/' . $slug] ?? null;
    }

    /** @return list<string> where the file is looked for, in order */
    public function files(): array
    {
        $site = dirname($this->location->contentDir);

        return [$site . '/addresses.json', $site . '/site/dist/_slim-killer/addresses.json'];
    }

    /** @return array<string, string> */
    private function read(): array
    {
        foreach ($this->files() as $file) {
            if (! is_file($file)) {
                continue;
            }
            $data = json_decode((string) file_get_contents($file), true);
            $addresses = [];
            foreach (is_array($data) ? $data : [] as $key => $address) {
                // A path on this website, nothing a link could be tricked with.
                if (is_string($key) && is_string($address) && preg_match('#^/(?!/)[^\s"<>\\\\]*$#', $address) === 1) {
                    $addresses[$key] = $address;
                }
            }

            return $addresses;
        }

        return [];
    }
}
