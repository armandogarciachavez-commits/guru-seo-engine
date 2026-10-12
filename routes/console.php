<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Publicar artículos cuya fecha programada ya llegó (1/semana, etc.)
Schedule::command('articles:publish-scheduled')->dailyAt('08:00');

// Motor SEO: genera y publica contenido de proyectos con next_run_at vencido
Schedule::command('app:run-seo-scheduler')->dailyAt('08:30');
