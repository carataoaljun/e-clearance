<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RegistrarAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('registrar')->check()) {
            return redirect()->route('registrar.login');
        }

        return $next($request);
    }
}
