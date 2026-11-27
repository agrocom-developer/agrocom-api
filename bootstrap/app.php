<?php

use App\Dominios\Compartido\Dominio\Excepciones\CuentaNoAprobada;
use App\Dominios\Compartido\Infraestructura\Http\ErroresHttpEnEspanol;
use App\Dominios\Compartido\Infraestructura\Http\Middleware\RecordarOrigenNavegacion;
use App\Dominios\Seguridad\Infraestructura\Http\Middleware\ExigirCuentaSinEliminacion;
use App\Dominios\Seguridad\Infraestructura\Http\Middleware\LimitarSondeoDeCuentas;
use App\Dominios\Seguridad\Infraestructura\Http\Middleware\ResolverRolActivo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // Rutas de las apps de campo — separadas del panel, sin guards
        // compartidos (ADR 0008).
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ADR 0017: en producción beta, el proxy del Proxmox (contenedor 119)
        // termina HTTPS y reenvía en HTTP plano al contenedor de la app. Sin
        // esto, Laravel ve todo el tráfico como inseguro y genera URLs/redirects
        // con http:// aunque el cliente haya entrado por https://. Confiar en
        // '*' es razonable acá porque hay un único punto de entrada controlado
        // (ese proxy), no una cadena de proxies arbitraria de Internet.
        $middleware->trustProxies(at: '*');

        // ADR 0004, extensión 27/8/2026: resuelve/revalida el rol activo de
        // la sesión del panel. Alias disponible para que las rutas del panel
        // (fuera de alcance de HU-02 backend, a cargo de `frontend`) lo
        // agreguen después de `auth:interno` sin depender del FQCN.
        $middleware->alias([
            'rol.activo' => ResolverRolActivo::class,
            'origen.navegacion' => RecordarOrigenNavegacion::class,
            'sondeo.cuentas' => LimitarSondeoDeCuentas::class,
            'cuenta.sin-eliminacion' => ExigirCuentaSinEliminacion::class,
        ]);

        // Sin sesión, el panel va al formulario de ingreso.
        $middleware->redirectGuestsTo(fn (): string => route('login.form'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // La compuerta de la cuenta de app (ADR 0046, punto 12): `403 cuenta_no_aprobada` con el estado y lo que falta.
        $exceptions->render(
            fn (CuentaNoAprobada $excepcion, Request $request) => response()->json($excepcion->cuerpo(), 403),
        );

        // Los errores que arma el propio framework al responder JSON (401,
        // 403, 404, 405, 419, 429, 5xx) salen en inglés y a veces con nombres
        // de clases internas: se traducen acá, respetando los mensajes
        // propios del dominio — ver ErroresHttpEnEspanol.
        $exceptions->render(
            fn (Throwable $excepcion, Request $request) => ErroresHttpEnEspanol::responder($excepcion, $request),
        );
    })->create();
