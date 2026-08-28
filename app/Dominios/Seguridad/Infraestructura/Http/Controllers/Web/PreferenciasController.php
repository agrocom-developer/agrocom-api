<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Aplicacion\ActualizarPreferenciaUsuario;
use App\Dominios\Seguridad\Dominio\TemaPreferencia;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserPreferencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
