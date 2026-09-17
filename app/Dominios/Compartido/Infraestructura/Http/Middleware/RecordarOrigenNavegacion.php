<?php

namespace App\Dominios\Compartido\Infraestructura\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memento de navegación (17/9/2026): recuerda, en sesión, desde qué pantalla
 * salió el usuario al usar un "acceso directo" — crear cliente/propiedad/lote
 * desde el formulario de contrato, "Nueva orden" desde el resumen de un
 * cliente, etc. — para que el botón "Volver" de la pantalla de destino
 * regrese a ESE origen (`x-molecules.boton-volver`), no siempre al listado de
 * su propio módulo.
 *
 * Reemplaza (para el botón de cabecera; ver docblock de `boton-volver`) el
 * mecanismo `volver_a` que Clientes/Propiedades/Lotes reimplementaban cada
 * uno por su cuenta: leer el query param en `create()`, flashearlo a sesión
 * en `store()`, releerlo en `edit()`. Acá vive una sola vez, para cualquier
 * pantalla del panel, sin que su controlador toque una línea.
 *
 * Contrato:
 * - Un link de acceso directo agrega `?volver_a=<url>&volver_texto=<etiqueta>`
 *   a su `href`. En cuanto este middleware ve los dos, los guarda en sesión.
 * - El resto de los requests del mismo sub-flujo (el POST del alta, el
 *   redirect a `edit()`, cualquier pantalla intermedia) no traen esos query
 *   params — el memento se queda como está, así sobrevive alta→edición sin
 *   que ningún controlador lo vuelva a leer ni a flashear.
 * - Visitar cualquier listado (ruta `*.index`, convención ya vigente en todas
 *   las rutas de panel) reinicia el memento: es la señal de que el usuario
 *   entró por un módulo a propósito (el sidebar solo enlaza a `index`, nunca
 *   a un `create`/`edit` con query params — confirmado, no arma URLs con
 *   parámetros), no que sigue dentro de un sub-flujo.
 */
final class RecordarOrigenNavegacion
{
    private const CLAVE_SESION = 'navegacion_origen';

    public function handle(Request $request, Closure $next): Response
    {
        $url = $request->query('volver_a');
        $etiqueta = $request->query('volver_texto');

        if (is_string($url) && $url !== '' && is_string($etiqueta) && $etiqueta !== '') {
            $request->session()->put(self::CLAVE_SESION, [
                'url' => $url,
                'etiqueta' => $etiqueta,
            ]);
        } elseif ($request->routeIs('*.index')) {
            $request->session()->forget(self::CLAVE_SESION);
        }

        return $next($request);
    }
}
