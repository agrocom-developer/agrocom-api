<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Personal\Infraestructura\Eloquent\EquipoIntegrante;

/**
 * Finaliza la vigencia de un integrante (tarea 72, HU-49): fija `hasta`,
 * nunca borra la fila. Es lo que preserva la historia — un gasto de marzo
 * tiene que poder seguir atribuyéndose a quien integraba el equipo en marzo,
 * aunque hoy ya no lo integre (ADR 0015 punto 3).
 */
final class DesasignarIntegranteEquipo
{
    public function ejecutar(EquipoIntegrante $integrante, string $hasta): EquipoIntegrante
    {
        $integrante->fill(['hasta' => $hasta]);
        $integrante->save();

        return $integrante->refresh();
    }
}
