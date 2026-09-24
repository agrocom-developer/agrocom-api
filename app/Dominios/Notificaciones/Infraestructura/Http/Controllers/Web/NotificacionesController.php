<?php

namespace App\Dominios\Notificaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Notificaciones\Aplicacion\AbrirAlerta;
use App\Dominios\Notificaciones\Aplicacion\AbrirNotificacion;
use App\Dominios\Notificaciones\Aplicacion\LimpiarNotificaciones;
use App\Dominios\Notificaciones\Aplicacion\MarcarTodasLeidas;
use App\Dominios\Notificaciones\Infraestructura\DestinoDeNotificacion;
use App\Dominios\Notificaciones\Infraestructura\Http\Requests\AlertasDeLaCampanaRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * La campana del panel (tarea 141, ADR 0025). Adaptador delgado (ADR 0008)
 * sobre los casos de uso; sin permiso de grano fino, mismo criterio que
 * `/panel/perfil`: el sujeto es siempre la cuenta autenticada, nunca un id de
 * la petición. El id de cuenta sale de la sesión (`user('interno')`); el
 * id de un aviso del motor que llega de afuera se busca ENTRE LOS DE ESA
 * CUENTA — el de otra cuenta responde 404. Los ids de alerta técnica son de
 * todos (no hay «alerta de otra cuenta»): lo que es de cada cuenta es qué hizo
 * con ellas, y eso se escribe siempre bajo el id de la sesión.
 */
final class NotificacionesController
{
    private const PERMISO_ALERTAS = 'operaciones.alerta.ver';

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

    /**
     * `GET /panel/notificaciones/alertas/{alerta}/abrir`: deja la alerta técnica
     * leída para ESTA cuenta y lleva a su pantalla. No la declara atendida (eso es
     * de todos y se hace allá). Mismo criterio que `abrir`: GET porque es un enlace
     * de la campana, y marcar leído es idempotente. Exige el permiso de ver
     * alertas contra el rol activo, como la campana para mostrarlas.
     */
    public function abrirAlerta(Request $request, int $alerta, AbrirAlerta $abrir, AutorizacionPanelWeb $autorizacion): RedirectResponse
    {
        abort_unless($autorizacion->tienePermiso($request, self::PERMISO_ALERTAS), 403);

        $abrir->ejecutar($this->cuenta($request), $alerta);

        return redirect()->route('panel.alertas.index');
    }

    /** `POST /panel/notificaciones/marcar-todas`: solo las de esta cuenta. */
    public function marcarTodas(AlertasDeLaCampanaRequest $request, MarcarTodasLeidas $marcar, AutorizacionPanelWeb $autorizacion): RedirectResponse
    {
        $marcar->ejecutar($this->cuenta($request), $this->alertasQuePuedeVer($request, $autorizacion));

        return redirect()->back();
    }

    /** `POST /panel/notificaciones/limpiar`: vacía la campana de esta cuenta, y solo la suya. */
    public function limpiar(AlertasDeLaCampanaRequest $request, LimpiarNotificaciones $limpiar, AutorizacionPanelWeb $autorizacion): RedirectResponse
    {
        $limpiar->ejecutar($this->cuenta($request), $this->alertasQuePuedeVer($request, $autorizacion));

        return redirect()->back();
    }

    /**
     * Las alertas que llegaron en el formulario, si el rol activo puede verlas: sin el
     * permiso la campana no las muestra, así que no hay nada que marcar ni limpiar.
     *
     * @return list<int>
     */
    private function alertasQuePuedeVer(AlertasDeLaCampanaRequest $request, AutorizacionPanelWeb $autorizacion): array
    {
        return $autorizacion->tienePermiso($request, self::PERMISO_ALERTAS) ? $request->idsDeAlerta() : [];
    }

    private function cuenta(Request $request): int
    {
        return (int) $request->user('interno')?->getAuthIdentifier();
    }
}
