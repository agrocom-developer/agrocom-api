<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Logout del portal del cliente (HU-41, tarea 55), guard `cliente`.
 * Adaptador delgado (ADR 0008), mismo criterio que {@see SesionController}.
 *
 * El INGRESO no vive acá: desde el 16/9/2026 el portal no tiene URL de login
 * propia — `POST /login` ({@see SesionController::store()}) atiende a los dos
 * guards. Lo que sigue siendo propio del portal es cerrar SU guard.
 */
final class SesionPortalController
{
    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('cliente')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => __('seguridad.respuestas.sesion_finalizada')]);
    }
}
