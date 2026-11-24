<?php

use Illuminate\Support\Facades\Schedule;

// Per Cronjob jede Minute: php artisan schedule:run (siehe docs/INSTALLATION.md)
Schedule::command('leonida:backup')->dailyAt('03:30');
