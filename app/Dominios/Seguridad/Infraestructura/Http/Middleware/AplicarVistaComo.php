<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Middleware;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModoSoloLectura;
use App\Dominios\Seguridad\Aplicacion\ElegirRolActivo;
use App\Dominios\Seguridad\Aplicacion\ResolverVistaComo;
use App\Dominios\Seguridad\Aplicacion\TerminarVistaComo;
use App\Dominios\Seguridad\Aplicacion\VistaComoResuelta;
use App\Dominios\Seguridad\Dominio\Excepciones\EscrituraEnVistaComo;
use App\Dominios\Seguridad\Dominio\MotivoFinVistaComo;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Dominio\VistaComoActiva;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\PresentadorRol;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aplica la vista "como otro usuario" de la sesión (tarea 140). Es la pieza
 * crítica de esa capacidad: decide, request por request, a QUIÉN se evalúa el
 * panel o el portal, y lo hace sin reemplazar jamás la sesión de autenticación
 * del administrador.
 *
 * Va en el grupo `web`, después de `StartSession` y ANTES de `auth:interno` /
 * `auth:cliente` (ver `bootstrap/app.php`, lista de prioridad): cuando esos
 * middlewares preguntan "¿quién está autenticado?", ya contestan con la cuenta
 * observada. Con eso —y solo con eso— el resto del sistema no se entera: el
 * panel arma el menú y los permisos del rol observado, y el portal resuelve
 * su contrato desde la MISMA cuenta de portal que resolvería en una sesión
 * real (invariante 5 de CLAUDE.md): `AutorizacionPortalCliente::contratoId()`
 * lee `user('cliente')->contrato_id`, sin un `where` aparte para el caso
 * "admin mirando".
 *
 * Orden de las decisiones, de la más barata y más firme a la más costosa:
 *
 * 1. **Sin bandera → no hace nada.** El costo de esta capacidad para todo
 *    request normal es leer una clave de sesión.
 * 2. **Solo lectura, antes de tocar la base.** Con una bandera presente,
 *    cualquier método que no sea GET/HEAD/OPTIONS se rechaza con 403 — mire lo
 *    que mire el resto. No depende de que la vista siga siendo válida ni de
 *    que ninguna consulta salga bien: falla cerrado. La única excepción es la
 *    ruta de salida, que es justamente la forma de terminar la vista.
 * 3. **Revalidación.** La bandera nunca se cree a ciegas ({@see ResolverVistaComo}).
 *    Si ya no vale, la vista se cierra con motivo `invalidada` y el request
 *    sigue como el administrador.
 * 4. **Alcance.** Una vista de portal solo ve `/portal/*`; una interna, solo
 *    `/panel/*`. Sin esto, el banner diría "viendo como El Carmen" sobre las
 *    pantallas propias del administrador.
 * 5. **Sustitución acotada al request.** Se pone la cuenta observada en el
 *    guard (`setUser`, que no escribe la sesión de autenticación), se activa
 *    {@see ModoSoloLectura} y, en un `finally`, se devuelve todo: el guard
 *    vuelve a ser el administrador ANTES de que `StartSession` guarde la
 *    sesión (así `sessions.user_id` no queda apuntando a la cuenta observada).
 *
 * La ruta de salida NO sustituye nada: el actor de la bitácora sale del guard,
 * y la fila de "volvió" tiene que quedar a nombre del administrador real.
 */
final class AplicarVistaComo
{
    public const RUTA_SALIDA = 'vista-como.salir';

    /** Métodos que jamás cambian nada. Todo lo demás es escritura. */
    private const METODOS_DE_LECTURA = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(
        private readonly ResolverVistaComo $resolverVistaComo,
        private readonly TerminarVistaComo $terminarVistaComo,
        private readonly ElegirRolActivo $elegirRolActivo,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $sesion = $request->session();
        $crudo = $sesion->get(VistaComoActiva::CLAVE_SESION);

        if ($crudo === null) {
            return $next($request);
        }

        $esSalida = $request->routeIs(self::RUTA_SALIDA);

        if (! $esSalida) {
            $this->exigirSoloLectura($request);
        }

        $vista = VistaComoActiva::desdeSesion($crudo);

        if ($vista === null) {
            // Ilegible: no hay a quién sustituir. Se descarta y el request
            // sigue como el administrador (que sí está autenticado).
            $sesion->forget(VistaComoActiva::CLAVE_SESION);

            return $next($request);
        }

        if ($esSalida) {
            return $next($request);
        }

        $admin = Auth::guard('interno')->user();

        if (! $admin instanceof SecUser || $admin->id !== $vista->adminId) {
            // La autenticación de esta sesión no es la del administrador que
            // abrió la vista: no se sustituye a nadie, y tampoco se escribe (el
            // actor de la bitácora sería otro). Solo se descarta la bandera.
            Log::warning('Vista como otro usuario descartada: la sesión autenticada no es la del administrador que la abrió.', [
                'registro_id' => $vista->registroId,
                'admin_esperado' => $vista->adminId,
            ]);
            $sesion->forget(VistaComoActiva::CLAVE_SESION);

            return $next($request);
        }

        $resuelta = $this->resolverVistaComo->ejecutar($vista, $admin);

        if ($resuelta === null) {
            $this->terminarVistaComo->ejecutar($vista, MotivoFinVistaComo::Invalidada);

            return redirect('/')->with('vista_como_fin', __('seguridad.vista_como.invalidada'));
        }

        $desvio = $this->desvioPorAlcance($request, $vista);

        if ($desvio !== null) {
            return $desvio;
        }

        return $this->conVistaAplicada($request, $next, $vista, $resuelta);
    }

    /**
     * @throws EscrituraEnVistaComo si el método puede cambiar algo
     */
    private function exigirSoloLectura(Request $request): void
    {
        // Los dos: el método real de la conexión y el que el framework
        // resolvió. Un POST con `_method=GET` se enrutaría como lectura, pero
        // sigue siendo un POST — no hay motivo para dejarlo pasar.
        if (
            ! in_array($request->getRealMethod(), self::METODOS_DE_LECTURA, true)
            || ! in_array($request->getMethod(), self::METODOS_DE_LECTURA, true)
        ) {
            throw EscrituraEnVistaComo::porSerSoloLectura();
        }
    }

    /**
     * Una vista de portal solo recorre el portal; una interna, solo el panel.
     * Fuera de su alcance el request se desvía en vez de mostrar, bajo un
     * banner que dice otra cosa, las pantallas propias del administrador. Los
     * selectores de rol también quedan afuera: cambiar de rol es escribir.
     */
    private function desvioPorAlcance(Request $request, VistaComoActiva $vista): ?Response
    {
        if ($vista->tipo === TipoUsuario::Cliente) {
            return $request->routeIs('portal.*') ? null : redirect()->route('portal.avance.index');
        }

        return $request->routeIs('portal.*', 'panel.rol-activo.*') ? redirect('/') : null;
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    private function conVistaAplicada(Request $request, Closure $next, VistaComoActiva $vista, VistaComoResuelta $resuelta): Response
    {
        // Los dos guards del panel y del portal son de sesión: `setUser()` y
        // `forgetUser()` no forman parte del contrato genérico `Guard`.
        /** @var SessionGuard $guard */
        $guard = Auth::guard($vista->guard());

        if ($vista->tipo === TipoUsuario::Interno && $resuelta->rol !== null) {
            $this->elegirRolActivo->fijarParaVistaComo($resuelta->rol);
        }

        View::share('vistaComo', [
            'nombre' => $resuelta->observado->name,
            'tipo' => $vista->tipo->value,
            'rol' => $resuelta->rol !== null ? PresentadorRol::nombreLegible($resuelta->rol) : null,
            'admin' => $resuelta->admin->name,
            'salirUrl' => route(self::RUTA_SALIDA),
        ]);

        $guard->setUser($resuelta->observado);
        ModoSoloLectura::activar();

        try {
            return $next($request);
        } finally {
            ModoSoloLectura::desactivar();

            if ($vista->tipo === TipoUsuario::Interno) {
                $guard->setUser($resuelta->admin);
            } else {
                $guard->forgetUser();
            }
        }
    }
}
