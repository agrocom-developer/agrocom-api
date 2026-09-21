<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Aplicacion\Siembra\GuardarSiembra;
use App\Dominios\Comercial\Infraestructura\Eloquent\LoteCampania;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Guarda la siembra de una propiedad para UNA campaña (HU-48, tarea 71,
 * etapa 3; ADR 0020 — opera sobre `Propiedad` directo, un salto menos que
 * bajo ADR 0018): la ficha de la propiedad manda el set completo de filas
 * de esa campaña —una por lote, algunas vacías si ese lote no se sembró— y
 * esta clase decide, por lote, si crea, actualiza o da de baja la fila de
 * `com_lote_campania` correspondiente. Nunca toca las siembras de OTRA
 * campaña del mismo lote (prompt, punto 4: "cambiar de campaña en el
 * selector... no pisa la de la anterior") porque el `where` siempre incluye
 * `campania_id`.
 *
 * Sin guarda de cliente (ADR 0015, corregida el 15/9/2026): la campaña es un
 * catálogo compartido, sin `cliente_id` propio contra el cual comparar el de
 * la propiedad — cualquier lote puede sembrarse en cualquier campaña.
 */
final class GuardarSiembraCampania
{
    /**
     * @param  list<array{lote_id: int, cultivo_id: int|null, etapa_cultivo: string|null, hectareas_sembradas: string|null, fecha_siembra: string|null, fecha_cosecha_estimada: string|null}>  $filas
     */
    public function ejecutar(Propiedad $propiedad, int $campaniaId, array $filas): void
    {
        DB::transaction(function () use ($propiedad, $campaniaId, $filas): void {
            $lotesPorId = $propiedad->lotes->keyBy('id');

            $existentesPorLote = LoteCampania::query()
                ->where('campania_id', $campaniaId)
                ->whereIn('lote_id', $lotesPorId->keys())
                ->get()
                ->keyBy('lote_id');

            foreach ($filas as $fila) {
                $lote = $lotesPorId->get($fila['lote_id']);

                // Defensivo: una fila que no corresponde a un lote de esta
                // propiedad no puede llegar desde la ficha (los <select> del
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
                    'etapa_cultivo' => $fila['etapa_cultivo'],
                    'hectareas_sembradas' => (string) $fila['hectareas_sembradas'],
                    'fecha_siembra' => $fila['fecha_siembra'],
                    'fecha_cosecha_estimada' => $fila['fecha_cosecha_estimada'],
                ]);
            }
        });
    }

    /**
     * Fila quitada del formulario: la siembra se da de baja (soft delete),
     * nunca un DELETE físico (invariante 8) — mismo criterio que
     * `EliminarCultivo`.
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
