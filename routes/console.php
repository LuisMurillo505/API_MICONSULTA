<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tarea:ejecutar-controlador')
    ->dailyAt('07:45');

Schedule::command('tarea:ejecutar-correocita')
    ->dailyAt('07:45');

Schedule::command('tarea:ejecutar-verificarSuscripcion')
    ->dailyAt('19:00');
