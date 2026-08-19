<?php

namespace App\Enums\System;

/**
 * The commands that may be run from the admin panel, and nothing else.
 *
 * WHY A FIXED LIST RATHER THAN A TERMINAL. The live server is Webuzo shared
 * hosting with no shell, so `php artisan migrate` after a deploy had nowhere to
 * happen. The obvious fix — a text box that runs what you type — is a permanent
 * remote-code-execution hole behind a single password: one stolen session and
 * somebody owns the account, not merely the booking data. It would also survive
 * every future deploy, quietly, long after the afternoon it was needed.
 *
 * An enum cannot be typed into a form. The browser sends a case name, this
 * class turns it into a command, and a request naming anything not listed here
 * fails validation before it reaches Artisan. That is the whole security model
 * and it is worth more than any amount of input filtering on a free-text field.
 *
 * WHAT IS DELIBERATELY ABSENT:
 *
 *   migrate:fresh, migrate:rollback, db:wipe — one mis-click destroys the
 *   studio's bookings. Rolling back a migration on live is a decision to make
 *   with a database backup open, not a button.
 *
 *   tinker, and anything taking arbitrary arguments — that is the text box
 *   again wearing a different hat.
 *
 *   key:generate — rotating APP_KEY makes every encrypted setting, including
 *   the SSLCommerz credentials, permanently unreadable.
 *
 *   config:cache — useful, and it bakes the current .env into a file. Get it
 *   wrong on a host where you cannot clear it from a shell and the site is down
 *   with no way back in. optimize:clear is here; caching is not.
 *
 *   shunno:strip-phase-comments — rewrites source files. Source edits belong in
 *   git on a machine somebody can review the diff on.
 */
enum MaintenanceTask: string
{
    case ClearCaches   = 'clear-caches';
    case Migrate       = 'migrate';
    case MigrateStatus = 'migrate-status';
    case SeedRoles     = 'seed-roles';
    case StorageLink   = 'storage-link';
    case QueueRestart  = 'queue-restart';
    case QueueFailed   = 'queue-failed';
    case QueueRetry    = 'queue-retry';
    case RemindPayments = 'remind-payments';

    public function label(): string
    {
        return match ($this) {
            self::ClearCaches    => 'Clear all caches',
            self::Migrate        => 'Run pending migrations',
            self::MigrateStatus  => 'Check migration status',
            self::SeedRoles      => 'Re-sync roles and permissions',
            self::StorageLink    => 'Re-create the storage link',
            self::QueueRestart   => 'Restart the queue worker',
            self::QueueFailed    => 'List failed jobs',
            self::QueueRetry     => 'Retry all failed jobs',
            self::RemindPayments => 'Send payment reminders now',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ClearCaches => 'Config, routes, views, events and the application cache. Run this after '
                . 'changing anything in .env, or when a settings change has not shown up yet.',

            self::Migrate => 'Applies any database changes that came with the last upload. Safe to run '
                . 'twice — migrations already applied are skipped.',

            self::MigrateStatus => 'Lists every migration and whether it has run. Changes nothing. Start '
                . 'here if you are not sure whether an upload needs migrating.',

            self::SeedRoles => 'Rebuilds the permission list and reassigns it to Admin and Manager. Needed '
                . 'whenever a release adds a permission. Does not touch staff accounts or their passwords.',

            self::StorageLink => 'Rebuilds public/storage. Fix for uploaded workshop images returning 404 '
                . 'after a deploy that replaced the public folder.',

            self::QueueRestart => 'Tells the running worker to finish its job and exit, so the next one '
                . 'picks up the code you just uploaded. Queued email is not lost.',

            self::QueueFailed => 'Lists email and jobs that gave up after three attempts. Changes nothing.',

            self::QueueRetry => 'Pushes every failed job back onto the queue. Usually the right answer '
                . 'after fixing SMTP credentials.',

            self::RemindPayments => 'Runs the reminder sweep immediately instead of waiting for the hour. '
                . 'It deduplicates against the communications log, so nobody is emailed twice.',
        };
    }

    /**
     * The artisan command and its arguments.
     *
     * Arguments are fixed here rather than accepted from the request, which is
     * what keeps this a list of nine buttons rather than a shell with nine
     * shortcuts.
     *
     * --force on the two writing commands because Artisan refuses to run them
     * unattended in production otherwise, and there is no terminal here to
     * answer the confirmation prompt.
     *
     * @return array{0:string,1:array<string,mixed>}
     */
    public function command(): array
    {
        return match ($this) {
            self::ClearCaches    => ['optimize:clear', []],
            self::Migrate        => ['migrate', ['--force' => true]],
            self::MigrateStatus  => ['migrate:status', []],
            self::SeedRoles      => ['db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]],
            self::StorageLink    => ['storage:link', []],
            self::QueueRestart   => ['queue:restart', []],
            self::QueueFailed    => ['queue:failed', []],
            self::QueueRetry     => ['queue:retry', ['id' => ['all']]],
            self::RemindPayments => ['shunno:remind-payments', []],
        };
    }

    /**
     * Whether this one changes anything.
     *
     * Drives the confirmation dialog and the button colour. The read-only ones
     * are the ones somebody should feel free to press while working out what is
     * wrong, and making them look identical to a migration discourages exactly
     * the diagnosis that avoids running a migration blind.
     */
    public function isReadOnly(): bool
    {
        return in_array($this, [self::MigrateStatus, self::QueueFailed], true);
    }

    /**
     * Roughly how long before this is worth worrying about, in seconds. Used
     * for the browser's own timeout only — nothing here can extend PHP's.
     */
    public function timeout(): int
    {
        return $this === self::Migrate ? 120 : 60;
    }

    /** @return array<int,self> */
    public static function all(): array
    {
        return self::cases();
    }
}
