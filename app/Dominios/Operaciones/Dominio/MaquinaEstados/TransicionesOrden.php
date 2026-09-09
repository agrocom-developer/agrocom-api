<?php

namespace App\Dominios\Operaciones\Dominio\MaquinaEstados;

use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;

/**
 * Tabla de transiciones permitidas para `ope_ordenes_aplicacion.estado`
 * (invariante 7 de CLAUDE.md; espec §5, HU-25, tarea 38). Reglas puras, sin
 * Eloquent ni `Illuminate\Database` (verificado por
 * `tests/Unit/ArquitecturaModulosTest.php`).
 *
 * `emitida` es el único estado de alta — lo fija
 * `Aplicacion/MaquinaEstados/MaquinaEstadosOrden::crear()` directamente,
 * nunca es destino de una transición (mismo criterio que `TransicionesContrato`
 * con `borrador`). Desde `emitida`, la única salida es `vigente` (con la
 * guarda de "única orden vigente por lote", ver `MaquinaEstadosOrden::activar()`).
 *
 * `consumida` y `vencida` NO aparecen como destino de ninguna transición acá
 * a propósito: no tienen ningún disparador de negocio definido todavía —
 * ninguna otra tarea los setea, no hay evento de dominio ni cierre de trabajo
 * que los dispare hoy (verificado: un `grep` de "consumida"/"vencida" en
 * `app/` solo devuelve el propio enum y sus docblocks). Agregarlos sin una
 * regla real que los dispare sería inventar comportamiento — queda para la
 * tarea futura que sí tenga esa regla de negocio.
 */
final class TransicionesOrden
{
    /** @var array<string, list<string>> */
    private const array PERMITIDAS = [
        'emitida' => ['vigente'],
        'vigente' => [],
        'consumida' => [],
        'vencida' => [],
    ];

    public static function permitida(EstadoOrdenAplicacion $desde, EstadoOrdenAplicacion $hacia): bool
    {
        return in_array($hacia->value, self::PERMITIDAS[$desde->value], true);
    }
}
