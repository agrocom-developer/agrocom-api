<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Aplicacion\ActualizarPreferenciaUsuario;
use App\Dominios\Seguridad\Dominio\TemaPreferencia;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserPreferencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * `POST /portal/preferencias/tema`: persiste el tema claro/oscuro del toggle
 * del header del portal. Mismo mecanismo que {@see PreferenciasController}
 * (`ActualizarPreferenciaUsuario` es agnóstico de guard: cualquier `SecUser`
 * administra su propia preferencia), separado en su propio controlador
 * porque lee `$request->user('cliente')`, no `'interno'` — sin idioma
 * variable todavía (único soportado, `es`), a diferencia del panel no hace
 * falta leer un idioma previo: siempre se persiste `es`.
 */
final class PreferenciasPortalController
{
    public function actualizarTema(Request $request, ActualizarPreferenciaUsuario $actualizar): JsonResponse
    {
        $validado = $request->validate([
            'tema' => ['required', 'in:light,dark'],
        ]);

        /** @var SecUser $usuario */
        $usuario = $request->user('cliente');

        $preferencia = $actualizar->ejecutar(
            $usuario,
            TemaPreferencia::desdeAtributoBootstrap($validado['tema']),
            'es',
        );

        return response()->json([
            'tema' => $preferencia->tema->atributoBootstrap(),
        ]);
    }

    /**
     * `POST /portal/preferencias/zona-horaria` (tarea 63): mismo criterio que
     * {@see PreferenciasController::actualizarZonaHoraria()}, guard `cliente`.
     */
    public function actualizarZonaHoraria(Request $request, ActualizarPreferenciaUsuario $actualizar): JsonResponse
    {
        $validado = $request->validate([
            'zona_horaria' => ['required', 'string', Rule::in(\DateTimeZone::listIdentifiers())],
        ]);

        /** @var SecUser $usuario */
        $usuario = $request->user('cliente');

        // Conserva el tema vigente: a diferencia de `actualizarTema`, acá el
        // tema no es lo que cambia — pisarlo con `Claro` a secas revertiría
        // el tema oscuro de un cliente que ya lo hubiera elegido. `value()`
        // sobre un Eloquent builder SÍ aplica el cast del modelo (hidrata la
        // fila y lee el atributo), así que ya llega como enum, no string.
        $temaActual = SecUserPreferencia::query()->where('user_id', $usuario->id)->value('tema') ?? TemaPreferencia::Claro;

        $preferencia = $actualizar->ejecutar(
            $usuario,
            $temaActual,
            'es',
            $validado['zona_horaria'],
        );

        return response()->json([
            'zona_horaria' => $preferencia->zona_horaria,
        ]);
    }
}
