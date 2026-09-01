<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosSesion;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosTrabajo;
use App\Dominios\Operaciones\Contratos\AperturaSesion;
use App\Dominios\Operaciones\Contratos\AperturaTrabajo;
use App\Dominios\Operaciones\Contratos\EscrituraSincronizacion;
use App\Dominios\Operaciones\Contratos\ResultadoSincronizacion;
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

    public function abrirTrabajo(AperturaTrabajo $datos): ResultadoSincronizacion
    {
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
