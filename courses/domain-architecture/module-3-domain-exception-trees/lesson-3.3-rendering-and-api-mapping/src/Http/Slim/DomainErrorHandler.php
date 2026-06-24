<?php

declare(strict_types=1);

namespace Bond\Http\Slim;

use Bond\Http\Problem\ProblemDetailsMapper;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * DomainErrorHandler — the thin Slim adapter at the EDGE.
 *
 * It matches Slim's error-handler signature, so it can be registered with
 * $errorMiddleware->setDefaultErrorHandler($handler). Its only logic is:
 *   1. ALWAYS log the full exception detail server-side (never sent to the client).
 *   2. Delegate the HTTP translation to ProblemDetailsMapper.
 *   3. Write the RFC 7807 JSON to a PSR-7 response.
 *
 * Notice the domain knows nothing about any of this — the mapping lives entirely at the
 * boundary, exactly where Rule E says it should.
 */
final class DomainErrorHandler
{
    /** @var list<string> Demo log sink; in production inject a PSR-3 LoggerInterface. */
    public array $log = [];

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly bool $debug = false,
    ) {}

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
    ): ResponseInterface {
        // 1. Full detail to the server log — including file, line, and the wrapped cause.
        $this->log[] = sprintf(
            '[%s] %s in %s:%d',
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
        );

        // 2. Translate type -> status + safe body. Debug only when Slim/APP_DEBUG says so.
        $problem = ProblemDetailsMapper::map($exception, $this->debug || $displayErrorDetails);

        // 3. Write the response.
        $response = $this->responseFactory->createResponse($problem->status);
        $response->getBody()->write($problem->toJson());

        return $response->withHeader('Content-Type', $problem->contentType());
    }
}
