<?php

declare(strict_types=1);

namespace SlimKiller\Support;

/**
 * Runs the work when PHP shuts down, after closing the connection where the server
 * allows it: fastcgi_finish_request() under PHP-FPM, litespeed_finish_request() under
 * LiteSpeed (Hostinger). Elsewhere the caller waits until the work is done, which is
 * slower but still correct.
 */
final class ShutdownAfterResponse implements AfterResponse
{
    public function run(callable $work): void
    {
        register_shutdown_function(static function () use ($work): void {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } elseif (function_exists('litespeed_finish_request')) {
                litespeed_finish_request();
            }
            ignore_user_abort(true);
            @set_time_limit(300);
            $work();
        });
    }
}
