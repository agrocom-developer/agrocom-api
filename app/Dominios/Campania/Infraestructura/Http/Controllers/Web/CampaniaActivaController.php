<?php

namespace App\Dominios\Campania\Infraestructura\Http\Controllers\Web;

use App\Dominios\Campania\Aplicacion\ElegirCampaniaActiva;
use App\Dominios\Campania\Infraestructura\Http\Requests\ActualizarCampaniaActivaRequest;
use Illuminate\Http\RedirectResponse;

/**
 * `POST /panel/campania-activa` (ADR 0015 punto 1, tarea 69): cambio de
 * campaña activa sin volver a loguearse — espejo de
 * `Seguridad\Infraestructura\Http\Controllers\Web\RolActivoController::update()`,
 * pero sin pantalla de selección propia: no hay "sin campaña activa
 * resuelta" que bloquee nada (la campaña activa filtra, no autoriza), así
 * que no hace falta una vía de escape como la del rol.
 *
 * Adaptador delgado (ADR 0008): valida forma, invoca el caso de uso, vuelve
 * a la página desde la que se pidió el cambio — el `<select>` que postea
 * acá vive en `campania::pages.campanias.index` (único lugar del panel con
 * el listado completo de campañas para elegir, gateado por
 * `campania.campania.ver`).
 *
 * Si `CampaniaNoEncontrada` (campaña borrada o inexistente, `<select>`
 * fabricado a mano) el manejador de excepciones del framework la traduce a
 * 403 sin mapeo adicional acá — mismo patrón que `RolNoAsignado`.
 */
final class CampaniaActivaController
{
    public function update(ActualizarCampaniaActivaRequest $request, ElegirCampaniaActiva $elegirCampaniaActiva): RedirectResponse
    {
        $elegirCampaniaActiva->ejecutar((int) $request->validated('id_campania'));

        return redirect()->back();
    }
}
