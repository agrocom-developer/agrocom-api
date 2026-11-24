<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Vencimientos de documentos de proveedores (ADR 0032, punto 4): avisa 30 y 7
 * días antes, marca los vencidos y suspende a sus sujetos. Una vez al día, de
 * madrugada; el comando es idempotente.
 */
Schedule::command('proveedores:vencimientos')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer();
