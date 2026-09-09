<?php

namespace App\Dominios\Operaciones\Aplicacion\MaquinaEstados;

use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\Excepciones\OrdenVigenteDuplicadaEnLote;
use App\Dominios\Operaciones\Dominio\Excepciones\TransicionOrdenNoPermitida;
use App\Dominios\Operaciones\Dominio\MaquinaEstados\TransicionesOrden;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use Illuminate\Database\QueryException;

/**
 * Única clase que crea/muta el `estado` de `ope_ordenes_aplicacion`
 * (invariante 7 de CLAUDE.md), mismo criterio que `MaquinaEstadosContrato`.
 *
 * La guarda de "una única orden vigente por lote" NO se duplica acá en PHP:
 * ya vive en el índice parcial de la base
 * (`ope_ordenes_aplicacion_lote_vigente_unico`, ver docblock de la
 * migración) — `activar()` solo atrapa la `QueryException` que esa
 * violación produce y la traduce a un error de dominio legible.
 */
final class MaquinaEstadosOrden
{
    /**
     * @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase.
     */
    public function crear(array $atributos): OrdenAplicacion
    {
        return OrdenAplicacion::create([...$atributos, 'estado' => EstadoOrdenAplicacion::Emitida]);
    }

    /**
     * `emitida → vigente` (HU-25, tarea 38). Sin guarda de datos adicional
     * (a diferencia de `MaquinaEstadosContrato::activar()`): la única regla
     * de negocio de esta transición es la unicidad por lote, y esa vive en
     * la base.
     *
     * @throws TransicionOrdenNoPermitida si `$orden` no está `emitida`.
     * @throws OrdenVigenteDuplicadaEnLote si el lote ya tiene otra orden vigente.
     */
    public function activar(OrdenAplicacion $orden): OrdenAplicacion
    {
        $desde = $orden->estado;
        $hasta = EstadoOrdenAplicacion::Vigente;

        if (! TransicionesOrden::permitida($desde, $hasta)) {
            throw TransicionOrdenNoPermitida::entre($desde, $hasta);
        }

        $orden->estado = $hasta;

        try {
            $orden->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicado($excepcion, (int) $orden->lote_id);
        }

        return $orden->refresh();
    }

    /**
     * Formato del mensaje distinto por driver: Postgres nombra el índice
     * (`ope_ordenes_aplicacion_lote_vigente_unico`); SQLite (motor de los
     * tests) nombra tabla.columna (`ope_ordenes_aplicacion.lote_id`) — mismo
     * criterio que `CrearDron::relanzarComoDuplicado`.
     *
     * @throws OrdenVigenteDuplicadaEnLote si la violación corresponde al índice de lote vigente.
     * @throws QueryException si la violación no es la contemplada.
     */
    private function relanzarComoDuplicado(QueryException $excepcion, int $loteId): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'ope_ordenes_aplicacion_lote_vigente_unico') || str_contains($mensaje, 'ope_ordenes_aplicacion.lote_id')) {
            throw OrdenVigenteDuplicadaEnLote::porLote($loteId);
        }

        throw $excepcion;
    }
}
