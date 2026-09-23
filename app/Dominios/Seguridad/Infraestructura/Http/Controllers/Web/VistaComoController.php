<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Aplicacion\IniciarVistaComo;
use App\Dominios\Seguridad\Aplicacion\TerminarVistaComo;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Dominio\Excepciones\VistaComoNoPermitida;
use App\Dominios\Seguridad\Dominio\MotivoFinVistaComo;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Dominio\VistaComoActiva;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Middleware\AplicarVistaComo;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\IniciarVistaComoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Entrada y salida de la vista "como otro usuario" (tarea 140). Adaptador
 * delgado (ADR 0008): las reglas viven en {@see IniciarVistaComo} y
 * {@see TerminarVistaComo}, y lo que hace que la vista se aplique en cada
 * request vive en {@see AplicarVistaComo}.
 *
 * Los dos son POST y los dos corren con la sesión REAL del administrador — el
 * middleware no sustituye nada en la ruta de salida y en la de entrada todavía
 * no hay bandera —, así que la bitácora los firma con quien de verdad los hizo.
 */
final class VistaComoController
{
    private const PERMISO = 'seguridad.usuario.ver_como';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    /**
     * `POST /panel/usuarios/{usuario}/ver-como` (`panel.usuarios.ver-como`).
     * Permiso propio, verificado contra el ROL ACTIVO: `ver_como` no está
     * implícito en ningún otro permiso de usuarios.
     */
    public function iniciar(IniciarVistaComoRequest $request, SecUser $usuario, IniciarVistaComo $iniciarVistaComo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        /** @var SecUser $admin */
        $admin = $request->user('interno');
        $idRol = $request->validated('rol_id');

        try {
            $vista = $iniciarVistaComo->ejecutar(
                $admin,
                (int) $request->session()->get('sec_rol_activo_id'),
                $usuario->id,
                $idRol !== null ? (int) $idRol : null,
            );
        } catch (VistaComoNoPermitida $excepcion) {
            return redirect()
                ->route('panel.usuarios.index')
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        // El destino lo resuelve el request siguiente, YA con la vista aplicada:
        // `/` elige el primer ítem visible del rol observado (no se conoce acá
        // sin sustituir al usuario) y el portal arranca en su avance.
        return $vista->tipo === TipoUsuario::Cliente
            ? redirect()->route('portal.avance.index')
            : redirect('/');
    }

    /**
     * `POST /vista-como/salir` (`vista-como.salir`): «Volver a mi vista». Es la
     * única escritura que `AplicarVistaComo` deja pasar mientras hay una vista
     * abierta — justamente porque es la forma de terminarla.
     */
    public function salir(Request $request, TerminarVistaComo $terminarVistaComo): RedirectResponse
    {
        $vista = VistaComoActiva::desdeSesion($request->session()->get(VistaComoActiva::CLAVE_SESION));

        if ($vista === null) {
            // Nada que cerrar (ya se cerró en otra pestaña, o venció).
            $request->session()->forget(VistaComoActiva::CLAVE_SESION);

            return redirect('/');
        }

        // La bandera es de OTRO administrador (una sesión mezclada, que no debería
        // darse): no se escribe nada a nombre de quien no la abrió, solo se descarta.
        if ((int) $request->user('interno')?->getAuthIdentifier() !== $vista->adminId) {
            $request->session()->forget(VistaComoActiva::CLAVE_SESION);

            return redirect('/');
        }

        $terminarVistaComo->ejecutar($vista, MotivoFinVistaComo::Manual);

        // Con el rol del administrador ya restaurado, este permiso se evalúa
        // contra ÉL: si puede ver usuarios vuelve al listado desde el que
        // entró; si no, al primer destino visible de su menú (`/`).
        return $this->autorizacion->tienePermiso($request, 'seguridad.usuario.ver')
            ? redirect()->route('panel.usuarios.index')->with('estado', __('seguridad.vista_como.volviste'))
            : redirect('/')->with('estado', __('seguridad.vista_como.volviste'));
    }
}
