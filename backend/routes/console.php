<?php

use Illuminate\Support\Facades\Schedule;

// Per Cronjob jede Minute: php artisan schedule:run (siehe docs/INSTALLATION.md)
// Ausgabe (auch Fehler) landet in storage/logs/backup.log
Schedule::command('leonida:backup')->dailyAt('03:30')->appendOutputTo(storage_path('logs/backup.log'));
