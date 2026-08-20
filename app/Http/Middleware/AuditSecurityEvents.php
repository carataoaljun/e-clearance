<?php

namespace App\Http\Middleware;

use App\Support\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditSecurityEvents
{
    public function handle(Request $request, Closure $next): Response
    {
        $actor = AuditLogger::currentActor();
        $response = $next($request);

        if (! $actor || $request->isMethodSafe() || $response->getStatusCode() >= 400) {
            return $response;
        }

        $routeName = (string) optional($request->route())->getName();
        if ($routeName === '' || str_contains($routeName, 'login') || str_contains($routeName, 'logout')
            || str_contains($routeName, 'password-recovery') || str_contains($routeName, 'account-recovery')) {
            return $response;
        }

        $event = match (true) {
            str_contains($routeName, 'clearance') && preg_match('/approve|pending|status|bulk/', $routeName) => 'clearance.status_changed',
            str_contains($routeName, 'destroy'), str_contains($routeName, 'delete') => 'record.deleted',
            str_contains($routeName, 'chat') => 'chat.message_sent',
            str_contains($routeName, 'password') => 'account.password_changed',
            str_contains($routeName, 'account'), str_contains($routeName, 'profile') => 'account.updated',
            str_contains($routeName, 'import') => 'administrator.data_imported',
            default => 'application.state_changed',
        };

        $routeParameters = collect($request->route()?->parameters() ?? [])
            ->map(fn ($value) => is_object($value) && method_exists($value, 'getRouteKey') ? $value->getRouteKey() : $value)
            ->filter(fn ($value) => is_scalar($value) || $value === null)
            ->all();

        AuditLogger::record(
            $event,
            $actor['guard'],
            $actor['actor'],
            $routeName,
            $routeParameters ? implode('|', array_map('strval', $routeParameters)) : null,
            [
                'route' => $routeName,
                'method' => $request->method(),
                'status' => $request->input('status'),
                'student_id' => $request->input('student_id'),
                'subject_id' => $request->input('subject_id'),
                'office_role' => $request->input('office_role'),
                // Chat records who was written to, never the message body.
                'recipient' => $request->input('receiver_id'),
                'recipient_portal' => $request->input('partner_role'),
                'bulk_count' => is_array($request->input('student_ids'))
                    ? count($request->input('student_ids'))
                    : (is_array($request->input('items')) ? count($request->input('items')) : null),
            ],
        );

        return $response;
    }
}
