# Backup and restore runbook

The `backup:system` command creates one ZIP archive containing an online
database backup, `storage/app/private`, and—while legacy/public uploads still
exist—`storage/app/public`. MySQL consistency limits are documented in the
restore section below. The command stages data under `storage/framework/backup-temp`,
uploads the completed archive to the configured filesystem disk, verifies its
size, and then applies age/count retention.

Temporary files are removed after both successful and failed attempts. The
command never invokes a command shell. MySQL arguments are passed as an array to
the process runner, and the database password is supplied only through the
child process environment.

## Requirements

- PHP ZIP extension.
- PHP SQLite3 extension when using SQLite.
- `mysqldump` or a compatible MariaDB dump binary when using MySQL/MariaDB.
- A Laravel filesystem disk backed by storage outside the application host.
- Enough temporary free space for the uncompressed database dump plus archive.

Laravel's example `s3` disk requires the compatible S3 Flysystem adapter to be
installed in the application. Use a provider IAM role where available, or a
complete key/secret pair from the production secret store. Alternatively,
define another supported remote disk. Never make the backup disk or prefix
web-accessible.

## Configuration

Set these values in the production secret store, not in Git:

```env
BACKUP_ENABLED=true
BACKUP_DISK=s3
BACKUP_PATH=e-clearance/system-backups
BACKUP_DATABASE_CONNECTION=mysql
BACKUP_MYSQLDUMP_BINARY=mysqldump
BACKUP_MYSQLDUMP_TIMEOUT=900
BACKUP_MYSQL_NO_TABLESPACES=true
BACKUP_INCLUDE_PUBLIC_STORAGE=true
BACKUP_ARCHIVE_PASSWORD=
BACKUP_RETENTION_DAYS=30
BACKUP_RETENTION_COUNT=30
BACKUP_SCHEDULE_TIME=02:00
BACKUP_SCHEDULE_TIMEZONE=Asia/Manila
BACKUP_OVERLAP_TIMEOUT=180
BACKUP_ON_ONE_SERVER=true
```

Use an absolute `BACKUP_MYSQLDUMP_BINARY` path when the program is not in the
service account's `PATH`. The database user needs permission to read all
application tables and the routines, triggers, and events included by the dump.

`BACKUP_ARCHIVE_PASSWORD` enables AES-256 entry encryption when supported by the
installed ZIP library. Store that password in a separate password manager or
secret store. If the storage provider already provides managed encryption, keep
its encryption key and recovery process independent from the web host.

At least one of `BACKUP_RETENTION_DAYS` or `BACKUP_RETENTION_COUNT` must be
positive. Retention runs only after the new archive has uploaded and passed its
size check. It prunes only filenames matching this application's configured
archive prefix and generated timestamp/random format, never the archive created
by the current run. Give the application a dedicated `BACKUP_PATH` as an
additional isolation boundary. A local backup can help development, but does
not count as offsite and causes the production preflight to fail.

## Manual backup and verification

After configuration changes, rebuild Laravel's config cache and run:

```bash
php artisan optimize
php artisan backup:system
php artisan security:preflight --document-root=/absolute/path/to/e-clearance/public
```

The success line reports only the disk, object path, file count, and byte size;
it never prints credentials. Confirm independently in the storage provider that
the object exists, is private, has the expected size, and is covered by provider
versioning or immutability where available.

## Scheduling and alerting

When `BACKUP_ENABLED=true`, `routes/console.php` schedules `backup:system` daily
at the configured local time and applies `withoutOverlapping`. With
`BACKUP_ON_ONE_SERVER=true`, Laravel uses the shared cache lock so only one
application node runs it.

The operating system must still invoke Laravel's scheduler every minute:

```cron
* * * * * cd /absolute/path/to/e-clearance && php artisan schedule:run >/dev/null 2>&1
```

Use `php artisan schedule:list` to confirm the next run. The scheduled event has
an explicit `onFailure` hook that writes a critical log entry, while the backup
command logs the underlying dump, archive, upload, verification, or retention
error. Connect critical production logs to an external alerting service. Do not
rely only on the outer `schedule:run` process exit status; Laravel can complete
that scheduler invocation after an individual scheduled command fails.

## Restore test cadence

Test a restore before launch, after schema/storage changes, and at least every
quarter. Always use an isolated host and database. Never test by overwriting the
live database or storage.

Record:

1. Archive object path, creation time, and checksum from the provider.
2. Person performing the restore and isolated destination.
3. Database and file restoration start/end times.
4. Migration status, record counts, login test, and authorized document test.
5. Any errors and the corrective action.

## Restore procedure

1. Put the isolated application in maintenance mode and stop its queue workers.
2. Download the selected archive over an authenticated channel.
3. Verify the provider checksum/size before extraction.
4. Extract it outside any web root. Supply the archive password interactively;
   never place it in a command line or script.
5. Inspect `backup-metadata.json`. Confirm the expected database driver,
   timestamp, file roots, and format version.
6. Restore the database and files as described below.
7. Apply correct ownership and least-privilege permissions.
8. Point an isolated release at the restored data, run `php artisan optimize`,
   and complete functional verification.
9. Securely remove temporary plaintext dumps and extracted archives.

### MySQL or MariaDB

The dump uses `--single-transaction`, which provides a consistent snapshot for
transactional tables such as InnoDB while no concurrent schema change is in
progress. Confirm all production tables use a transactional engine and do not
run migrations or other DDL during the backup. Quiesce writes or use a
provider-native snapshot if the database contains non-transactional tables.

Create an empty isolated database and import `database/database.sql`. Prompt for
the password rather than putting it in process arguments:

```bash
mysql --host=127.0.0.1 --port=3306 --user=restore_user --password isolated_e_clearance < database/database.sql
```

Review import errors, table counts, routines, triggers, and events. The restore
account should be temporary and limited to the isolated database.

### SQLite

With the isolated application stopped, replace its configured SQLite database
with `database/database.sqlite`. Preserve a copy of the previous isolated file,
apply service-account ownership, and start the application only after the copy
is complete.

### Application files

Restore the contents beneath:

```text
files/storage/app/private/
files/storage/app/public/
```

into the corresponding isolated `storage/app` directories. Do not restore a
public storage link for protected student documents. Check that expected files
exist and that an unauthenticated request cannot download them.

## Disaster-recovery notes

Keep the production `APP_KEY` and other encryption keys in a separate secret
manager with controlled recovery access; they are not embedded in the backup.
Maintain more than one recovery point and at least one copy outside the hosting
provider's failure domain. A successful backup job is not proof of recoverability
until an isolated restore has passed.
