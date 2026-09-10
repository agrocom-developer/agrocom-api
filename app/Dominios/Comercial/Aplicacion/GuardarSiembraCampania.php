<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Campania\Contratos\LecturaCampania;
use App\Dominios\Comercial\Aplicacion\Siembra\GuardarSiembra;
use App\Dominios\Comercial\Dominio\Excepciones\CampaniaDeOtroCliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\LoteCampania;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Guarda la siembra de un campo para UNA campaña (HU-48, tarea 71, etapa 3):
 * la ficha del campo manda el set completo de filas de esa campaña —una por
 * lote, algunas vacías si ese lote no se sembró— y esta clase decide, por
 * lote, si crea, actualiza o da de baja la fila de `com_lote_campania`
 * correspondiente. Nunca toca las siembras de OTRA campaña del mismo lote
 * (prompt, punto 4: "cambiar de campaña en el selector... no pisa la de la
 * anterior") porque el `where` siempre incluye `campania_id`.
 *
 * Guarda central de esta tarea (prompt, punto 3): el lote tiene que
 * pertenecer a un campo del MISMO cliente que la campaña. Se verifica UNA
 * sola vez para el lote de la campaña elegida —todas las filas del
 * formulario son de lotes de este mismo `$campo`, así que alcanza con
 * comprobar `$campo` contra la campaña— vía {@see LecturaCampania} (ADR
 * 0003 regla 2, frontera de `Campania`), mismo criterio que
 * `CrearContrato::verificarCampania`.
 */
final class GuardarSiembraCampania
{
    public function __construct(private readonly LecturaCampania $lecturaCampania) {}

    /**
     * @param  list<array{lote_id: int, cultivo_id: int|null, hectareas_sembradas: string|null, fecha_siembra: string|null, fecha_cosecha_estimada: string|null}>  $filas
     *
     * @throws CampaniaDeOtroCliente si la campaña elegida no es del cliente dueño del campo.
     */
    public function ejecutar(Campo $campo, int $campaniaId, array $filas): void
    {
        $this->verificarCampania($campo, $campaniaId);

        DB::transaction(function () use ($campo, $campaniaId, $filas): void {
            $lotesPorId = $campo->lotes->keyBy('id');

            $existentesPorLote = LoteCampania::query()
                ->where('campania_id', $campaniaId)
                ->whereIn('lote_id', $lotesPorId->keys())
                ->get()
                ->keyBy('lote_id');

            foreach ($filas as $fila) {
                $lote = $lotesPorId->get($fila['lote_id']);

                // Defensivo: una fila que no corresponde a un lote de este
                // campo no puede llegar desde la ficha (los <select> del
                // formulario solo listan lotes propios), pero si llegara no
                // se procesa en silencio.
                if ($lote === null) {
                    continue;
                }

                $existente = $existentesPorLote->get($fila['lote_id']);

                if ($fila['cultivo_id'] === null) {
                    if ($existente !== null) {
                        $this->darDeBaja($existente);
                    }

                    continue;
                }

                $siembra = $existente ?? new LoteCampania([
                    'lote_id' => $lote->id,
                    'campania_id' => $campaniaId,
                ]);

                GuardarSiembra::guardar($siembra, $lote, [
                    'cultivo_id' => $fila['cultivo_id'],
                    'hectareas_sembradas' => (string) $fila['hectareas_sembradas'],
                    'fecha_siembra' => $fila['fecha_siembra'],
                    'fecha_cosecha_estimada' => $fila['fecha_cosecha_estimada'],
                ]);
            }
        });
    }

    /** @throws CampaniaDeOtroCliente si la campaña elegida no es del cliente dueño del campo. */
    private function verificarCampania(Campo $campo, int $campaniaId): void
    {
        $campania = $this->lecturaCampania->obtener($campaniaId);

        if ($campania === null) {
            return;
        }

        if ($campania->clienteId !== $campo->propiedad->cliente_id) {
            throw CampaniaDeOtroCliente::paraCampania($campania->codigo);
        }
    }

    /**
     * Fila quitada del formulario: la siembra se da de baja (soft delete),
     * nunca un DELETE físico (invariante 8) — mismo criterio que
     * `EliminarCultivo`/`ActualizarCampo::sincronizarLotes`.
     */
    private function darDeBaja(LoteCampania $siembra): void
    {
        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $siembra->updated_by = (int) $usuarioId;
            $siembra->save();
        }

        $siembra->delete();
    }
}
