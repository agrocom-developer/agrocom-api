<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Aplicacion\FijarZonaHorariaUsuario;
use App\Dominios\Seguridad\Aplicacion\IniciarSesionPanel;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\IniciarSesionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Adaptador delgado (ADR 0008) para el login/logout del panel: valida
 * credenciales, invoca el caso de uso de resolución de rol activo, responde
 * — la regla de negocio ("¿hace falta elegir rol?") vive en
 * {@see IniciarSesionPanel}, no acá.
 *
 * Autenticación por `username` + password, nunca por correo (memoria del
 * proyecto). `POST /login` es la única puerta de entrada del sistema: atiende
 * al guard `interno` y al guard `cliente` del portal, que ya no tiene URL de
 * ingreso propia (ver `store()`).
 */
final class SesionController
{
    public function store(IniciarSesionRequest $request, AutorizacionPanelWeb $autorizacion, IniciarSesionPanel $iniciarSesion, FijarZonaHorariaUsuario $fijarZonaHoraria): JsonResponse
    {
        $credenciales = $request->safe()->only(['username', 'password']);

        // `state` como condición extra de `Auth::attempt()`: el
        // EloquentUserProvider agrega un `where` por cada clave de
        // `$credentials` distinta de `password`, así que esto rechaza una
        // cuenta bloqueada (`seguridad.usuario.bloquear`, HU-45) con el
        // mismo mensaje genérico que una credencial incorrecta — nunca
        // revela si la cuenta existe pero está bloqueada.
        // "Recordarme": segundo argumento de `attempt()`, la cookie
        // `remember_web_*` de Laravel — sobrevive al cierre del navegador,
        // mientras que la sesión sola muere con él. Requiere
        // `sec_user.remember_token` (migración 2026_09_09_130001).
        //
        // Un solo login para todos (16/9/2026): se prueba el guard `interno`
        // y, si no es una cuenta interna, el guard `cliente` del portal. Los
        // guards siguen separados (ADR 0002 punto 6) — lo único compartido es
        // la puerta de entrada. `sec_user.username` es único entre cuentas
        // vivas sin importar `type`, así que a lo sumo uno de los dos
        // intentos puede encontrar la cuenta: nunca entra la persona
        // equivocada, y cada guard solo autentica las cuentas de su tipo.
        $guard = null;

        foreach (['interno', 'cliente'] as $candidato) {
            if (Auth::guard($candidato)->attempt([...$credenciales, 'state' => true], $request->boolean('remember'))) {
                $guard = $candidato;

                break;
            }
        }

        if ($guard === null) {
            throw ValidationException::withMessages([
                'username' => ['Las credenciales no coinciden con ningún registro.'],
            ]);
        }

        // Una sesión, una identidad: con una sola puerta de entrada, quien
        // ingresa reemplaza a quien estuviera en este navegador bajo el OTRO
        // guard — si no, un cliente que entra en una máquina donde quedó
        // abierta una sesión interna (o al revés) dejaría las dos vivas.
        $otroGuard = $guard === 'interno' ? 'cliente' : 'interno';

        if (Auth::guard($otroGuard)->check()) {
            Auth::guard($otroGuard)->logout();
            $request->session()->forget('sec_rol_activo_id');
        }

        $request->session()->regenerate();

        /** @var SecUser $usuario */
        $usuario = Auth::guard($guard)->user();

        $fijarZonaHoraria->ejecutarSiVacia($usuario, $request->string('zona_horaria')->toString() ?: null);

        // Una cuenta de portal no tiene `sec_user_role` (ADR 0004): no hay
        // rol que elegir ni menú que resolver, va directo a su portal. Misma
        // forma de respuesta que el panel, para que `pages/login.js` no
        // tenga que distinguir quién ingresó.
        if ($guard === 'cliente') {
            return response()->json([
                'requiere_seleccion_rol' => false,
                'rol_activo_id' => null,
                'roles' => [],
                'destino' => route('portal.avance.index'),
            ]);
        }

        $resultado = $iniciarSesion->ejecutar($usuario);

        return response()->json([
            'requiere_seleccion_rol' => $resultado->requiereSeleccion,
            'rol_activo_id' => $resultado->rolActivo?->id,
            'roles' => $resultado->rolesDisponibles->map(fn (SecRole $rol): array => [
                'id' => $rol->id,
                'name' => $rol->name,
                'description' => $rol->description,
            ])->values(),
            // Primer ítem visible del menú del rol activo (tarea 62, fuga 2):
            // solo tiene sentido cuando el rol activo ya quedó fijado en esta
            // misma request (rol único o preferido vivo, `IniciarSesionPanel`
            // arriba) — con 2+ roles sin preferido, `login.js` va al selector
            // y este valor no se usa.
            'destino' => $resultado->requiereSeleccion ? null : $autorizacion->primerDestinoVisible($request),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('interno')->logout();

        $request->session()->forget('sec_rol_activo_id');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sesión finalizada.']);
    }
}
