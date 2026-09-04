<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Aplicacion\ActualizarPreferenciaUsuario;
use App\Dominios\Seguridad\Dominio\TemaPreferencia;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
