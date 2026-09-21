<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;

/**
 * Edición de una persona operativa (HU-26, tarea 37).
 *
 * Sin guarda contra devengos históricos: `Finanzas/GenerarDevengosSesion`
 * copia `tarifa_ha` en el `DevengoPersonal` al momento de generarse, nunca
 * la relee de esta tabla después — cambiar la tarifa acá no altera ningún
 * devengo ya generado (ver `tests/Feature/Personal/GestionPersonasPanelTest.php`,
 * caso de congelamiento).
 */
final class ActualizarPersona
{
    public function ejecutar(PerPersona $persona, DatosPersona $datos): PerPersona
    {
        $persona->fill($datos->atributos());
        $persona->save();

        return $persona->refresh();
    }
}
