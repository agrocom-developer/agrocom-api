<?php

namespace Database\Seeders\Catalogo;

use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Proveedores\Dominio\TipoProveedor;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use Illuminate\Database\Seeder;

/**
 * Agrocom existe como un proveedor de tipo `propio` (especificación §4.3, ADR
 * 0028): recibe la prioridad de asignación (ADR 0030). Nace `pendiente` y pasa
 * por el mismo flujo de aprobación que cualquier proveedor (ADR 0032, punto 6).
 * Sin titular: el dueño asigna la cuenta que lo administra con
 * `php artisan proveedores:titular-propio {username}`.
 *
 * Corre en todos los entornos, producción incluida (sin el proveedor propio no
 * hay a quién ofrecerle el trabajo primero). Idempotente: no pisa nada si ya
 * existe.
 */
class ProveedorPropioSeeder extends Seeder
{
    public const RAZON_SOCIAL = 'Agrocom SRL';

    public function run(): void
    {
        if (Proveedor::query()->where('tipo', TipoProveedor::Propio->value)->exists()) {
            return;
        }

        Proveedor::query()->create([
            'usuario_titular_id' => null,
            'tipo' => TipoProveedor::Propio,
            'razon_social' => self::RAZON_SOCIAL,
            'estado' => EstadoAprobacion::Pendiente,
        ]);
    }
}
