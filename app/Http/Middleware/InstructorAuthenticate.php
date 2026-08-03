<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class InstructorAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('instructor')->check()) {
            return redirect()->route('instructor.login');
        }

        return $next($request);
    }
}
