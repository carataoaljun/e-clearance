<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('security.force_https') && ! $request->secure()) {
            $applicationUrl = rtrim((string) config('app.url'), '/');
            abort_unless(str_starts_with($applicationUrl, 'https://'), 500, 'APP_URL must use HTTPS when FORCE_HTTPS is enabled.');

            return redirect()->to($applicationUrl.$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
