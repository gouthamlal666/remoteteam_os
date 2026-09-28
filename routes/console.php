<?php

use Illuminate\Support\Facades\Schedule;

// Run `php artisan schedule:work` locally (or a cron calling schedule:run in production).
Schedule::command('reminders:send')->everyMinute()->withoutOverlapping();
