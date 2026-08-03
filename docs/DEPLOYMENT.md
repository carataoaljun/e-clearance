# Production deployment

This application is not safe to publish by copying the working directory into
a general web directory. Build a clean release, store secrets outside version
control, and point the web server at Laravel's `public` directory only.

## 1. Prepare the production environment

The supported baseline is PHP 8.3 or newer, Laravel 13, a production database,
the PHP extensions required by Composer, and a web server with a valid TLS
certificate. Disable Xdebug and PHP `display_errors` in production.

Copy `.env.production.example` to the production secret store or `.env` on the
server. Replace every placeholder. At minimum:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-real-hostname.example
FORCE_HTTPS=true
# The supplied web-server templates own HSTS for dynamic and static responses.
SECURITY_HSTS=false
LOG_LEVEL=warning

SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

Generate a unique production `APP_KEY`. Do not reuse a development key and do
not commit, email, or paste the resulting production `.env`. Use a dedicated,
least-privileged database user with a strong password.

Restrict `.env` so only the account running PHP and the deployment administrator
can read it. If any secret was previously committed or shared, remove it from
Git history and rotate it. Plan an `APP_KEY` rotation separately because values
encrypted with the old key may otherwise become unreadable.

## 2. Build a clean release

Run tests and dependency audits before creating the release:

```bash
composer validate --strict
composer audit --locked
composer test
npm audit
npm ci
npm run build
```

Install only runtime PHP dependencies in the production release:

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
```

Do not ship `node_modules`, development Composer packages, test caches, logs,
route dumps, editor backups, database dumps, test-account scripts, or files from
`storage/framework`. Keep `public/build` from the trusted build output; it is
intentional static web content and must be served from within `public`. Keep the
runtime `vendor` directory outside the document root and never expose it
directly.

Deploy from a clean version-controlled checkout or CI artifact. Do not upload
the entire local WAMP directory.

## 3. Configure the web server and HTTPS

Set the document root exactly to the release's `public` directory:

```text
/absolute/path/to/e-clearance/public
```

Templates are provided in:

- `deploy/apache/e-clearance.conf.example`
- `deploy/nginx/e-clearance.conf.example`

Replace the example domain, paths, TLS certificate, PHP handler, and log paths.
Enable the HTTP-to-HTTPS redirect and HSTS only on the HTTPS virtual host. The
templates make the web server the owner of HSTS, nosniff, and referrer policy so
those headers are not duplicated by PHP responses and are also present on
static assets. Test the certificate chain and renewal before launch. If a
reverse proxy terminates TLS, configure only its known IP addresses as trusted
proxies in Laravel and ensure it sends the forwarded protocol header.

The root `.htaccess` intentionally denies all access if Apache is accidentally
pointed at the project root. It is a last-resort safeguard, not an alternative
to a correct document root. The Apache template disables parent overrides and
allows overrides only inside `public`.

From the public hostname, confirm all of these are denied or not found:

```text
/.env
/composer.json
/vendor/composer/installed.json
/storage/logs/laravel.log
/app/
/database/
```

No mobile API or Flutter source is present in this repository, so no mobile
endpoint is exposed by this release. Before connecting a separately maintained
Flutter client, add an authenticated Laravel API using Sanctum, define the
minimum required token abilities, rate-limit it, and test record-level policies.
The release build must use an `https://` API origin. Never connect Flutter
directly to MySQL or place database credentials in the application.

## 4. Runtime directories and public files

Grant the PHP service account write access only where Laravel needs it:

```text
storage/
bootstrap/cache/
```

Do not grant the web-server account write access to application source,
configuration, or `.env`. Do not create `public/storage` for student documents
that require authorization. A storage link is appropriate only for deliberately
public assets.

## 5. Database, cache, and workers

Put the application into maintenance mode for a deployment that changes the
schema, then run:

```bash
php artisan migrate --force
php artisan optimize
```

Restart long-running queue workers after each release:

```bash
php artisan queue:restart
```

Run a supervised queue worker when `QUEUE_CONNECTION` is asynchronous. Configure
the operating system to invoke Laravel's scheduler every minute:

```cron
* * * * * cd /absolute/path/to/e-clearance && php artisan schedule:run >/dev/null 2>&1
```

On Windows, create an equivalent Task Scheduler job using explicit paths to
`php.exe` and `artisan`, with the project as its working directory. Use a
non-interactive service account and configure failure monitoring.

## 6. Backups

Configure `BACKUP_DISK` as off-host storage, enable backups, and perform a manual
backup before opening the site:

```bash
php artisan backup:system
php artisan schedule:list
```

The local disk does not satisfy the offsite requirement. See
`docs/BACKUP_AND_RESTORE.md` for destination, encryption, retention, scheduler,
and restore-test instructions.

## 7. Production preflight

Run the preflight after caching the final production configuration. Supply the
document root copied from the active virtual-host configuration:

```bash
php artisan security:preflight --document-root=/absolute/path/to/e-clearance/public
```

The command returns a failure exit code for unsafe environment, debug, URL,
key, session cookie, config-cache, backup, retention, database-backup, or
document-root settings. `MANUAL` lines are launch requirements that cannot be
proven safely from a CLI process, including certificate validity, public URL
exposure, scheduler execution, offsite ownership, and a real restore test.

Do not deploy while the command reports `FAIL` or while any `MANUAL` item is
unverified.

## 8. Launch and rollback

Before removing maintenance mode, record the deployed commit and retain the
previous release. Check `/up`, portal login pages, queue health, scheduled tasks,
logs, mail delivery, and one authorized document workflow. Keep database
rollback and backup restoration as explicit procedures; never assume a code
rollback can reverse a migrated database safely.
