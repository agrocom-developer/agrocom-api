<?php

namespace App\Dominios\Notificaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Notificaciones\Aplicacion\AbrirNotificacion;
use App\Dominios\Notificaciones\Aplicacion\MarcarTodasLeidas;
use App\Dominios\Notificaciones\Infraestructura\DestinoDeNotificacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * La campana del panel (tarea 141, ADR 0025). Adaptador delgado (ADR 0008)
 * sobre los casos de uso; sin permiso de grano fino, mismo criterio que
 * `/panel/perfil`: el sujeto es siempre la cuenta autenticada, nunca un id de
 * la petición. El id de cuenta sale de la sesión (`user('interno')`); el
 * único id que llega de afuera es el del aviso, y se busca ENTRE LOS DE ESA
 * CUENTA — el de otra cuenta responde 404.
 */
final class NotificacionesController
{
    /**
     * `GET /panel/notificaciones/{notificacion}/abrir`: marca leído y lleva al
     * recurso (o al tablero si el rol activo no puede verlo). GET a propósito:
     * es un enlace de la campana, se puede abrir en otra pestaña; marcar leído
     * es idempotente.
     */
    public function abrir(Request $request, int $notificacion, AbrirNotificacion $abrir, DestinoDeNotificacion $destino): RedirectResponse
    {
        $aviso = $abrir->ejecutar($this->cuenta($request), $notificacion);

        return redirect()->to($destino->url($request, $aviso));
    }

    /** `POST /panel/notificaciones/marcar-todas`: solo las de esta cuenta. */
    public function marcarTodas(Request $request, MarcarTodasLeidas $marcar): RedirectResponse
    {
        $marcar->ejecutar($this->cuenta($request));

        return redirect()->back();
    }

    private function cuenta(Request $request): int
    {
        return (int) $request->user('interno')?->getAuthIdentifier();
    }
}
