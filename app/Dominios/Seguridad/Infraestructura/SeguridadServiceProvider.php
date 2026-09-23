<?php

namespace App\Dominios\Seguridad\Infraestructura;

use App\Dominios\Seguridad\Aplicacion\BuscarEnElPanel;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Contratos\AutorizacionPortalCliente;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use App\Dominios\Seguridad\Contratos\LecturaUsuarioDePersona;
use App\Dominios\Seguridad\Contratos\LecturaUsuariosPorRol;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecTokenDispositivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use App\Dominios\Seguridad\Infraestructura\Http\AutorizacionPanelWebSesion;
use App\Dominios\Seguridad\Infraestructura\Http\AutorizacionPortalClienteSesion;
use App\Dominios\Seguridad\Infraestructura\Http\IdentidadOperarioTokenSanctum;
use App\Dominios\Seguridad\Infraestructura\Http\Middleware\ResolverRolActivo;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\PermisoVista;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
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
        // controlador web de OTRO módulo (p. ej. Distribucion, HU-20) nunca
        // importa `SecUser` — pide el permiso/la cáscara por acá.
        $this->app->bind(AutorizacionPanelWeb::class, AutorizacionPanelWebSesion::class);

        // Misma frontera, para el portal del cliente (HU-41, tarea 55):
        // `Portal` necesita el `contrato_id` de la sesión sin importar
        // `SecUser`/`SecUsuarioCliente`.
        $this->app->bind(AutorizacionPortalCliente::class, AutorizacionPortalClienteSesion::class);

        // Misma frontera, para la API de campo (tarea 12): `Sincronizacion`
        // necesita saber qué persona de `Personal` firma el token sin
        // importar `SecUser`.
        $this->app->bind(IdentidadOperarioToken::class, IdentidadOperarioTokenSanctum::class);

        // Misma frontera, para la ficha de una persona (tarea 112): `Personal`
        // muestra la cuenta vinculada sin importar `SecUser`.
        $this->app->bind(LecturaUsuarioDePersona::class, LecturaUsuarioDePersonaEloquent::class);

        // Misma frontera, para `Notificaciones` (tarea 141, ADR 0025): a qué
        // cuentas avisar cuando un hecho le importa a un rol.
        $this->app->bind(LecturaUsuariosPorRol::class, LecturaUsuariosPorRolEloquent::class);

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

        $this->configurarTokensDeDispositivo();
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

                /** @var SecUsuarioInterno|null $usuario */
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
