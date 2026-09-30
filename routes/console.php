<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('subscriptions:notify-expiring-trials')->daily();
Schedule::command('subscriptions:expire-trials')->daily();
Schedule::command('tenants:migrate')->weeklyOn(1, '02:00');
