<?php

namespace Database\Seeders\Catalogo;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRolePermission;
use Illuminate\Database\Seeder;

/**
 * Catálogo de roles y permisos (HU-01, diseño `modulos-roles` §2): corre en
 * todos los entornos, producción incluida — sin roles ni permisos no hay con
 * qué autorizar al primer usuario.
 *
 * Sin autor explícito (`created_by`/`updated_by` quedan NULL): a diferencia
 * del seeder Demo (que sí asigna un usuario autor porque simula un flujo de
 * negocio), esto es dato de catálogo — nadie "creó" el rol `dueno`, lo
 * sembró el sistema antes de que exista ningún usuario.
 *
 * Idempotente vía `firstOrCreate`: correrlo de nuevo no duplica filas ni
 * pisa `description`/`state` si ya fueron editados a mano.
 */
class SeguridadSeeder extends Seeder
{
    /** @var array<string, string> */
    private const ROLES = [
        'piloto' => 'Piloto de dron: ejecuta sesiones de vuelo en campo.',
        'auxiliar' => 'Auxiliar de campo: apoya la preparación y logística de la sesión.',
        'jefe_campo' => 'Jefe de campo: coordina la cuadrilla y valida sesiones ajenas.',
        'encargado_operaciones' => 'Encargado de operaciones: administra usuarios, órdenes y planificación.',
        'dueno' => 'Dueño de Agrocom SRL: acceso total, incluida la gestión de otros dueños.',
    ];

    /** @var array<string, string> */
    private const PERMISOS = [
        'seguridad.usuario.ver' => 'Ver listado/detalle de usuarios',
        'seguridad.usuario.crear' => 'Crear usuario y asignarle roles (excepto dueño)',
        'seguridad.usuario.editar' => 'Editar datos y reasignar roles de un usuario (excepto dueño)',
        'seguridad.usuario.bloquear' => 'Bloquear/desbloquear (toggle de state, no es baja)',
        'seguridad.usuario.eliminar' => 'Baja lógica (soft delete)',
        'seguridad.usuario.asignar_rol_dueno' => 'Asignar o quitar el rol dueño a cualquier usuario',
        // HU-03: ver y revocar sesiones de la app de campo. Separados a
        // propósito — mirar quién tiene sesión abierta y dejar a alguien
        // afuera en medio de una jornada de vuelo no son la misma
        // responsabilidad.
        'seguridad.dispositivo.ver' => 'Ver los dispositivos con sesión abierta en la app de campo',
        'seguridad.dispositivo.revocar' => 'Revocar el acceso de un dispositivo de campo',
        // HU-20: autorizar una versión del APK para distribución (RC). Solo
        // el dueño — ningún RC se actualiza sin su visto bueno.
        'distribucion.version.autorizar' => 'Autorizar una versión del APK para distribución',
        // HU-05 (tarea 13): ver el listado de trabajos/sesiones del panel —
        // "hasta que el jefe lo vea en el panel" (prompt de la tarea). Un
        // único permiso de lectura: el detalle con evidencias y los filtros
        // llegan con HU-15.
        'operaciones.trabajo.ver' => 'Ver el listado de trabajos y sesiones en el panel',
        // HU-14 (tarea 14): cola de validación — aprobar o rechazar una
        // sesión cerrada. Un único permiso gatea listar y decidir (mismo
        // criterio que `distribucion.version.autorizar`): la policy
        // validador≠piloto (invariante 4) rige la fila puntual, no la
        // visibilidad de la pantalla.
        'operaciones.sesion.validar' => 'Validar o rechazar sesiones cerradas desde el panel',
    ];

    /** @var list<string> Todo, salvo asignar_rol_dueno (diseño §2). */
    private const PERMISOS_ENCARGADO_OPERACIONES = [
        'seguridad.usuario.ver',
        'seguridad.usuario.crear',
        'seguridad.usuario.editar',
        'seguridad.usuario.bloquear',
        'seguridad.usuario.eliminar',
        // Es quien administra la operación diaria: si un piloto pierde el
        // teléfono en campo, tiene que poder cortarle el acceso sin
        // escalar al dueño (HU-03).
        'seguridad.dispositivo.ver',
        'seguridad.dispositivo.revocar',
        // Administra órdenes y planificación (diseño §2): ve qué trabajos y
        // sesiones se cerraron en el panel, igual que el jefe de campo.
        'operaciones.trabajo.ver',
        // HU-14: administra la operación diaria, así que también puede
        // destrabar la cola de validación — mismo criterio que trabajo.ver.
        'operaciones.sesion.validar',
    ];

    /**
     * Coordina la cuadrilla y valida sesiones ajenas (diseño §2) — necesita
     * ver qué se cerró en el panel para poder coordinar la jornada siguiente,
     * y (HU-14) aprobar o rechazar sesiones cerradas.
     *
     * @var list<string>
     */
    private const PERMISOS_JEFE_CAMPO = [
        'operaciones.trabajo.ver',
        'operaciones.sesion.validar',
    ];

    public function run(): void
    {
        $roles = collect(self::ROLES)->mapWithKeys(
            fn (string $description, string $name) => [$name => $this->rol($name, $description)],
        );

        $permisos = collect(self::PERMISOS)->mapWithKeys(
            fn (string $description, string $code) => [$code => $this->permiso($code, $description)],
        );

        // dueno: todos los permisos del catálogo, sin excepción (diseño §2).
        $this->asignar($roles['dueno'], $permisos->values()->all());

        // encargado_operaciones: todo salvo asignar_rol_dueno.
        $this->asignar(
            $roles['encargado_operaciones'],
            $permisos->only(self::PERMISOS_ENCARGADO_OPERACIONES)->values()->all(),
        );

        // jefe_campo: solo lo suyo (diseño §2) — antes ninguno, ahora ver
        // trabajos/sesiones (HU-05, tarea 13).
        $this->asignar(
            $roles['jefe_campo'],
            $permisos->only(self::PERMISOS_JEFE_CAMPO)->values()->all(),
        );

        // piloto, auxiliar: sin permisos de seguridad ni de panel (diseño §2).
    }

    private function rol(string $name, string $description): SecRole
    {
        return SecRole::query()->firstOrCreate(
            ['name' => $name],
            ['description' => $description, 'state' => true],
        );
    }

    private function permiso(string $code, string $description): SecPermission
    {
        return SecPermission::query()->firstOrCreate(
            ['code' => $code],
            ['description' => $description, 'state' => true],
        );
    }

    /** @param list<SecPermission> $permisos */
    private function asignar(SecRole $rol, array $permisos): void
    {
        foreach ($permisos as $permiso) {
            $yaAsignado = SecRolePermission::query()
                ->where('id_role', $rol->id)
                ->where('id_permission', $permiso->id)
                ->exists();

            if ($yaAsignado) {
                continue;
            }

            (new SecRolePermission([
                'id_role' => $rol->id,
                'id_permission' => $permiso->id,
            ]))->save();
        }
    }
}
