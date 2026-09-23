<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Dominio\Excepciones\PermisoDenegado;
use App\Dominios\Seguridad\Dominio\Excepciones\VistaComoNoPermitida;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Dominio\VistaComoActiva;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecVistaComo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * Abre una vista "como otro usuario" para un administrador de la plataforma
 * (tarea 140): a partir del request siguiente, el panel o el portal se
 * evalúan con el alcance de la cuenta observada, en modo estrictamente de
 * lectura (ver `AplicarVistaComo`).
 *
 * Qué hace y qué NO hace:
 * - Deja la sesión de autenticación del administrador exactamente como está.
 *   No hay un login como el otro: solo una bandera de sesión
 *   ({@see VistaComoActiva}) y una fila en `sec_vistas_como`, cuya creación
 *   la bitácora registra sola (invariante 9 de CLAUDE.md) con el
 *   administrador REAL como actor.
 * - No la usa para actuar en nombre de nadie: es solo mirar. La suplantación
 *   con escritura es un problema distinto y no está pedida.
 *
 * El permiso (`seguridad.usuario.ver_como`) se revalida acá contra el ROL
 * ACTIVO del administrador — el controlador ya lo verificó, pero un caso de
 * uso no confía en quien lo llama (mismo criterio que {@see AsignarRolesUsuario}).
 */
final class IniciarVistaComo
{
    private const PERMISO = 'seguridad.usuario.ver_como';

    /**
     * @param  int  $idRolActivoAdmin  rol activo del administrador en su sesión.
     * @param  int|null  $idRolObservado  rol bajo el que se ve una cuenta interna;
     *                                    opcional solo si tiene uno único.
     *
     * @throws PermisoDenegado si el administrador no tiene el permiso en su rol activo.
     * @throws VistaComoNoPermitida si la sesión ya tiene una vista abierta, la cuenta no
     *                              está disponible o el rol no es válido.
     */
    public function ejecutar(SecUser $admin, int $idRolActivoAdmin, int $idUsuarioObservado, ?int $idRolObservado): VistaComoActiva
    {
        if (
            $admin->type !== TipoUsuario::Interno
            || ! in_array($idRolActivoAdmin, $admin->idsDeRolesActivos(), true)
            || ! $admin->tienePermisoEnRol(self::PERMISO, $idRolActivoAdmin)
        ) {
            throw PermisoDenegado::porFaltaDePermiso(self::PERMISO);
        }

        if (Session::has(VistaComoActiva::CLAVE_SESION)) {
            throw VistaComoNoPermitida::yaHayUnaAbierta();
        }

        // `find()` sobre la base `SecUser` (sin el scope de tipo de cada
        // guard): quien se mira puede ser interno o de portal. Una cuenta dada
        // de baja no aparece (soft delete), y una bloqueada o la propia se
        // rechazan con el mismo mensaje — no se distingue cuál de las tres.
        $observado = SecUser::query()->find($idUsuarioObservado);

        if ($observado === null || ! $observado->state || $observado->id === $admin->id) {
            throw VistaComoNoPermitida::cuentaNoDisponible();
        }

        $idRol = $this->rolAObservar($observado, $idRolObservado);

        $registro = DB::transaction(fn (): SecVistaComo => SecVistaComo::query()->create([
            'admin_id' => $admin->id,
            'usuario_id' => $observado->id,
            'tipo' => $observado->type,
            'rol_id' => $idRol,
            'iniciada_at' => now(),
        ]));

        $vista = new VistaComoActiva(
            registroId: $registro->id,
            tipo: $observado->type,
            adminId: $admin->id,
            adminRolId: $idRolActivoAdmin,
            usuarioId: $observado->id,
            rolId: $idRol,
        );

        Session::put(VistaComoActiva::CLAVE_SESION, $vista->paraSesion());

        return $vista;
    }

    /**
     * Una cuenta de portal no tiene rol (ADR 0004) pero sí necesita contrato:
     * sin él no hay nada que mirar. Una interna se ve bajo UN rol — el que
     * eligió el administrador, o el único que tiene.
     */
    private function rolAObservar(SecUser $observado, ?int $idRolElegido): ?int
    {
        if ($observado->type === TipoUsuario::Cliente) {
            return $observado->contrato_id !== null
                ? null
                : throw VistaComoNoPermitida::sinContrato();
        }

        $rolesVivos = $observado->idsDeRolesActivos();

        if ($rolesVivos === []) {
            throw VistaComoNoPermitida::rolNoDisponible();
        }

        if ($idRolElegido === null) {
            return count($rolesVivos) === 1 ? $rolesVivos[0] : throw VistaComoNoPermitida::rolRequerido();
        }

        return in_array($idRolElegido, $rolesVivos, true) ? $idRolElegido : throw VistaComoNoPermitida::rolNoDisponible();
    }
}
