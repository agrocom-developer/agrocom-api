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
 * `POST /panel/preferencias/tema` (`panel.preferencias.tema`): persiste el
 * tema claro/oscuro elegido con el toggle del header (quinta vuelta — hasta
 * acá el toggle solo cambiaba `data-bs-theme` en el DOM y la preferencia se
 * perdía en cada recarga; el oyente de `agrocom:theme-changed` en
 * `resources/js/molecules/theme-toggle.js` ahora postea acá).
 *
 * Solo `auth:interno`, sin `rol.activo`: el tema es del usuario, no del rol
 * — y así el toggle también funciona en la pantalla de selección de rol,
 * que corre sin rol activo resuelto.
 *
 * Adaptador delgado (ADR 0008): mapea `light`/`dark` del DOM al vocabulario
 * del dominio vía {@see TemaPreferencia::desdeAtributoBootstrap()} y delega
 * en {@see ActualizarPreferenciaUsuario} (que conserva el idioma vigente).
 */
final class PreferenciasController
{
    public function actualizarTema(Request $request, ActualizarPreferenciaUsuario $actualizar): JsonResponse
    {
        $validado = $request->validate([
            'tema' => ['required', 'in:light,dark'],
        ]);

        /** @var SecUser $usuario */
        $usuario = $request->user('interno');

        $idiomaActual = SecUserPreferencia::query()
            ->where('user_id', $usuario->id)
            ->value('idioma') ?? 'es';

        $preferencia = $actualizar->ejecutar(
            $usuario,
            TemaPreferencia::desdeAtributoBootstrap($validado['tema']),
            $idiomaActual,
        );

        return response()->json([
            'tema' => $preferencia->tema->atributoBootstrap(),
        ]);
    }

    /**
     * `POST /panel/preferencias/zona-horaria` (`panel.preferencias.zona-horaria`,
     * tarea 63): cambio explícito desde el selector del topbar — a diferencia
     * del fijado automático del login (`FijarZonaHorariaUsuario`), este SIEMPRE
     * pisa lo que hubiera. `Rule::in()` contra la base IANA de PHP rechaza con
     * 422 cualquier valor que no sea un identificador real, antes de llegar al
     * caso de uso.
     */
    public function actualizarZonaHoraria(Request $request, ActualizarPreferenciaUsuario $actualizar): JsonResponse
    {
        $validado = $request->validate([
            'zona_horaria' => ['required', 'string', Rule::in(\DateTimeZone::listIdentifiers())],
        ]);

        /** @var SecUser $usuario */
        $usuario = $request->user('interno');

        $preferenciaActual = SecUserPreferencia::query()->where('user_id', $usuario->id)->first();

        $preferencia = $actualizar->ejecutar(
            $usuario,
            $preferenciaActual->tema ?? TemaPreferencia::Claro,
            $preferenciaActual->idioma ?? 'es',
            $validado['zona_horaria'],
        );

        return response()->json([
            'zona_horaria' => $preferencia->zona_horaria,
        ]);
    }
}
