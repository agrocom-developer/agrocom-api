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

        // piloto, auxiliar, jefe_campo: sin permisos de seguridad (diseño §2).
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
