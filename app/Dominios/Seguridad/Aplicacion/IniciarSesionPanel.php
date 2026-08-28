<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserPreferencia;
use Illuminate\Support\Facades\Session;

/**
 * Resuelve el rol activo inmediatamente después de autenticar credenciales
 * en el panel (ADR 0004, extensión 27/8/2026, punto 3). La verificación de
 * `username`+password en sí (guard `interno`, `Auth::attempt()`) es
 * responsabilidad del controlador — es plumbing de autenticación, no una
 * regla de negocio de `Seguridad`; lo que sí es una regla de negocio, y por
 * eso vive acá, es qué pasa con el rol activo una vez que el usuario ya está
 * autenticado:
 *
 * - **Un solo rol vivo** → se activa solo, sin pedir selección.
 * - **Más de un rol vivo, con rol preferido vivo** ("Entrar siempre con este
 *   rol", quinta vuelta — maqueta 5c) → se activa el preferido solo, sin
 *   pedir selección; el selector reaparece únicamente al cambiar de rol
 *   explícitamente desde el menú. Si el preferido dejó de estar vivo
 *   (revocado/desactivado), se ignora y se pide selección como siempre —
 *   nunca un fallback silencioso a otro rol.
 * - **Más de un rol vivo, sin preferido** → no se fija ningún rol activo
 *   todavía; el llamador (controlador de login) debe pedir al usuario que
 *   elija antes de dejarlo entrar al panel (no hay panel sin rol activo
 *   resuelto).
 * - **Cero roles vivos** → mismo resultado que "más de uno" (sin fijar rol,
 *   `requiereSeleccion = true`) pero con la lista de roles disponibles
 *   vacía: no hay panel que mostrar, nunca un fallback a permitir todo.
 */
final class IniciarSesionPanel
{
    public function __construct(private readonly ElegirRolActivo $elegirRolActivo) {}

    public function ejecutar(SecUser $usuario): ResultadoInicioSesion
    {
        $idsRolesVivos = $usuario->idsDeRolesActivos();

        if (count($idsRolesVivos) === 1) {
            $rol = $this->elegirRolActivo->ejecutar($usuario, $idsRolesVivos[0]);

            return ResultadoInicioSesion::conRolActivo($rol);
        }

        $idRolPreferido = SecUserPreferencia::query()
            ->where('user_id', $usuario->id)
            ->value('rol_preferido_id');

        if ($idRolPreferido !== null && in_array((int) $idRolPreferido, $idsRolesVivos, true)) {
            $rol = $this->elegirRolActivo->ejecutar($usuario, (int) $idRolPreferido);

            return ResultadoInicioSesion::conRolActivo($rol);
        }

        // Sesión vieja de antes de esta feature, o primer login de un
        // usuario con 0/2+ roles: no hay rol activo válido que conservar.
        Session::forget('sec_rol_activo_id');

        $rolesDisponibles = SecRole::query()
            ->whereIn('id', $idsRolesVivos)
            ->where('state', true)
            ->orderBy('name')
            ->get();

        return ResultadoInicioSesion::requiereSeleccion($rolesDisponibles);
    }
}
