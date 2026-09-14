<?php

use App\Dominios\Mantenimiento\Aplicacion\MaquinaEstados\MaquinaEstadosOrdenMantenimiento;
use App\Dominios\Mantenimiento\Dominio\Excepciones\DescripcionFinalRequerida;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\OrdenMantenimiento;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-89 (tarea 104): la guarda de `descripcion_final` vive en el dominio
 * (`MaquinaEstadosOrdenMantenimiento::cerrar()`), no solo en
 * `CerrarOrdenMantenimientoRequest` — se invoca la máquina de estados
 * directo, sin pasar por HTTP (el camino HTTP ya está cubierto en
 * OrdenesMantenimientoPanelTest.php de esta misma carpeta). La guarda corre
 * antes de tocar repuestos/stock, así que `$lineasRepuestos` va vacía a
 * propósito.
 */

uses(RefreshDatabase::class);

function ordenAbiertaParaCierreDeDominio(): OrdenMantenimiento
{
    $dron = Dron::query()->create(['identificador' => 'DRN-104']);

    return OrdenMantenimiento::query()->create([
        'equipo_tipo' => 'dron',
        'equipo_id' => $dron->id,
        'tipo' => 'preventivo',
        'descripcion' => 'Revisión de rutina',
        'estado' => 'abierta',
        'fecha_apertura' => now(),
    ]);
}

it('rechaza cerrar con descripción final vacía', function () {
    $orden = ordenAbiertaParaCierreDeDominio();

    app(MaquinaEstadosOrdenMantenimiento::class)->cerrar($orden, [], '');
})->throws(DescripcionFinalRequerida::class);

it('rechaza cerrar con descripción final de solo espacios', function () {
    $orden = ordenAbiertaParaCierreDeDominio();

    app(MaquinaEstadosOrdenMantenimiento::class)->cerrar($orden, [], '   ');
})->throws(DescripcionFinalRequerida::class);
