<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Requires Laravel scheduler on the server (php artisan schedule:run every minute).
Schedule::command('loyalty:sync')->hourly()->withoutOverlapping();
