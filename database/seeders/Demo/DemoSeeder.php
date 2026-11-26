<?php

namespace Database\Seeders\Demo;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Datos de demostración del marketplace para recorrer el panel y las apps:
 * plataforma (configuración y zonas), los drones operativos que se enlazan a la
 * flota, clientes, proveedores y pilotos en cada estado de aprobación, y las
 * solicitudes en cada estado con sus ofertas, chat, fotos y ubicaciones.
 * No hay datos del sistema anterior (personal, bases, cuadrillas, baterías ni
 * cuentas de dueño o jefe de campo).
 *
 * Reglas de la familia:
 * - Idempotente: cada seeder mira un centinela (un NIT, un CI, un
 *   `uuid_cliente` determinista) y si ya está, no vuelve a sembrar.
 * - Los estados pasan por las máquinas de estado y los casos de uso reales
 *   (invariante 7): firmar un acta genera el reporte técnico.
 * - La autoría y la bitácora salen solas: se deja autenticado al admin de
 *   plataforma durante la siembra, así `RegistraAutoria` y
 *   `BitacoraObserver` completan `created_by` y `plt_bitacoras` como en una
 *   sesión del panel.
 *
 * No corre desde `DatabaseSeeder` a propósito (tarea 100: la instalación
 * nueva arranca con el mínimo dato estructural). Se invoca con `bin/demo seed`
 * o a mano:
 *
 *   docker compose exec -T app php artisan db:seed --class='Database\Seeders\Demo\DemoSeeder'
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'staging'])) {
            return;
        }

        $admin = SecUser::query()->where('username', 'miguelo')->first()
            ?? SecUser::query()->orderBy('id')->first();

        if ($admin === null) {
            $this->command?->warn('No hay ningún usuario en sec_user: corre primero DatabaseSeeder.');

            return;
        }

        Auth::guard('interno')->setUser($admin);

        $this->call(PlataformaDemoSeeder::class);
        // Los drones operativos que el marketplace enlaza a su flota.
        $this->call(FlotaDemoSeeder::class);
        // Marketplace: clientes, proveedores, pilotos y drones aprobados.
        $this->call(ProveedoresDemoSeeder::class);
        // Cada piloto del marketplace necesita su persona para abrir sesiones.
        $this->call(PersonasDeOperariosDemoSeeder::class);
        // Negociación completa para las apps (solicitudes en cada estado, ofertas, chat, fotos y
        // ubicación), con las marcas de hora relativas a ahora, y vigencias que se renuevan al re-sembrar.
        $this->call(NegociacionDemoSeeder::class);
        $this->call(RenovarVigenciaDemoSeeder::class);
    }
}
