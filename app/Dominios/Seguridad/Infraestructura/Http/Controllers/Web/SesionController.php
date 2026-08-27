<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Aplicacion\IniciarSesionPanel;
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
    public function store(IniciarSesionRequest $request, IniciarSesionPanel $iniciarSesion): JsonResponse
    {
        $credenciales = $request->validated();

        if (! Auth::guard('interno')->attempt($credenciales)) {
            throw ValidationException::withMessages([
                'username' => ['Las credenciales no coinciden con ningún registro.'],
            ]);
        }

        $request->session()->regenerate();

        /** @var SecUser $usuario */
        $usuario = Auth::guard('interno')->user();

        $resultado = $iniciarSesion->ejecutar($usuario);

        return response()->json([
            'requiere_seleccion_rol' => $resultado->requiereSeleccion,
            'rol_activo_id' => $resultado->rolActivo?->id,
            'roles' => $resultado->rolesDisponibles->map(fn (SecRole $rol): array => [
                'id' => $rol->id,
                'name' => $rol->name,
                'description' => $rol->description,
            ])->values(),
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
