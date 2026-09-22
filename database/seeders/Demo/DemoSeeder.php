<?php

namespace Database\Seeders\Demo;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Datos de demostración para recorrer el panel pantalla por pantalla con
 * TODOS los objetos del esquema poblados (pedido del dueño, 22/9/2026, tras
 * la reestructuración del modelo de datos y del rediseño de UI/UX).
 *
 * Se apoya en lo que ya cargó el dueño a mano en el compose (GAMELERA SA,
 * la campaña VERANO2027, las cuadrillas EQ1/EQ2, el dron DRONT50, la orden
 * y la orden de trabajo #1) y suma alrededor: no toca ni borra nada de eso.
 *
 * Reglas de la familia:
 * - Idempotente: cada seeder mira un centinela (un NIT, un CI, un
 *   `uuid_cliente` determinista) y si ya está, no vuelve a sembrar.
 * - Los estados pasan por las máquinas de estado y los casos de uso reales
 *   (invariantes 3 y 7): validar una sesión genera el devengo, firmar un
 *   acta genera el reporte técnico, cerrar una orden de mantenimiento
 *   consume stock y registra el gasto.
 * - La autoría y la bitácora salen solas: se deja autenticado al admin de
 *   plataforma durante la siembra, así `RegistraAutoria` y
 *   `BitacoraObserver` completan `created_by` y `plt_bitacoras` como en una
 *   sesión del panel.
 *
 * No corre desde `DatabaseSeeder` a propósito (tarea 100: la instalación
 * nueva arranca con el mínimo dato estructural). Se invoca a mano:
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
        $this->call(PersonalDemoSeeder::class);
        $this->call(FlotaDemoSeeder::class);
        $this->call(CuadrillasDemoSeeder::class);
        $this->call(CarteraDemoSeeder::class);
        $this->call(OperacionDemoSeeder::class);
        $this->call(FinanzasDemoSeeder::class);
    }
}
