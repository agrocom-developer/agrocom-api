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

/*
 * Motor de asignación (ADR 0030 y 0038): cada minuto toma las solicitudes
 * publicadas que el oyente no alcanzó a tomar, vence las ofertas sin respuesta
 * y abre la ronda siguiente. Idempotente; sin solaparse consigo mismo.
 */
Schedule::command('asignacion:procesar')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

/*
 * Política de sanciones (ADR 0038): una vez al día, de madrugada, recalcula las
 * tasas y aplica las sanciones de calificación, aceptación y cancelación.
 */
Schedule::command('asignacion:evaluar-proveedores')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->onOneServer();

/*
 * Cierre del servicio (especificación §5.1, ADR 0039): cada hora completa por
 * conformidad tácita las solicitudes que llevan más del plazo en `ejecutada` sin
 * conformidad ni disputa, y una vez al día aplica la política de calificación a
 * los clientes con aviso vigente. Ambos son idempotentes.
 */
Schedule::command('solicitudes:completar-vencidas')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('solicitudes:evaluar-clientes')
    ->dailyAt('04:30')
    ->withoutOverlapping()
    ->onOneServer();
