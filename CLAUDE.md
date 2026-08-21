# MCC e-Clearance

Laravel 13 / PHP 8.3 student clearance system for Madridejos Community College.
Runs locally under WAMP at `c:\wamp64\www\e-clearance`, deployed to
`https://mcceclearance.com` (Hostinger). An Android WebView wrapper lives in
`mobile/student-android`.

## Seven portals, seven guards

The central architectural fact: there is no single `User`. Each portal has its own
guard, model, table, auth controller, and authenticate middleware.

| Guard | Model | Table | Routes | Middleware |
|---|---|---|---|---|
| `student` | `StudentAccount` | `student_account` | `routes/portal.php` | `student.auth` |
| `registrar` | `Registrar` | `registrar` | `routes/portal.php` | `registrar.auth` |
| `office` | `AdminPersonnel` | `admin_personnel` | `routes/office_treasurer.php` | `office.auth` |
| `treasurer` | `Treasurer` | `treasurers` (inferred) | `routes/office_treasurer.php` | `treasurer.auth` |
| `instructor` | `Instructor` | `instructor_account` | `routes/instructor.php` | `instructor.auth` |
| `admin` | `MainAdmin` | `main_admin` | `routes/web.php` | `admin.auth` |
| `web` | `User` | `users` | (stock Laravel, largely unused) | — |

Most authenticated routes also carry `no.history` (`PreventBackHistory`, no-store
headers). Guards are declared in `config/auth.php`, aliases in `bootstrap/app.php`,
which also forces JSON error rendering for `api/*` and `notifications/api*`.

## New-device sign-in codes

Every portal emails a six-digit code the first time an account signs in from a
given browser. A correct password alone never opens a session.

- `App\Http\Controllers\Concerns\VerifiesNewDevices` is the whole flow. A portal's
  `AuthController` still owns its own credential check, then hands the account to
  `completeLogin()`; the trait supplies the three public route targets
  (`verifyLoginCode`/`resendLoginCode`/`cancelLoginCode`) and each portal declares
  only `deviceGuard()`, `devicePanel()`, `deviceLoginRoute()`, `deviceHomeRoute()`,
  `deviceAccount()`, and — where the login form is not keyed on `email` —
  `deviceErrorField()`.
- `App\Support\LoginChallenge` holds the code mechanics: session key
  `login_challenge_{guard}`, hashed code, 10-minute expiry, 5 attempts per code.
  The plain code is **never** rendered back into any HTTP response, in any
  environment — an earlier version echoed it onto the login page when running
  locally without real SMTP, which was a genuine leak: Laravel's own default
  mailer is `log` (`env('MAIL_MAILER', 'log')`), so any unconfigured environment
  silently fell back to it and the code became visible HTML to anyone loading
  the page, no email access required. A developer testing with the `log` mailer
  can already read the full rendered email in `storage/logs/laravel.log`.
- Brute-forcing the code is bounded two ways. The per-code `attempts` counter
  (5) protects one code, but resets to zero on every "Resend code", so alone it
  caps nothing across a longer attempt. `LoginChallengeLockout` (private to
  `LoginChallenge.php`) is the real ceiling: an account-level `RateLimiter` key,
  independent of resend, that accumulates wrong guesses across every code the
  account is issued (`login_security.otp_account_lockout_after`, default 8)
  before `send()` itself refuses to mail another code.
- `App\Support\TrustedDevice` remembers verified browsers in **one encrypted
  cookie**, not a table — deploys never run `artisan migrate`, and a table that
  never got created would mean a code on every login forever. Entries are keyed
  `guard:hash(accountId)` and bound to a fingerprint built from
  `DeviceFingerprint::describe()` (browser/platform/category, so a browser update
  is not a new device). Default trust window is 30 days.
- **Main Admin never banks a device** — `deviceTrustAllowed()` returns false there,
  so it re-verifies on every sign-in. Flipping that to true is the only change
  needed to make it behave like the rest.
- An account with no email on file cannot sign in at all: there is nowhere to send
  the code. That is deliberate, and audited as `authentication.mfa_unavailable`.
- Tests go through `Tests\TestCase::loginThroughDeviceCode()`, which posts the
  credentials and then swaps the challenge's `code_hash` for a code it knows. Any
  test that logs in over HTTP rather than `actingAs()` needs it.
- Route names are `{portal}.login.otp.{verify,resend,cancel}`, except Main Admin's,
  which are `login.otp.*`. `auth/portal-login.blade.php` derives them from
  `$recoveryPortal`; the student portal has its own login template and hardcodes
  them.

## Clearance domain

Two separate status tables — don't conflate them:

- **`office_clearance_status`** — one row per (student, office_role). Unique on
  `['student_id','office_role']`. `status` enum `Pending|Approved|Rejected`.
  Note `approver_id` is **NOT NULL** in the real migration.
- **`clearance_status`** — one row per (student, subject, instructor). The
  per-subject instructor sign-off.

`App\Support\ClearanceWorkflow` owns the rules:

- `OFFICE_ROLES` defines the nine offices and their order, ending at `registrar`.
- `normalizeOfficeRole()` — office role strings are stored inconsistently
  (`'Section Treasurer'`, `'section_treasurer'`, …). Always normalize; queries use
  `whereRaw("LOWER(TRIM(office_role)) = ?")`.
- `prerequisitesMet($student, $role)` — gates approval. `registrar` requires all
  eight earlier offices approved **and** every instructor clearance approved;
  `dean` requires the two treasurers plus instructors; `department treasurer`
  requires `section treasurer`.

Authorization is `StudentAccountPolicy` (`reviewSubject`/`reviewOffice`/
`reviewTreasury`/`reviewRegistrar`) delegating scope checks to
`App\Support\ClearanceAccess`.

**Asymmetry to remember:** setting a clearance back to `Pending` runs no
prerequisite check; approving does. So a record can be reverted but then refuse to
re-approve — that is the rule working, not a bug.

## Cross-role chat

`chat_messages` rows carry both a `sender_role`/`sender_id` and a
`receiver_role`/`receiver_id`, so a conversation can span any two portals. Four
staff portals talk to students: `instructor`, `office`, `treasurer`, `registrar`.

- `App\Support\ChatDirectory` is the single authorization gate — `permits($student,
  $role, $account|$id)` — plus the two contact lists (`staffContactsFor`,
  `studentContactsFor`). Scoping mirrors `ClearanceAccess` with one relaxation: a
  conversation does **not** require an existing `office_clearance_status` row, so a
  student can ask before submitting. Program heads are limited to their program;
  section treasurers to their exact program/year/section; instructors reach a
  student through `instructor_assignment` **or** `irregular_enrollment`.
- `App\Support\ChatThread` reads and writes one thread, marks it read on open, and
  creates the recipient notification. Partners are `['role' => …, 'id' => …]` pairs
  keyed as `role|id` (ids collide across portals, so the role is never optional).
- Staff portals share `App\Http\Controllers\StaffChatController`; each subclass only
  declares `guard()`, `view()`, `subheading()`. The UI is one component,
  `<x-portal.messenger>`, which pushes its own polling script.
- Keep SQL portable: production is MySQL, tests are SQLite. No `CONCAT`, and no
  multi-column `COUNT(DISTINCT a, b)`.

## Activity tracking

`security_audit_logs` was already being written by the `Login`/`Logout` listeners in
`AppServiceProvider`, the `AuditSecurityEvents` middleware (every non-safe request),
and `App\Support\LoginSecurity`. `App\Http\Controllers\ActivityLogController` is the
Main Admin read-only view over it at `mainAdmin/activity` (`activity.index`); it
guards on `Schema::hasTable()` because deploys never migrate. Device information is
derived from the stored `user_agent` by `App\Support\DeviceFingerprint` — there is no
device id column. `App\Support\PortalAccounts` resolves a (role, id) pair back to a
display name across the seven tables, one query per portal.

## Gotchas discovered the hard way

- **Deploys never run artisan.** `.github/workflows/deploy-hostinger.yml` fires on
  push to `main` and does `composer install` + `npm run build` + FTP sync to
  `/public_html/` — no `migrate`, no cache clear. `bootstrap/cache/` is gitignored,
  so any `config.php`/`routes-*.php` cached on the server is **frozen forever**
  until someone clears it over SSH. `deploy/hostinger-deploy.sh` (manual, SSH) does
  cache things. Never put a machine-specific absolute path in a config default.
- **Validation errors are invisible in the portals.** `layouts/portal.blade.php`
  renders neither `$errors` nor a summary, and
  `partials/action-feedback-modal.blade.php` only reads `session('flash')`,
  `login_success`, `success`, `status`. A bare `ValidationException` on a form POST
  therefore looks like the button did nothing. Use the
  `Concerns\ReportsClearanceRefusals` trait, or flash `['type','title','message']`.
  Bulk actions post JSON and surface errors through their own modal, so bulk and
  single-row paths can behave differently.
- **`@section('x', $maybeNull)` leaks an output buffer.** Blade's `startSection()`
  calls `ob_start()` when the value is null and waits for an `@endsection` that
  never comes, truncating the page. Several views do this with `full_name`
  (`office/*.blade.php`, `treasurer/*.blade.php` have no `??` fallback). Give test
  users a non-null label or PHPUnit flags the test risky.
- **There is no Tailwind in this project.** All three layouts load Bootstrap 5, and
  `main_admin_portal.css` styles `.pagination`/`.page-link` with `--bs-pagination-*`
  variables. Laravel's paginator nevertheless defaults to its *Tailwind* view, whose
  `sm:hidden` / `hidden sm:flex` blocks then both render and whose chevron
  `<svg class="w-5 h-5">` paints at full container width — a giant arrow between two
  sets of page links. `AppServiceProvider::boot()` now calls
  `Paginator::useBootstrapFive()`, so plain `->links()` is correct everywhere; do not
  pass a view name per call. The same trap applies to any Tailwind utility class
  copied in from documentation — none of them do anything here.
- **Uploads are private.** Student documents live in `storage/app/private`, served
  through controllers via `App\Support\SubmissionFileResponse`. Never move them into
  `public/` or behind the storage symlink. `App\Support\SecureUpload` does MIME and
  content validation.
- `app/Models/` contains five empty leftover directories (`Instractors`, `Offices`,
  `Registrar`, `Student`, `Treasures`) alongside the real `.php` models.

## Commands

```bash
php artisan test                          # 203 tests, all should pass
php artisan test --filter=SomeTest        # prefer this while iterating
npm run build                             # vite -> public/build
php artisan security:preflight --document-root=/path/to/public
```

No Pint or static analysis is configured. Tests build their own schema with
`Schema::create` in `setUp()` rather than running migrations — match that pattern
(see `tests/Feature/BulkClearanceStatusTest.php`).

## Conventions

- Controllers are namespaced per portal (`Http/Controllers/Registrar/…`,
  `Office/…`, `Treasurer/…`, `Student/…`, `Instructors/…`).
- Heavy use of the query builder (`DB::table`) over Eloquent in clearance code.
- Success feedback: `return back()->with('flash', ['type' => 'success', 'message' => …])`.
- Blade views are dense and often single-line; match the surrounding density.
