<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PostLogout
{
    private const SESSION_KEY = 'security.redirect_history_to_landing';

    public static function response(Request $request, string $loginRoute): RedirectResponse
    {
        // The previous session was invalidated by the controller. Store this in
        // the new session so a history-triggered request is sent to the landing page.
        $request->session()->put(self::SESSION_KEY, true);

        $response = redirect()->route($loginRoute)
            ->with('status', 'You have been logged out successfully.');
        $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        $response->headers->set('Clear-Site-Data', '"cache"');

        return $response;
    }

    public static function guestRedirect(Request $request, string $loginRoute): RedirectResponse
    {
        if ((bool) $request->session()->get(self::SESSION_KEY, false)) {
            return redirect()->route('landing');
        }

        return redirect()->route($loginRoute);
    }

    public static function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
