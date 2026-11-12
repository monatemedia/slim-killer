<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SlimKiller\Publishing\Fetcher;
use SlimKiller\Publishing\WebhookSignature;
use SlimKiller\Support\AfterResponse;
use SlimKiller\Support\AppConfig;
use SlimKiller\Support\Clock;

/**
 * The webhook: the site's workflow says a build is ready. The host answers at once and
 * then fetches it (spike S5: about 70 s from save to live). It only rings the bell: the
 * host decides what to fetch, from GitHub, so a genuine call can do no more than start
 * a fetch that cron would have started anyway.
 */
final class BuildReadyAction
{
    public function __construct(
        private readonly AppConfig $config,
        private readonly Clock $clock,
        private readonly Fetcher $fetcher,
        private readonly AfterResponse $afterResponse,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($this->config->webhookSecret === '') {
            return $this->json($response, 404, ['error' => 'The webhook is not set up on this website.']);
        }

        $problem = WebhookSignature::problem(
            $this->config->webhookSecret,
            $request->getHeaderLine(WebhookSignature::TIMESTAMP_HEADER),
            $request->getHeaderLine(WebhookSignature::SIGNATURE_HEADER),
            (string) $request->getBody(),
            $this->clock->now()->getTimestamp(),
        );
        if ($problem !== null) {
            return $this->json($response, 401, ['error' => $problem]);
        }

        $fetcher = $this->fetcher;
        $this->afterResponse->run(static function () use ($fetcher): void {
            $result = $fetcher->fetch();
            if ($result->isFailed()) {
                error_log('Slim Killer webhook fetch: ' . $result->message);
            }
        });

        return $this->json($response, 202, ['status' => 'Fetching the build.']);
    }

    /** @param array<string, string> $data */
    private function json(ResponseInterface $response, int $status, array $data): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
