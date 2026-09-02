<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Dominio\Excepciones\CampoDuplicado;
use App\Dominios\Comercial\Dominio\Excepciones\LoteConHistorialAsociado;
use App\Dominios\Comercial\Dominio\Excepciones\LoteDuplicado;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Edición de un campo con sus lotes en una sola operación (HU-24, tarea 35):
 * mismo criterio que `ActualizarCliente` — una sola transacción para campo y
 * lotes.
 *
 * El set de lotes recibido es el COMPLETO y definitivo, no un delta: los que
 * faltan respecto a los actuales se dan de baja (soft delete), los que
 * traen `id` se actualizan y los que no traen `id` se crean — mismo criterio
 * que `ActualizarCliente::sincronizarContactos`.
 *
 * Decisión de esta tarea (prompt, punto 1 — no estaba escrita en la
 * especificación): un lote que se quita del formulario NO se da de baja si
 * ya tiene una orden de aplicación o un trabajo ejecutado en `Operaciones`
 * — se rechaza la operación completa con un mensaje claro. Dejarlo
 * soft-deleted igual habría dejado historial de negocio real (trabajos
 * ejecutados, órdenes emitidas) colgando de un lote inaccesible desde el
 * panel; el `restrictOnDelete()` de la migración solo protege contra el
 * DELETE físico que esta capa nunca ejecuta, así que sin esta regla nada
 * más lo impediría.
 *
 * El chequeo se hace vía `DB::table(...)->exists()`, no con los modelos
 * Eloquent `Trabajo`/`OrdenAplicacion` de `Operaciones`: ADR 0003 prohíbe
 * relaciones Eloquent cruzadas entre módulos, y esta tarea no tiene alcance
 * para crear un contrato formal en `Operaciones/Contratos/` (ver
 * "Puede tocar" del prompt de la tarea 35 — no incluye ese módulo). Es una
 * lectura de solo existencia, sin escritura ni acoplamiento de código; si el
 * chequeo creciera en complejidad, la siguiente tarea que sí pueda tocar
 * `Operaciones` debería promoverlo a un contrato propio.
 */
final class ActualizarCampo
{
    /**
     * @param  list<array{id: int|null, codigo: string, hectareas: string, geometria: array<string, mixed>|null, restricciones: string|null}>  $lotes
     *
     * @throws CampoDuplicado si el nombre ya pertenece a otro campo activo
     *                        del mismo cliente.
     * @throws LoteDuplicado si el código de un lote ya pertenece a otro lote
     *                       activo del mismo campo.
     * @throws LoteConHistorialAsociado si un lote quitado del set enviado
     *                                  tiene órdenes o trabajos asociados.
     */
    public function ejecutar(Campo $campo, int $clienteId, string $nombre, ?string $ubicacion, array $lotes): Campo
    {
        return DB::transaction(function () use ($campo, $clienteId, $nombre, $ubicacion, $lotes): Campo {
            $campo->cliente_id = $clienteId;
            $campo->nombre = $nombre;
            $campo->ubicacion = $ubicacion;

            try {
                $campo->save();
            } catch (QueryException $excepcion) {
                $this->relanzarCampoComoDuplicado($excepcion, $nombre);
            }

            $this->sincronizarLotes($campo, $lotes);

            return $campo->refresh();
        });
    }

    /** @param  list<array{id: int|null, codigo: string, hectareas: string, geometria: array<string, mixed>|null, restricciones: string|null}>  $lotes */
    private function sincronizarLotes(Campo $campo, array $lotes): void
    {
        $idsEnviados = array_values(array_filter(array_column($lotes, 'id')));

        // `whereNotIn` con un array vacío no excluye nada (Laravel lo
        // resuelve como "verdadero para toda fila"): si ningún lote enviado
        // trae `id`, esto da de baja a todos los actuales — correcto, el set
        // enviado los reemplaza por completo.
        $aEliminar = $campo->lotes()->whereNotIn('id', $idsEnviados)->get();

        // Se valida TODO el lote a eliminar antes de borrar ninguno: si uno
        // solo tiene historial asociado, la transacción entera se aborta sin
        // dejar cambios parciales (ni el campo, ni los demás lotes).
        foreach ($aEliminar as $lote) {
            if ($this->tieneHistorialAsociado($lote)) {
                throw LoteConHistorialAsociado::paraLote($lote->codigo);
            }
        }

        foreach ($aEliminar as $lote) {
            $usuarioId = Auth::id();

            if ($usuarioId !== null) {
                $lote->updated_by = (int) $usuarioId;
                $lote->save();
            }

            $lote->delete();
        }

        foreach ($lotes as $datos) {
            $id = $datos['id'];
            unset($datos['id']);

            $lote = $id !== null
                ? $campo->lotes()->whereKey($id)->firstOrFail()
                : new Lote(['campo_id' => $campo->id]);

            $lote->fill($datos);

            try {
                $lote->save();
            } catch (QueryException $excepcion) {
                $this->relanzarLoteComoDuplicado($excepcion, $datos['codigo']);
            }
        }
    }

    /** @see self por qué esto no usa los modelos Eloquent de `Operaciones`. */
    private function tieneHistorialAsociado(Lote $lote): bool
    {
        $tieneOrdenes = DB::table('ope_ordenes_aplicacion')
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

    /**
     * @throws CampoDuplicado si la violación corresponde al nombre.
     * @throws QueryException si la violación no es la contemplada.
     */
    private function relanzarCampoComoDuplicado(QueryException $excepcion, string $nombre): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'com_campos_nombre_unico') || str_contains($mensaje, 'com_campos.nombre')) {
            throw CampoDuplicado::porNombre($nombre);
        }

        throw $excepcion;
    }

    /**
     * @throws LoteDuplicado si la violación corresponde al código.
     * @throws QueryException si la violación no es la contemplada.
     */
    private function relanzarLoteComoDuplicado(QueryException $excepcion, string $codigo): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'com_lotes_codigo_unico') || str_contains($mensaje, 'com_lotes.codigo')) {
            throw LoteDuplicado::porCodigo($codigo);
        }

        throw $excepcion;
    }
}
