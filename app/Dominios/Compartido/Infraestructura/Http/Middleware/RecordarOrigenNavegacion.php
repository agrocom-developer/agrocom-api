<?php

namespace App\Dominios\Compartido\Infraestructura\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memento de navegación (17/9/2026, ampliado a PILA el mismo día): recuerda,
 * en sesión, la cadena de pantallas que el usuario fue dejando atrás al usar
 * accesos directos encadenados — cliente → "Nueva propiedad" → propiedad →
 * "Generar lotes" → lotes → "Siembra", o contrato → "Nueva orden de
 * aplicación" (cruza a `Operaciones`) — para que el botón "Volver"
 * (`x-molecules.boton-volver`) de CADA pantalla de la cadena vuelva un
 * escalón hacia atrás, no siempre al listado de su propio módulo.
 *
 * Un solo valor (la primera versión de este middleware) alcanza para UN
 * salto, pero se pisa a sí mismo apenas hay dos: cliente → propiedad ya
 * sobrescribía el origen "cliente" en cuanto propiedad abría OTRO acceso
 * directo hacia lotes. Una pila no tiene ese problema: cada acceso directo
 * apila un escalón nuevo: cada "Volver" saca el de arriba y cae en el
 * anterior — la cadena se puede recorrer completa, en cualquier profundidad.
 *
 * Contrato:
 * - Un link de acceso directo agrega `?volver_a=<url>&volver_texto=<etiqueta>`
 *   — la URL/etiqueta de la pantalla que lo ofrece, NUNCA la de destino. En
 *   cuanto este middleware ve los dos, apila esa pantalla (si no es ya la
 *   que está arriba de la pila — recargar la misma URL no duplica).
 * - El botón "Volver" arma su `href` con el tope de la pila + la marca
 *   `?_volver=1` — al aterrizar ahí, este middleware la ve, saca ESE escalón
 *   (ya lo usó) y redirige a la misma URL sin la marca (para que un F5
 *   posterior no vuelva a sacar otro escalón de encima).
 * - El resto de los requests del mismo sub-flujo (el POST del alta, el
 *   redirect a `edit()`) no traen ninguna de las dos marcas — la pila se
 *   queda como está, así sobrevive alta→edición sin que ningún controlador
 *   la vuelva a tocar.
 * - Visitar un listado (`*.index`) SIN query string reinicia la pila entera:
 *   es la señal de que el usuario entró por un módulo del sidebar a
 *   propósito (esas rutas nunca llevan query params — confirmado). Un
 *   listado CON query string (`panel.lotes.index?propiedad_id=`, el acceso
 *   "Ver lotes" del resumen de una propiedad con lotes ya cargados) es un
 *   escalón más de la cadena, no una entrada nueva — no la reinicia.
 */
final class RecordarOrigenNavegacion
{
    private const CLAVE_SESION = 'navegacion_pila';

    private const MARCA_VOLVER = '_volver';

    public function handle(Request $request, Closure $next): Response
    {
        $sesion = $request->session();

        if ($request->query(self::MARCA_VOLVER) !== null) {
            $pila = $sesion->get(self::CLAVE_SESION, []);
            array_pop($pila);
            $sesion->put(self::CLAVE_SESION, $pila);

            return new RedirectResponse($request->fullUrlWithQuery([self::MARCA_VOLVER => null]));
        }

        $url = $request->query('volver_a');
        $etiqueta = $request->query('volver_texto');

        if (is_string($url) && $url !== '' && is_string($etiqueta) && $etiqueta !== '') {
            $pila = $sesion->get(self::CLAVE_SESION, []);
            $tope = $pila === [] ? null : end($pila);

            if ($tope === null || $tope['url'] !== $url || $tope['etiqueta'] !== $etiqueta) {
                $pila[] = ['url' => $url, 'etiqueta' => $etiqueta];
                $sesion->put(self::CLAVE_SESION, $pila);
            }
        } elseif ($request->routeIs('*.index') && $request->query() === []) {
            $sesion->forget(self::CLAVE_SESION);
        }

        return $next($request);
    }
}
