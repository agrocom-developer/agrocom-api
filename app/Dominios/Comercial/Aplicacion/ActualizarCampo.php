<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Aplicacion\Lote\GuardadoLote;
use App\Dominios\Comercial\Aplicacion\Lote\VerificadorHistorialLote;
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
 * El chequeo de historial vive en {@see VerificadorHistorialLote} (extraído
 * en la tarea 77 para que `EliminarLote`, la baja de un lote suelto, lo reuse
 * sin duplicarlo): vía `DB::table(...)->exists()`, no con los modelos
 * Eloquent `Trabajo`/`OrdenAplicacion` de `Operaciones` — ADR 0003 prohíbe
 * relaciones Eloquent cruzadas entre módulos, y ninguna de las dos tareas
 * tiene alcance para crear un contrato formal en `Operaciones/Contratos/`
 * (fuera de "Puede tocar" de ambos prompts). Es una lectura de solo
 * existencia, sin escritura ni acoplamiento de código; si el chequeo
 * creciera en complejidad, la siguiente tarea que sí pueda tocar
 * `Operaciones` debería promoverlo a un contrato propio.
 */
final class ActualizarCampo
{
    /**
     * @param  list<array{id: int|null, codigo: string, hectareas: string, geometria: array<string, mixed>|null, restricciones: string|null, desnivel: string|null, limpieza: string|null}>  $lotes
     *
     * @throws CampoDuplicado si el nombre ya pertenece a otro campo activo
     *                        de la misma propiedad.
     * @throws LoteDuplicado si el código de un lote ya pertenece a otro lote
     *                       activo del mismo campo.
     * @throws LoteConHistorialAsociado si un lote quitado del set enviado
     *                                  tiene órdenes o trabajos asociados.
     */
    public function ejecutar(Campo $campo, int $propiedadId, string $nombre, array $lotes): Campo
    {
        return DB::transaction(function () use ($campo, $propiedadId, $nombre, $lotes): Campo {
            $campo->propiedad_id = $propiedadId;
            $campo->nombre = $nombre;

            try {
                $campo->save();
            } catch (QueryException $excepcion) {
                $this->relanzarCampoComoDuplicado($excepcion, $nombre);
            }

            $this->sincronizarLotes($campo, $lotes);

            return $campo->refresh();
        });
    }

    /** @param  list<array{id: int|null, codigo: string, hectareas: string, geometria: array<string, mixed>|null, restricciones: string|null, desnivel: string|null, limpieza: string|null}>  $lotes */
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
            if (VerificadorHistorialLote::tiene($lote)) {
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

            GuardadoLote::guardar($lote, $datos);
        }
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
}
