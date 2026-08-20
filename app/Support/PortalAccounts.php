<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * There is no single users table in this system — every portal has its own guard,
 * model and table. Anything that has to work across portals (the Main Admin
 * activity log, cross-role chat) needs one place that knows how to turn a
 * (role, account id) pair back into a display name.
 *
 * Every lookup is guarded with Schema::hasTable(), because deploys never run
 * artisan migrate and a given database may not have the newer tables at all.
 */
final class PortalAccounts
{
    /**
     * Keyed by guard name. `key` is the column holding the identifier the audit
     * log and chat tables store; `name` lists the columns that build a label.
     *
     * @var array<string, array{table: string, key: string, name: array<int, string>, label: string, icon: string}>
     */
    public const PORTALS = [
        'admin' => ['table' => 'main_admin', 'key' => 'id', 'name' => ['name'], 'label' => 'Main Admin', 'icon' => 'bi bi-shield-check'],
        'student' => ['table' => 'student_account', 'key' => 'student_id', 'name' => ['firstname', 'lastname'], 'label' => 'Student', 'icon' => 'bi bi-mortarboard'],
        'instructor' => ['table' => 'instructor_account', 'key' => 'instructor_id', 'name' => ['firstname', 'lastname'], 'label' => 'Instructor', 'icon' => 'bi bi-person-video3'],
        'office' => ['table' => 'admin_personnel', 'key' => 'personnel_id', 'name' => ['firstname', 'lastname'], 'label' => 'Office Personnel', 'icon' => 'bi bi-building'],
        'treasurer' => ['table' => 'treasurers', 'key' => 'treasurer_id', 'name' => ['firstname', 'lastname'], 'label' => 'Treasurer', 'icon' => 'bi bi-wallet2'],
        'registrar' => ['table' => 'registrar', 'key' => 'registrar_id', 'name' => ['firstname', 'lastname'], 'label' => 'Registrar', 'icon' => 'bi bi-building-check'],
        'web' => ['table' => 'users', 'key' => 'id', 'name' => ['name'], 'label' => 'System User', 'icon' => 'bi bi-person'],
    ];

    public static function label(?string $role): string
    {
        $role = trim((string) $role);

        return self::PORTALS[$role]['label'] ?? ($role === '' ? 'Unknown portal' : ucwords(str_replace(['_', '-'], ' ', $role)));
    }

    public static function icon(?string $role): string
    {
        return self::PORTALS[trim((string) $role)]['icon'] ?? 'bi bi-person-badge';
    }

    /**
     * Display names for a batch of identifiers within one portal, keyed by id.
     * One query per portal keeps the activity listing off the N+1 path.
     *
     * @param  iterable<int, string|int>  $identifiers
     * @return Collection<string, string>
     */
    public static function names(string $role, iterable $identifiers): Collection
    {
        $portal = self::PORTALS[$role] ?? null;
        $ids = collect($identifiers)->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (string) $id)->unique()->values();

        if ($portal === null || $ids->isEmpty() || ! Schema::hasTable($portal['table'])) {
            return collect();
        }

        $columns = array_values(array_filter(
            $portal['name'],
            fn (string $column) => Schema::hasColumn($portal['table'], $column),
        ));

        if ($columns === [] || ! Schema::hasColumn($portal['table'], $portal['key'])) {
            return collect();
        }

        return DB::table($portal['table'])
            ->whereIn($portal['key'], $ids->all())
            ->get(array_merge([$portal['key']], $columns))
            ->mapWithKeys(fn ($row) => [
                (string) $row->{$portal['key']} => self::composeName($row, $columns),
            ]);
    }

    /** Single-account convenience wrapper around names(). */
    public static function name(string $role, string|int|null $identifier): ?string
    {
        if ($identifier === null || $identifier === '') {
            return null;
        }

        return self::names($role, [$identifier])->get((string) $identifier);
    }

    /**
     * @param  array<int, string>  $columns
     */
    private static function composeName(object $row, array $columns): string
    {
        $parts = array_filter(array_map(
            fn (string $column) => trim((string) ($row->{$column} ?? '')),
            $columns,
        ));

        return $parts === [] ? '' : implode(' ', $parts);
    }
}
