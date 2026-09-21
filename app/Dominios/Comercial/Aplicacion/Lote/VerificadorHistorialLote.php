<?php

namespace App\Dominios\Comercial\Aplicacion\Lote;

use App\Dominios\Comercial\Dominio\Excepciones\LoteConHistorialAsociado;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use Illuminate\Support\Facades\DB;

/**
 * Un lote con órdenes de aplicación o trabajos ejecutados en `Operaciones` no
 * se da de baja (tarea 77, HU-54; decisión original de la tarea 35, ver
 * {@see LoteConHistorialAsociado}).
 *
 * Colaborador compartido: `EliminarLote` (baja de un lote, ficha propia) lo
 * reusa sin duplicar el chequeo.
 *
 * Vía `DB::table(...)->exists()`, no los modelos Eloquent `Trabajo`/
 * `OrdenAplicacion` de `Operaciones`: ADR 0003 prohíbe relaciones Eloquent
 * cruzadas entre módulos, y esta tarea no tiene alcance para crear un
 * contrato formal en `Operaciones/Contratos/` (fuera de "Puede tocar" del
 * prompt). Lectura de solo existencia, sin escritura ni acoplamiento de
 * código.
 *
 * "Tiene órdenes" se resuelve por `ope_orden_lotes` (HU-92, tarea 107): el
 * lote de una orden ya no es `ope_ordenes_aplicacion.lote_id` (columna
 * eliminada, una orden puede cubrir varios lotes), sino una fila de esa
 * tabla de detalle.
 */
final class VerificadorHistorialLote
{
    public static function tiene(Lote $lote): bool
    {
        $tieneOrdenes = DB::table('ope_orden_lotes')
            ->where('lote_id', $lote->id)
            ->whereNull('deleted_at')
            ->exists();

        if ($tieneOrdenes) {
            return true;
        }

        return DB::table('ope_trabajos')
            ->where('lote_id', $lote->id)
            ->whereNull('deleted_at')
            ->exists();
    }
}
