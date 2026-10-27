<?php

namespace App\Http\Middleware;

use App\Support\DemoMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Demo only: tidies old visitor orders after the response has been sent, and asks search engines to
 *  index the portfolio instead of this demo copy (link previews when sharing still work). */
class DemoCleanup
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (DemoMode::enabled()) {
            $response->headers->set('X-Robots-Tag', 'noindex');
        }

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        DemoMode::cleanUp();
    }
}
