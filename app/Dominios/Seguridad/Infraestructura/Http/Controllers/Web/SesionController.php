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
 * Autenticación por `username` + password contra el guard `interno`, nunca
 * por correo (memoria del proyecto). Sin broker de "olvidé mi contraseña"
 * (config/auth.php ya lo declara `null`).
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
        if (! Auth::guard('interno')->attempt([...$credenciales, 'state' => true])) {
            throw ValidationException::withMessages([
                'username' => ['Las credenciales no coinciden con ningún registro.'],
            ]);
        }

        $request->session()->regenerate();

        /** @var SecUser $usuario */
        $usuario = Auth::guard('interno')->user();

        $fijarZonaHoraria->ejecutarSiVacia($usuario, $request->string('zona_horaria')->toString() ?: null);

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
