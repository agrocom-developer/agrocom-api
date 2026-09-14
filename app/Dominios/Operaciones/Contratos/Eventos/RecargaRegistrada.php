<?php

namespace App\Dominios\Operaciones\Contratos\Eventos;

/**
 * Evento de dominio (ADR 0003, regla 2; HU-87, tarea 102), mismo molde que
 * {@see SesionValidada}: DTO primitivo, nunca un modelo Eloquent, para que
 * el módulo oyente (`Mantenimiento`) no dependa de `Operaciones` más allá de
 * este identificador.
 *
 * `$bateriaSalienteId` es el mismo texto libre de
 * `RegistroRecarga::$bateriaSalienteId` — correlación blanda con
 * `man_baterias.identificador`, no una FK (`Operaciones` no conoce el
 * catálogo de baterías de `Mantenimiento`, ADR 0003 regla 3).
 *
 * Quien lo dispara es `EscrituraSincronizacionEloquent::registrarRecarga()`,
 * dentro del mismo `DB::transaction` que crea la fila de `ope_recargas` — un
 * reintento del mismo `uuid_cliente` nunca llega a disparar el evento porque
 * la `QueryException` interrumpe la transacción antes: la idempotencia de la
 * recarga alcanza gratis, sin guard propio en este evento.
 *
 * Oyente real desde HU-87 (tarea 102):
 * `Mantenimiento\Infraestructura\MantenimientoServiceProvider::boot()`
 * registra el listener que incrementa `ciclos_acumulados` — ver
 * `Mantenimiento\Aplicacion\IncrementarCiclosBateria`.
 */
final readonly class RecargaRegistrada
{
    public function __construct(public string $bateriaSalienteId) {}
}
