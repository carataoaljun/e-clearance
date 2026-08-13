<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait ReportsClearanceRefusals
{
    /**
     * Refuse a clearance change in a way the reviewer actually sees.
     *
     * The clearance workspaces render neither $errors nor a validation summary, so a
     * bare ValidationException redirects back leaving the row unchanged and the page
     * silent, which reads as the button being broken. Flashing the reason drives the
     * shared feedback modal the success path already uses. Errors stay in the session
     * so anything reading them keeps working, and JSON callers still get a 422.
     */
    protected function refuseClearanceChange(Request $request, string $message): RedirectResponse
    {
        if ($request->expectsJson()) {
            throw ValidationException::withMessages(['status' => $message]);
        }

        return back()
            ->withErrors(['status' => $message])
            ->with('flash', [
                'type' => 'danger',
                'title' => 'Approval Blocked',
                'message' => $message,
            ]);
    }
}
