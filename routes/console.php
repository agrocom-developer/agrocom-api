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
 * Negociación (`negociacion-contrato.md` §1): cada minuto vence las ofertas sin respuesta, reevalúa
 * las solicitudes que se quedaron sin ofertas (la solicitud no vence). Idempotente;
 * sin solaparse consigo mismo. Cada hora avisa a las partes de un servicio en ejecución sin avance.
 */
Schedule::command('negociacion:procesar')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('seguimiento:avisar-inactividad')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

/*
 * Política de calificación del cliente (ADR 0039): una vez al día aplica el aviso y la limitación
 * a los clientes con promedio bajo. Idempotente.
 */
Schedule::command('solicitudes:evaluar-clientes')
    ->dailyAt('04:30')
    ->withoutOverlapping()
    ->onOneServer();

/*
 * Eliminación de cuentas (ADR 0047, punto 5): una vez al día ejecuta las solicitudes cuya gracia de 7 días venció.
 * Idempotente; sin solaparse consigo mismo.
 */
Schedule::command('cuentas:ejecutar-eliminaciones')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer();
