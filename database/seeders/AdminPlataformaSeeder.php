<?php

namespace Database\Seeders;

use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraAutoria;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Database\Seeder;

/**
 * Tarea 100: la única cuenta con la que arranca una instalación nueva de
 * `local`/`staging` — el resto de los datos (clientes, contratos, personas,
 * órdenes...) los carga el usuario a mano desde el panel, ya no hay familia
 * `Demo/`.
 *
 * Top-level, NO bajo `Catalogo/` (que corre en producción y no debe traer una
 * contraseña fija en código) ni bajo un `Demo/` nuevo (eso es justo lo que
 * esta tarea elimina). Gateado a `local`/`staging` desde
 * {@see DatabaseSeeder}, igual que corría la familia `Demo/` antes.
 *
 * Autoría NULL en las columnas de auditoría: nadie "creó" a este usuario, lo
 * siembra el sistema — mismo criterio que `SeguridadSeeder` (sin usuario
 * autenticado, {@see RegistraAutoria} deja `created_by`/`updated_by` en
 * NULL).
 *
 * Idempotente: `firstOrCreate` por `username`, y la asignación del rol solo
 * crea el pivote si no existe ya — vivo o soft-deleteado (mismo motivo que
 * {@see SeguridadSeeder::asignar()}: si alguien revocó el rol a mano desde
 * el panel, una corrida nueva del seeder no debe reponerlo).
 */
class AdminPlataformaSeeder extends Seeder
{
    private const USERNAME = 'miguelo';

    /** Contraseña fija, no-productiva — este seeder nunca corre en producción. */
    private const PASSWORD = '0000';

    public function run(): void
    {
        $rol = SecRole::query()->where('name', 'admin_plataforma')->first();

        if ($rol === null) {
            return; // catálogo de roles no sembrado — nada que asignar
        }

        $usuario = SecUser::query()->firstOrCreate(
            ['username' => self::USERNAME],
            [
                'name' => 'Miguel Angel Escobar Lazcano',
                'email' => null,
                'password' => self::PASSWORD,
                'type' => TipoUsuario::Interno,
                'persona_id' => null,
            ],
        );

        $yaAsignado = SecUserRole::withTrashed()
            ->where('id_user', $usuario->id)
            ->where('id_role', $rol->id)
            ->exists();

        if ($yaAsignado) {
            return;
        }

        (new SecUserRole([
            'id_user' => $usuario->id,
            'id_role' => $rol->id,
        ]))->save();
    }
}
