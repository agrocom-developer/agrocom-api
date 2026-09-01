<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosSesion;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosTrabajo;
use App\Dominios\Operaciones\Contratos\AperturaSesion;
use App\Dominios\Operaciones\Contratos\AperturaTrabajo;
use App\Dominios\Operaciones\Contratos\EscrituraSincronizacion;
use App\Dominios\Operaciones\Contratos\ResultadoSincronizacion;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Implementación Eloquent del contrato de escritura de `Operaciones` (ADR
 * 0003, regla 2). Vive fuera de `Infraestructura/Eloquent/` a propósito,
 * mismo criterio que `LecturaOrdenesVigentesEloquent`: esa subcarpeta está
 * reservada a modelos que extienden `ModeloDominio`
 * (`tests/Unit/ArquitecturaModulosTest.php` lo exige), y esta clase no es un
 * modelo — es el adaptador que el `ServiceProvider` del módulo liga a
 * {@see EscrituraSincronizacion}.
 *
 * La resolución de `sesion → trabajo` por `uuid_cliente` (espec §2.1, punto
 * 5) es una consulta interna del propio módulo (ADR 0003, regla 1: un módulo
 * lee y escribe sus propias tablas sin restricción entre sí) — el trabajo
 * referenciado, si vino en el mismo lote, ya quedó persistido:
 * `Sincronizacion\Aplicacion\SincronizarLote` aplica siempre `trabajo` antes
 * que `sesion`, sin importar el orden de llegada del arreglo del cliente.
 *
 * `duplicado` se resuelve capturando la violación del índice único parcial
 * (invariante 1 de CLAUDE.md) — nunca con un `SELECT` previo, que tendría
 * condición de carrera. Cualquier otra violación de la base (FK inexistente,
 * dato fuera de rango) se traduce a `rechazado`: un lote no se frena por un
 * registro individual inválido (espec §2.1, punto 3).
 */
final class EscrituraSincronizacionEloquent implements EscrituraSincronizacion
{
    public function __construct(
        private readonly MaquinaEstadosTrabajo $maquinaTrabajo,
        private readonly MaquinaEstadosSesion $maquinaSesion,
    ) {}

    /**
     * Antes de aplicar (tarea 12, hallazgo 2), verifica que `orden_id` y
     * `lote_id` formen un par legítimo: la orden debe existir, estar
     * `Vigente`, y su `lote_id` debe coincidir con el declarado. La espec
     * (§4.3: "el trabajo no lleva piloto propio — un lote puede tener varios
     * pilotos y drones por relevo, falla o logística") descarta que exista
     * una noción de "lote del operario"; lo único verificable con lo que hay
     * en el repo es que el par orden/lote sea consistente con el catálogo
     * vigente que cualquier operario legítimo puede operar — un `lote_id`
     * que no es el de esa orden (o una orden ya consumida/vencida/emitida
     * sin vigencia) no es algo que el operario pueda tocar, aunque ambos ids
     * existan físicamente y la FK los acepte.
     */
    public function abrirTrabajo(AperturaTrabajo $datos): ResultadoSincronizacion
    {
        $orden = OrdenAplicacion::query()->find($datos->ordenId);

        if ($orden === null || $orden->estado !== EstadoOrdenAplicacion::Vigente || (int) $orden->lote_id !== $datos->loteId) {
            return ResultadoSincronizacion::rechazado('la orden y el lote declarados no forman un par vigente');
        }

        try {
            DB::transaction(function () use ($datos): void {
                $this->maquinaTrabajo->abrir([
                    'uuid_cliente' => $datos->uuidCliente,
                    'orden_id' => $datos->ordenId,
                    'lote_id' => $datos->loteId,
                    'nro_aplicacion' => $datos->nroAplicacion,
                    'hectareas_declaradas' => $datos->hectareasDeclaradas,
                    'inicio' => $datos->inicio,
                    'fin' => $datos->fin,
                ]);
            });
        } catch (QueryException $excepcion) {
            return $this->resultadoDesdeExcepcion($excepcion, 'ope_trabajos');
        }

        return ResultadoSincronizacion::aplicado();
    }

    public function abrirSesion(AperturaSesion $datos): ResultadoSincronizacion
    {
        $trabajoId = Trabajo::query()->where('uuid_cliente', $datos->trabajoUuidCliente)->value('id');

        if ($trabajoId === null) {
            return ResultadoSincronizacion::rechazado('el trabajo referenciado no existe todavía');
        }

        try {
            DB::transaction(function () use ($datos, $trabajoId): void {
                $this->maquinaSesion->abrir([
                    'uuid_cliente' => $datos->uuidCliente,
                    'trabajo_id' => $trabajoId,
                    'secuencia' => $datos->secuencia,
                    'piloto_id' => $datos->pilotoId,
                    'auxiliar_id' => $datos->auxiliarId,
                    'hectareas_declaradas' => $datos->hectareasDeclaradas,
                    'inicio' => $datos->inicio,
                    'fin' => $datos->fin,
                ]);
            });
        } catch (QueryException $excepcion) {
            return $this->resultadoDesdeExcepcion($excepcion, 'ope_sesiones');
        }

        return ResultadoSincronizacion::aplicado();
    }

    /**
     * Mismo criterio que `AsignarRolesUsuario::relanzarComoDuplicado()`: el
     * formato del mensaje de la violación difiere por driver (Postgres nombra
     * el índice; SQLite, el motor de los tests, nombra tabla.columna) — se
     * comprueban ambos formatos.
     */
    private function resultadoDesdeExcepcion(QueryException $excepcion, string $tabla): ResultadoSincronizacion
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, "{$tabla}_uuid_cliente_unico") || str_contains($mensaje, "{$tabla}.uuid_cliente")) {
            return ResultadoSincronizacion::duplicado();
        }

        return ResultadoSincronizacion::rechazado('no se pudo aplicar el registro: referencia o dato inválido');
    }
}
