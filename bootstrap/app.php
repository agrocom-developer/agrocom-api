<?php

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
        ]);

        // HU-41 (tarea 55): sin esto, `Authenticate::redirectTo()` manda
        // SIEMPRE a `route('login')` (el login del panel interno) sin
        // importar qué guard rechazó el request — un guest golpeando
        // `/portal/*` terminaría en el login equivocado. Ambas rutas
        // (`login.form`/`portal.login.form`) comparten URI con su POST
        // homónimo (`login`/`portal.login`), así que esto no cambia el
        // destino del panel interno, solo agrega el del portal.
        $middleware->redirectGuestsTo(
            fn (Request $request): string => $request->is('portal/*')
                ? route('portal.login.form')
                : route('login.form'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
