<?php

namespace App\Dominios\Seguridad\Infraestructura;

use App\Dominios\Seguridad\Aplicacion\AnonimizarCuentaDeSeguridad;
use App\Dominios\Seguridad\Aplicacion\BuscarEnElPanel;
use App\Dominios\Seguridad\Aplicacion\CodigosDeVerificacion;
use App\Dominios\Seguridad\Aplicacion\EjecutarEliminacionesDeCuenta;
use App\Dominios\Seguridad\Aplicacion\EliminarMiCuenta;
use App\Dominios\Seguridad\Aplicacion\GestionarFotoDePerfil;
use App\Dominios\Seguridad\Aplicacion\RevocarTokensDeCuenta;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Contratos\EnviadorDeCodigo;
use App\Dominios\Seguridad\Contratos\FotosDePerfil;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use App\Dominios\Seguridad\Contratos\LecturaCuentas;
use App\Dominios\Seguridad\Contratos\LecturaUsuariosPorRol;
use App\Dominios\Seguridad\Contratos\RegistroCuentas;
use App\Dominios\Seguridad\Dominio\NumeroDeTelefono;
use App\Dominios\Seguridad\Infraestructura\Codigos\EnviadorEnrutado;
use App\Dominios\Seguridad\Infraestructura\Console\EjecutarEliminacionesDeCuentaCommand;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecTokenDispositivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioApp;
use App\Dominios\Seguridad\Infraestructura\Http\AutorizacionPanelWebSesion;
use App\Dominios\Seguridad\Infraestructura\Http\IdentidadOperarioTokenSanctum;
use App\Dominios\Seguridad\Infraestructura\Http\Middleware\ResolverRolActivo;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\PermisoVista;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Events\TokenAuthenticated;
use Laravel\Sanctum\Sanctum;

/**
 * Registra lo que el módulo `Seguridad` necesita exponer al framework y que
 * no se resuelve por convención (Eloquent y casos de uso ya se resuelven
 * solos vía autoload/constructor injection, sin necesitar esto).
 *
 * Primer `ServiceProvider` por módulo del proyecto — ADR 0003 preveía este
 * patrón ("cada módulo registra su propio ServiceProvider") sin haberlo
 * necesitado todavía. Nace acá porque las páginas Blade del panel (`pages/`,
 * `docs/diseno/sistema_diseno_panel.md` §3 nota de `pages/`: "son las
 * vistas reales bajo `Infraestructura/Http/` de cada módulo, ADR 0008 — no
 * le corresponde a `design-ui`") viven físicamente dentro de este módulo
 * (`Infraestructura/Http/Views/`), no bajo `resources/views/` — Blade
 * necesita que algo le informe esa ruta adicional. Un namespace de vista
 * (`seguridad::pages.dashboard`, etc.) en vez de agregar la ruta a secas
 * (`View::addLocation`) evita colisiones de nombre con vistas de otro módulo
 * que en el futuro adopte el mismo patrón.
 *
 * También registra la directiva `@puede('codigo.del.permiso')`, con la que
 * cualquier vista oculta un control sin permiso — ver {@see PermisoVista}
 * para por qué la decisión vive en una sola clase y por qué es fail-closed.
 *
 * Y, desde HU-03, el cableado de Sanctum para el token por dispositivo — ver
 * {@see self::configurarTokensDeDispositivo()}.
 */
final class SeguridadServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Frontera de Seguridad hacia otros módulos (ADR 0003, regla 2): un
        // controlador web de OTRO módulo (p. ej. Solicitudes) nunca
        // importa `SecUser` — pide el permiso/la cáscara por acá.
        $this->app->bind(AutorizacionPanelWeb::class, AutorizacionPanelWebSesion::class);

        // Misma frontera, para la API de campo (tarea 12): `Sincronizacion`
        // necesita saber qué persona de `Personal` firma el token sin
        // importar `SecUser`.
        $this->app->bind(IdentidadOperarioToken::class, IdentidadOperarioTokenSanctum::class);

        // Misma frontera, para `Notificaciones` (tarea 141, ADR 0025): a qué
        // cuentas avisar cuando un hecho le importa a un rol.
        $this->app->bind(LecturaUsuariosPorRol::class, LecturaUsuariosPorRolEloquent::class);

        // Misma frontera, para el marketplace (ADR 0036): `Proveedores` y
        // `Solicitudes` registran cuentas con rol y leen su nombre sin importar
        // `SecUser`.
        $this->app->bind(RegistroCuentas::class, RegistroCuentasEloquent::class);
        $this->app->bind(LecturaCuentas::class, RegistroCuentasEloquent::class);

        // Códigos de un solo uso (ADR 0046, punto 29): el dominio pide `EnviadorDeCodigo`; el correo sale por `Notification` y
        // WhatsApp y SMS por el adaptador de `config/verificacion.php`.
        $this->app->bind(EnviadorDeCodigo::class, EnviadorEnrutado::class);

        // Eliminar mi cuenta (ADR 0047): cada módulo aporta impedimentos, efectos y anonimización etiquetando su
        // `ParticipanteDeEliminacion` con `eliminacion.participantes`; Seguridad no los conoce.
        $this->app->bind(EliminarMiCuenta::class, static fn ($app): EliminarMiCuenta => new EliminarMiCuenta(
            $app->make(CodigosDeVerificacion::class),
            $app->make(RevocarTokensDeCuenta::class),
            $app->tagged('eliminacion.participantes'),
        ));
        $this->app->bind(EjecutarEliminacionesDeCuenta::class, static fn ($app): EjecutarEliminacionesDeCuenta => new EjecutarEliminacionesDeCuenta(
            $app->tagged('eliminacion.participantes'),
            $app->make(AnonimizarCuentaDeSeguridad::class),
        ));

        // Foto de perfil de la cuenta: otros módulos piden solo su URL firmada.
        $this->app->bind(FotosDePerfil::class, GestionarFotoDePerfil::class);

        // Buscador global: el agregador recibe TODOS los proveedores que cada
        // módulo taggeó, sin conocer ninguno (ADR 0003, regla 2 — acá el
        // contrato viaja al revés: Seguridad no importa Comercial, es Comercial
        // el que se ofrece). Si un módulo no taggea nada, sus entidades
        // simplemente no aparecen en la búsqueda.
        $this->app->singleton(
            BuscarEnElPanel::class,
            static fn ($app): BuscarEnElPanel => new BuscarEnElPanel($app->tagged('busqueda.proveedores')),
        );
    }

    public function boot(): void
    {
        View::addNamespace('seguridad', app_path('Dominios/Seguridad/Infraestructura/Http/Views'));

        // Genera @puede / @elsepuede / @endpuede. Evalúa SIEMPRE contra el rol
        // activo de la sesión, nunca contra la unión de roles del usuario
        // (CLAUDE.md invariante 10).
        Blade::if('puede', fn (string $codigo): bool => PermisoVista::puede($codigo));

        if ($this->app->runningInConsole()) {
            $this->commands([EjecutarEliminacionesDeCuentaCommand::class]);
        }

        $this->configurarTokensDeDispositivo();
        $this->configurarLimitesDeLogin();
        $this->configurarLimiteDeRegistroPublico();
        $this->configurarLimiteDeCodigosPublicos();
    }

    /**
     * Límite de los códigos sin sesión (`POST /api/auth/recuperar` y `/confirmar`, ADR 0046, punto 27). Cuenta por IP (5 por
     * minuto y 30 por hora) y por identificador escrito (10 cada 15 minutos), exista o no la cuenta, para que el límite no
     * revele nada y nadie agote los códigos de otra persona desde muchas IP.
     */
    private function configurarLimiteDeCodigosPublicos(): void
    {
        RateLimiter::for('codigos-publicos', function (Request $request): array {
            $crudo = trim((string) $request->input('identificador'));
            $identificador = NumeroDeTelefono::normalizar($crudo) ?? mb_strtolower($crudo);

            return [
                Limit::perMinute(5)->by('codigos-publicos|minuto|'.$request->ip()),
                Limit::perHour(30)->by('codigos-publicos|hora|'.$request->ip()),
                Limit::perMinutes(15, 10)->by('codigos-publicos|identificador|'.sha1($request->input('app').'|'.$identificador)),
            ];
        });
    }

    /**
     * Límite del registro público de clientes y proveedores (ADR 0036): son
     * endpoints sin autenticación que crean cuentas, así que sin un tope sirven
     * para llenar la base de cuentas basura. Por IP: 5 altas por minuto y 20
     * por hora (una oficina que registra a varios clientes seguidos cabe; un
     * script no).
     */
    private function configurarLimiteDeRegistroPublico(): void
    {
        RateLimiter::for('registro-publico', function (Request $request): array {
            return [
                Limit::perMinute(5)->by('registro-publico|minuto|'.$request->ip()),
                Limit::perHour(20)->by('registro-publico|hora|'.$request->ip()),
            ];
        });
    }

    /**
     * Límite del login de la app de campo (`POST /api/auth/token`).
     *
     * Dos cubos, no uno. Los dispositivos del lote comparten una sola IP
     * (el enlace satelital), así que limitar solo por IP bloquearía a todo el
     * equipo por los errores de uno. Y limitar solo por usuario permitiría
     * probar miles de usuarios distintos desde una sola IP:
     * - por usuario + IP: 6 intentos por minuto. Frena la fuerza bruta contra
     *   una cuenta sin afectar a los compañeros de la misma IP.
     * - por IP: 60 intentos por minuto. Techo amplio contra el barrido de
     *   usuarios; un lote real con decenas de dispositivos no lo alcanza.
     */
    private function configurarLimitesDeLogin(): void
    {
        RateLimiter::for('login-campo', function (Request $request): array {
            // El teléfono se normaliza igual que en el login: `70012345` y `+591 700-12345` son el mismo cubo.
            $crudo = trim((string) $request->input('username'));
            $usuario = NumeroDeTelefono::normalizar($crudo) ?? mb_strtolower($crudo);

            return [
                Limit::perMinute(6)->by('login-campo|usuario|'.sha1($usuario).'|'.$request->ip()),
                Limit::perMinute(60)->by('login-campo|ip|'.$request->ip()),
            ];
        });
    }

    /**
     * Cableado de Sanctum para el token por dispositivo (HU-03).
     *
     * `usePersonalAccessTokenModel` apunta al modelo del módulo
     * ({@see SecTokenDispositivo}, tabla `sec_token_dispositivo`) — sin esto,
     * Sanctum buscaría en `personal_access_tokens`, una tabla que este
     * proyecto no crea a propósito: no lleva prefijo de módulo (ADR 0011) ni
     * las columnas de auditoría y soft delete del ADR 0007.
     *
     * `authenticateAccessTokensUsing` agrega, sobre las validaciones propias
     * de Sanctum (existencia, caducidad, provider), las dos que hacen a la
     * seguridad de este sistema, revalidadas contra la base en CADA request:
     * que la cuenta siga habilitada, y que el rol con el que se emitió el
     * token siga siendo un rol vivo del usuario. Es el equivalente para la
     * app de campo de lo que {@see ResolverRolActivo} hace en el panel —
     * bloquear a alguien o revocarle un rol tiene efecto en el request
     * siguiente, sin que nadie tenga que acordarse de revocar el token a mano
     * (ADR 0004, extensión 27/8/2026, punto 2).
     */
    private function configurarTokensDeDispositivo(): void
    {
        Sanctum::usePersonalAccessTokenModel(SecTokenDispositivo::class);

        Sanctum::authenticateAccessTokensUsing(
            static function (SecTokenDispositivo $token, bool $esValidoParaSanctum): bool {
                if (! $esValidoParaSanctum) {
                    return false;
                }

                /** @var SecUsuarioApp|null $usuario */
                $usuario = $token->tokenable;

                if ($usuario === null || ! $usuario->state) {
                    return false;
                }

                // Nunca la unión de roles: el token vale por el rol con el
                // que se emitió, y solo mientras ese rol siga vivo
                // (invariante 10 de CLAUDE.md).
                return in_array($token->role_id, $usuario->idsDeRolesActivos(), true);
            },
        );

        $this->registrarUltimoUso();
    }

    /**
     * `last_used_at` del token, escrito por nosotros y no por Sanctum
     * (`sanctum.last_used_at` está en `false` — ver el comentario largo en
     * config/sanctum.php).
     *
     * `saveQuietly()` es la pieza clave y tiene dos razones, ambas
     * suficientes por sí solas:
     *
     * 1. Sin eventos no corre `RegistraAutoria`, que llamaría a `Auth::id()`
     *    mientras el guard `sanctum` todavía está resolviendo al usuario —
     *    una re-entrada que se repite hasta agotar la memoria del proceso.
     * 2. Usar la app no es una edición del token: `updated_by` tiene que
     *    seguir diciendo quién lo emitió o lo revocó, no quién lo usó por
     *    última vez.
     */
    private function registrarUltimoUso(): void
    {
        Event::listen(static function (TokenAuthenticated $evento): void {
            // Siempre es un SecTokenDispositivo: es el único modelo de token
            // que este proyecto registra (ver usePersonalAccessTokenModel
            // arriba). El docblock del evento en Sanctum lo declara como su
            // propia clase de token, que no es la nuestra.
            /** @var SecTokenDispositivo $token */
            $token = $evento->token;

            $token->forceFill(['last_used_at' => now()])->saveQuietly();
        });
    }
}
