<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TreasurerAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('treasurer')->check()) {
            return redirect()->route('treasurer.login');
        }

        return $next($request);
    }
}
