<?php

namespace App\Dominios\Notificaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Notificaciones\Aplicacion\AbrirNotificacion;
use App\Dominios\Notificaciones\Aplicacion\LimpiarNotificaciones;
use App\Dominios\Notificaciones\Aplicacion\MarcarTodasLeidas;
use App\Dominios\Notificaciones\Infraestructura\DestinoDeNotificacion;
use App\Dominios\Notificaciones\Infraestructura\Eloquent\Notificacion;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * La campana del panel (tarea 141, ADR 0025). Adaptador delgado (ADR 0008)
 * sobre los casos de uso; sin permiso de grano fino, mismo criterio que
 * `/panel/perfil`: el sujeto es siempre la cuenta autenticada, nunca un id de
 * la petición. El id de cuenta sale de la sesión (`user('interno')`); el
 * id de un aviso que llega de afuera se busca ENTRE LOS DE ESA CUENTA — el de
 * otra cuenta responde 404.
 */
final class NotificacionesController
{
    use BuscaAvisosPorTitulo;

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    /**
     * `GET /panel/notificaciones` (`panel.notificaciones.index`): «Mis
     * notificaciones», los avisos de la cuenta que mira, del más nuevo al más
     * viejo. La campana muestra solo los 3 últimos y enlaza aquí. SIN permiso
     * propio, como el resto de las rutas de la campana: el sujeto es siempre la
     * cuenta autenticada, nunca un id de la petición. Lo eliminado con
     * «Eliminar todas» ya no aparece: no hay historial aparte.
     */
    public function index(Request $request): View
    {
        $busqueda = TextoDeFiltro::de($request, 'q');

        $avisos = Notificacion::query()
            ->deUsuario($this->cuenta($request))
            ->when($busqueda !== '', fn ($consulta) => $consulta->whereIn('tipo', $this->tiposPorTitulo($busqueda)))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $cascara = $this->autorizacion->cascara($request);
        $zona = $cascara['zonaHoraria'] ?? null;

        $filas = $avisos->getCollection()->map(fn (Notificacion $aviso): array => [
            'id' => $aviso->id,
            'icono' => $aviso->tipo->icono(),
            'titulo' => __($aviso->tipo->claveDeTitulo(), $aviso->parametros ?? []),
            'leida' => $aviso->leida_en !== null,
            'creado_en' => Carbon::instance($aviso->created_at)->timezone($zona ?: config('app.timezone'))->format('d/m/Y H:i'),
        ])->all();

        return view('notificaciones::pages.notificaciones.index', [
            ...$cascara,
            'avisos' => $avisos,
            'filas' => $filas,
            'filtros' => ['q' => $busqueda],
        ]);
    }

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

    /** `POST /panel/notificaciones/limpiar`: vacía la campana de esta cuenta, y solo la suya. */
    public function limpiar(Request $request, LimpiarNotificaciones $limpiar): RedirectResponse
    {
        $limpiar->ejecutar($this->cuenta($request));

        return redirect()->back();
    }

    private function cuenta(Request $request): int
    {
        return (int) $request->user('interno')?->getAuthIdentifier();
    }
}
