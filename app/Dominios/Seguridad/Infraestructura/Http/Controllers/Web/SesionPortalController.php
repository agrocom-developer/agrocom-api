<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Infraestructura\Http\Requests\IniciarSesionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Login/logout del portal del cliente (HU-41, tarea 55), guard `cliente`.
 * Adaptador delgado (ADR 0008), mismo criterio que {@see SesionController}
 * pero sin resolución de rol activo: una cuenta de portal no tiene
 * `sec_user_role` (ADR 0004), así que no hay nada que elegir tras el login —
 * a diferencia del panel interno, acá no hace falta un caso de uso propio
 * (`IniciarSesionPanel` es del guard `interno`, no se reusa).
 *
 * Reusa `IniciarSesionRequest`: valida `username`+password sin conocer el
 * guard, misma forma exacta que necesita este endpoint.
 */
final class SesionPortalController
{
    public function store(IniciarSesionRequest $request): JsonResponse
    {
        $credenciales = $request->validated();

        // `state` como condición extra, mismo criterio que SesionController:
        // rechaza una cuenta bloqueada con el mismo mensaje genérico que una
        // credencial incorrecta.
        if (! Auth::guard('cliente')->attempt([...$credenciales, 'state' => true])) {
            throw ValidationException::withMessages([
                'username' => ['Las credenciales no coinciden con ningún registro.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json(['message' => 'Sesión iniciada.']);
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('cliente')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sesión finalizada.']);
    }
}
