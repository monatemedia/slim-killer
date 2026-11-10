<?php

declare(strict_types=1);

namespace SlimKiller\GitHub;

use RuntimeException;
use SlimKiller\Http\Response;

/** A GitHub answer the panel cannot use, described for the client. Only inside this namespace. */
final class GitHubFailure extends RuntimeException
{
    public function __construct(
        public readonly Response $response,
    ) {
        parent::__construct('GitHub answered ' . $response->status);
    }

    public function describe(): string
    {
        return match (true) {
            $this->response->status === 0 => 'Your website could not reach GitHub, where your pages are kept. Check the connection and press Save again.',
            $this->response->status === 401, $this->response->status === 403 => 'GitHub refused the control panel\'s key. Your changes are not saved. Ask Monate Media to renew the key.',
            $this->response->status === 404 => 'GitHub could not find your website\'s repository. Ask Monate Media to check the setup.',
            $this->response->status >= 500 => 'GitHub is having trouble right now. Your changes are not saved. Press Save again in a few minutes.',
            default => 'GitHub did not accept the save (' . $this->response->status . '). Your changes are not saved. Ask Monate Media to look into it.',
        };
    }
}
