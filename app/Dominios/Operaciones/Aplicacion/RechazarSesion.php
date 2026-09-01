<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\Excepciones\PilotoNoPuedeDecidirSuPropiaSesion;
use App\Dominios\Operaciones\Dominio\Excepciones\SesionNoDisponibleParaDecision;
use App\Dominios\Operaciones\Dominio\PoliticaValidacionSesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\SesionRechazo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Caso de uso "el jefe de campo rechaza una sesión cerrada, con motivo"
 * (HU-14, tarea 14) — la implementación real de la invariante 2 de
 * CLAUDE.md para esta HU. Diseño completo (por qué tabla nueva y no un
 * `UPDATE`, por qué `anulada_en` y no un `estado`) en runs/14.md.
 *
 * A propósito NO pasa por `Aplicacion/MaquinaEstados/MaquinaEstadosSesion`:
 * el rechazo no transiciona ningún `estado` (la fila original se queda
 * `cerrado` para siempre — ver `EstadoSesion::Validado`, docblock), así que
 * no hay nada que la máquina de estados deba guardar acá. Lo único que este
 * caso de uso escribe sobre `$sesion` es `anulada_en`, una marca, no un
 * `estado` — por eso `tests/Unit/TransicionesEstadoTest.php` (invariante 7)
 * no tiene por qué vigilar esta clase.
 */
final class RechazarSesion
{
    /**
     * @throws SesionNoDisponibleParaDecision si `$sesion` no está `cerrado` o ya fue anulada por un rechazo anterior.
     * @throws PilotoNoPuedeDecidirSuPropiaSesion si `$autorPersonaId` es el piloto de `$sesion`.
     */
    public function ejecutar(Sesion $sesion, string $motivo, int $autorPersonaId): SesionRechazo
    {
        if ($sesion->anulada_en !== null) {
            throw SesionNoDisponibleParaDecision::porYaAnulada($sesion->id);
        }

        if ($sesion->estado !== EstadoSesion::Cerrado) {
            throw SesionNoDisponibleParaDecision::porEstadoInvalido($sesion->id, $sesion->estado);
        }

        if (! PoliticaValidacionSesion::puedeDecidir((int) $sesion->piloto_id, $autorPersonaId)) {
            throw PilotoNoPuedeDecidirSuPropiaSesion::paraSesion($sesion->id);
        }

        return DB::transaction(function () use ($sesion, $motivo, $autorPersonaId): SesionRechazo {
            $rechazo = SesionRechazo::create([
                'anula_a_id' => $sesion->id,
                'motivo' => $motivo,
                'rechazado_por' => $autorPersonaId,
            ]);

            // Columna de marca, no de negocio (docblock de la migración):
            // ninguna otra propiedad de `$sesion` se toca acá.
            $sesion->anulada_en = CarbonImmutable::now();
            $sesion->save();

            return $rechazo;
        });
    }
}
