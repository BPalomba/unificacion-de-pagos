<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Limpieza de informes vencidos para entornos donde corre el scheduler
// (servidor propio, `php artisan schedule:work`). En Vercel no hay
// scheduler: ahí la limpieza la hacen la app en cada uso y Vercel Cron.
Schedule::command('informes:purgar')->everyFifteenMinutes();
