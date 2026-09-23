<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Dominio\Excepciones\RendicionNoEditable;
use App\Dominios\Finanzas\Dominio\PoliticaEdicionRendicion;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Rendicion;
use Illuminate\Support\Facades\DB;

/**
 * Edición de la CABECERA de una rendición de campo (tarea 134, queja del
 * dueño del 22/9/2026: "casi nada de Finanzas se puede corregir después de
 * creado"). Solo toca `base_id`/`jefe_campo_id`/`fecha`/`descripcion` — nunca
 * `estado`, `monto` ni `aprobado_por` (invariante 7 de CLAUDE.md: esos son
 * responsabilidad exclusiva de `MaquinaEstadosRendicion`; editar no es una
 * transición).
 *
 * Se admite mientras la rendición sigue `Abierta` ({@see PoliticaEdicionRendicion}):
 * en cuanto pasa a `Presentada` ya congeló su `monto` sumando sus gastos y
 * quedó a la espera del encargado (invariante 2 — no se pisa lo que ya
 * significa algo para otro rol). Sin motivo obligatorio: a diferencia de
 * `ActualizarOrdenTrabajo`, acá no hay un estado intermedio "ya publicada
 * pero corregible" — o admite edición libre (`Abierta`) o no se toca.
 *
 * La fila se relee con lock (`lockForUpdate`) para no editar una rendición
 * que otro operador acaba de presentar, mismo criterio que `ActualizarGasto`.
 */
final class ActualizarRendicion
{
    /** @throws RendicionNoEditable si la rendición ya no está `Abierta`. */
    public function ejecutar(
        Rendicion $rendicion,
        int $baseId,
        int $jefeCampoId,
        string $fecha,
        ?string $descripcion,
    ): Rendicion {
        return DB::transaction(function () use ($rendicion, $baseId, $jefeCampoId, $fecha, $descripcion): Rendicion {
            $actual = Rendicion::query()->lockForUpdate()->findOrFail($rendicion->id);

            if (! PoliticaEdicionRendicion::admiteEdicion($actual->estado)) {
                throw RendicionNoEditable::porEstado($actual->id, $actual->estado->value);
            }

            $actual->fill([
                'base_id' => $baseId,
                'jefe_campo_id' => $jefeCampoId,
                'fecha' => $fecha,
                'descripcion' => $descripcion,
            ]);
            $actual->save();

            return $actual->refresh();
        });
    }
}
