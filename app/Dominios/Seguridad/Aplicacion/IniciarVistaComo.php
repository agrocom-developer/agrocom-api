<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Contratos\TipoCuenta;
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
 * (tarea 140): a partir del request siguiente, el panel se evalúa con el
 * alcance de la cuenta de panel observada, en modo estrictamente de
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
     * @param  int|null  $idRolObservado  rol bajo el que se ve la cuenta;
     *                                    opcional solo si tiene uno único.
     *
     * @throws PermisoDenegado si el administrador no tiene el permiso en su rol activo.
     * @throws VistaComoNoPermitida si la sesión ya tiene una vista abierta, la cuenta no
     *                              está disponible o el rol no es válido.
     */
    public function ejecutar(SecUser $admin, int $idRolActivoAdmin, int $idUsuarioObservado, ?int $idRolObservado): VistaComoActiva
    {
        if (
            $admin->tipo_cuenta !== TipoCuenta::Panel
            || ! in_array($idRolActivoAdmin, $admin->idsDeRolesActivos(), true)
            || ! $admin->tienePermisoEnRol(self::PERMISO, $idRolActivoAdmin)
        ) {
            throw PermisoDenegado::porFaltaDePermiso(self::PERMISO);
        }

        if (Session::has(VistaComoActiva::CLAVE_SESION)) {
            throw VistaComoNoPermitida::yaHayUnaAbierta();
        }

        // `find()` sobre la base `SecUser` (sin el scope de tipo de cada
        // guard). Una cuenta dada de baja no aparece (soft delete), y una
        // bloqueada o la propia se rechazan con el mismo mensaje — no se
        // distingue cuál de las tres.
        $observado = SecUser::query()->find($idUsuarioObservado);

        if ($observado === null || ! $observado->state || $observado->id === $admin->id) {
            throw VistaComoNoPermitida::cuentaNoDisponible();
        }

        // «Ver como» solo sobre cuentas de panel (ADR 0046, punto 5): las cuentas
        // de app (cliente y proveedor) tienen datos personales que el panel no
        // recorre en nombre de otro.
        if ($observado->tipo_cuenta !== TipoCuenta::Panel) {
            throw VistaComoNoPermitida::soloCuentasDePanel();
        }

        $idRol = $this->rolAObservar($observado, $idRolObservado);

        $registro = DB::transaction(fn (): SecVistaComo => SecVistaComo::query()->create([
            'admin_id' => $admin->id,
            'usuario_id' => $observado->id,
            'tipo' => TipoUsuario::Interno,
            'rol_id' => $idRol,
            'iniciada_at' => now(),
        ]));

        $vista = new VistaComoActiva(
            registroId: $registro->id,
            tipo: TipoUsuario::Interno,
            adminId: $admin->id,
            adminRolId: $idRolActivoAdmin,
            usuarioId: $observado->id,
            rolId: $idRol,
        );

        Session::put(VistaComoActiva::CLAVE_SESION, $vista->paraSesion());

        return $vista;
    }

    /** Una cuenta de panel se ve bajo UN rol — el que eligió el administrador, o el único que tiene. */
    private function rolAObservar(SecUser $observado, ?int $idRolElegido): int
    {
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
