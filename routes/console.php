<?php

use Illuminate\Support\Facades\Schedule;

// Automatic backups are disabled when running in a Docker container
// it makes more sense to do the backup directly on the host machine.
if (getenv('BACKUP_DISABLED') === false) {
    Schedule::commandWithDynamicInterval(
        'backup:run --only-db',
        interval: config('backup.backup.schedule_interval'),
        intervalArgs: config('backup.backup.schedule_interval_args')
    );
    // Alte Backups löschen: nur die letzten 7 Tage behalten (config/backup.php → cleanup).
    // Uhrzeit in App-Zeitzone UTC, also nach dem täglichen backup:run (00:00 UTC).
    Schedule::command('backup:clean')->dailyAt('03:00');
}

Schedule::command('ai:models:check-status')->everyFifteenMinutes();
Schedule::command('ai:tools:check-status ')->everyFifteenMinutes();
Schedule::command('filestorage:cleanup')->daily();
