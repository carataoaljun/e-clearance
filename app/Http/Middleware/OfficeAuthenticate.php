<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class OfficeAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('office')->check()) {
            return redirect()->route('office.login');
        }

        return $next($request);
    }
}
