<?php

namespace App\Dominios\Mezclas\Infraestructura;

use App\Dominios\Mezclas\Contratos\EscrituraMezclas;
use App\Dominios\Mezclas\Contratos\RegistroMezcla;
use App\Dominios\Mezclas\Infraestructura\Eloquent\Mezcla;
use App\Dominios\Mezclas\Infraestructura\Eloquent\MezclaDetalle;
use App\Dominios\Mezclas\Infraestructura\Eloquent\Producto;
use App\Dominios\Operaciones\Contratos\LecturaTrabajos;
use App\Dominios\Operaciones\Contratos\ResultadoSincronizacion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Implementación Eloquent del contrato de escritura de `Mezclas` (ADR 0003,
 * regla 2; espec §7, HU-78, tarea 94, revierte CR-01).
 *
 * `trabajo_id` se resuelve por `Operaciones\Contratos\LecturaTrabajos` (no
 * por un `Trabajo::query()` directo, que sería un Eloquent cruzado prohibido
 * por ADR 0003 regla 2) — mismo dato que `recepcion_caldo` resuelve dentro
 * de su propio módulo, acá cruza la frontera.
 *
 * `duplicado` se resuelve capturando la violación del índice único parcial
 * de `mez_mezclas.uuid_cliente` (invariante 1) — nunca con un `SELECT`
 * previo. Cada producto de la lista se resuelve con `firstOrCreate()` por
 * `nombre` en `mez_productos` (catálogo abierto, ver docblock de la
 * migración): si dos sincronizaciones concurrentes intentan crear el MISMO
 * producto nuevo por primera vez, una de las dos transacciones completas
 * (cabecera + todos los detalles) se rechaza por la violación de unicidad de
 * `nombre` — un reintento posterior del mismo evento la encuentra ya creada
 * y aplica sin problema, mismo patrón de tolerancia a reintento que el resto
 * del motor de sync.
 */
final class EscrituraMezclasEloquent implements EscrituraMezclas
{
    public function __construct(
        private readonly LecturaTrabajos $trabajos,
    ) {}

    public function registrarMezcla(RegistroMezcla $datos): ResultadoSincronizacion
    {
        $trabajoId = $this->trabajos->idPorUuidCliente($datos->trabajoUuidCliente);

        if ($trabajoId === null) {
            return ResultadoSincronizacion::rechazado('el trabajo referenciado no existe todavía');
        }

        try {
            DB::transaction(function () use ($datos, $trabajoId): void {
                $mezcla = Mezcla::query()->create([
                    'uuid_cliente' => $datos->uuidCliente,
                    'trabajo_id' => $trabajoId,
                    'hora' => $datos->hora,
                ]);

                foreach ($datos->productos as $item) {
                    $producto = Producto::query()->firstOrCreate(['nombre' => $item->producto]);

                    MezclaDetalle::query()->create([
                        'mezcla_id' => $mezcla->id,
                        'producto_id' => $producto->id,
                        'cantidad' => $item->cantidad,
                        'unidad' => $item->unidad,
                    ]);
                }
            });
        } catch (QueryException $excepcion) {
            return $this->resultadoDesdeExcepcion($excepcion, $datos->uuidCliente);
        }

        return ResultadoSincronizacion::aplicado();
    }

    /**
     * Mismo criterio que `EscrituraSincronizacionEloquent::resultadoDesdeExcepcion()`:
     * el formato del mensaje de la violación difiere por driver (Postgres
     * nombra el índice; SQLite, el motor de los tests, nombra tabla.columna).
     */
    private function resultadoDesdeExcepcion(QueryException $excepcion, string $uuidCliente): ResultadoSincronizacion
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'mez_mezclas_uuid_cliente_unico') || str_contains($mensaje, 'mez_mezclas.uuid_cliente')) {
            return ResultadoSincronizacion::duplicado();
        }

        // Mismo motivo que `resultadoAperturaEstadiaDesdeExcepcion()`: si el
        // mensaje no matchea el patrón de `uuid_cliente` pero YA EXISTE una
        // fila con ese valor, es igual `duplicado` — diagnóstico DESPUÉS de
        // una violación real (p. ej. la de `mez_productos.nombre` en una
        // carrera entre dos productos nuevos concurrentes), no el mecanismo
        // primario de detectar el reintento.
        if (Mezcla::query()->where('uuid_cliente', $uuidCliente)->exists()) {
            return ResultadoSincronizacion::duplicado();
        }

        return ResultadoSincronizacion::rechazado('no se pudo aplicar el registro: referencia o dato inválido');
    }
}
