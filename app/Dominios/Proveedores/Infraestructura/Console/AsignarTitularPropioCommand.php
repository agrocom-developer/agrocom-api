<?php

namespace App\Dominios\Proveedores\Infraestructura\Console;

use App\Dominios\Proveedores\Dominio\TipoProveedor;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use App\Dominios\Seguridad\Contratos\LecturaCuentas;
use App\Dominios\Seguridad\Contratos\RegistroCuentas;
use Illuminate\Console\Command;

/**
 * `php artisan proveedores:titular-propio {username}`: asigna la cuenta que
 * administra el proveedor propio (Agrocom) y le da el rol `proveedor`, para que
 * registre desde la app a sus pilotos y drones. Agrocom existe como
 * proveedor desde el catálogo, pero sin titular (ADR 0028 y 0036): lo asigna el
 * dueño una sola vez. Idempotente.
 */
final class AsignarTitularPropioCommand extends Command
{
    protected $signature = 'proveedores:titular-propio {username : Login de la cuenta que administra al proveedor propio}';

    protected $description = 'Asigna la cuenta titular del proveedor propio (Agrocom)';

    public function handle(LecturaCuentas $cuentas, RegistroCuentas $registro): int
    {
        $usuarioId = $cuentas->idPorUsername((string) $this->argument('username'));

        if ($usuarioId === null) {
            $this->error('No existe una cuenta con ese login.');

            return self::FAILURE;
        }

        $propio = Proveedor::query()->where('tipo', TipoProveedor::Propio->value)->first();

        if ($propio === null) {
            $this->error('Todavía no existe el proveedor propio: corre el catálogo (db:seed).');

            return self::FAILURE;
        }

        if (Proveedor::query()->where('usuario_titular_id', $usuarioId)->where('id', '!=', $propio->id)->exists()) {
            $this->error('Esa cuenta ya es titular de otro proveedor.');

            return self::FAILURE;
        }

        $propio->usuario_titular_id = $usuarioId;
        $propio->save();
        $registro->agregarRol($usuarioId, 'proveedor');

        $this->info("La cuenta «{$this->argument('username')}» es la titular de {$propio->razon_social}.");

        return self::SUCCESS;
    }
}
