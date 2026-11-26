<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuentas y roles del panel (ADR 0046, puntos 4 y 5; plan de cuentas, fase A1).
 *
 * El panel lo usa solo el personal de Agrocom, con dos roles: `administrador`
 * (todo) y `operador` (lo que marque su matriz). Esta migración:
 *
 * 1. Suma `ambito` (`panel` | `app`) a `sec_role` y a `sec_permission`. Los
 *    roles `cliente`, `proveedor` y `piloto` y los permisos que ejercen las
 *    apps pasan a `app`: la pantalla de roles y la matriz ya no los ofrecen.
 *    TRANSITORIO: los roles de app siguen vivos porque el token del dispositivo
 *    resuelve permisos por ellos hasta la fase A5; se dan de baja ahí.
 * 2. Crea `administrador` y `operador` (si no existen) y le da al administrador
 *    todos los permisos del panel y al operador su preset de solo lectura.
 * 3. Da de baja LÓGICA los roles retirados del panel (`dueno`,
 *    `encargado_operaciones`, `jefe_campo`, `soporte`, `admin_plataforma`,
 *    `operario`) con sus permisos y asignaciones, y pasa a `administrador` a
 *    cada cuenta de panel que los tenía. No se borra ninguna fila.
 *
 * `down()` restaura los roles retirados con sus permisos y asignaciones, quita
 * las asignaciones a `administrador` de esas cuentas y deja las columnas
 * nuevas fuera; los roles `administrador` y `operador` quedan como filas (son
 * catálogo y no se borran).
 */
return new class extends Migration
{
    private const RETIRADOS = ['dueno', 'encargado_operaciones', 'jefe_campo', 'soporte', 'admin_plataforma', 'operario'];

    private const ROLES_DE_APP = ['cliente', 'proveedor', 'piloto'];

    /** Lo que ejercen las apps: la unión de los permisos de `config/permisos_app.php`. */
    private const PERMISOS_DE_APP = [
        'proveedores.cuenta.gestionar',
        'proveedores.cuenta.ver',
        'proveedores.documento.cargar',
        'solicitudes.cliente.gestionar',
        'solicitudes.terreno.gestionar',
        'solicitudes.solicitud.gestionar',
        'negociacion.mercado.ver',
        'negociacion.oferta.proveedor',
        'negociacion.oferta.cliente',
        'negociacion.seguimiento.registrar',
        'negociacion.seguimiento.ver',
        'negociacion.evidencia.subir',
        'negociacion.pago.confirmar',
        'negociacion.precio_final.confirmar',
        'negociacion.mensaje.usar',
        'negociacion.preferencias.gestionar',
        'calificaciones.calificacion.calificar_proveedor',
        'calificaciones.calificacion.calificar_cliente',
        'calificaciones.calificacion.ver_recibidas',
        'calificaciones.calificacion.reportar',
        'disputas.disputa.gestionar_cliente',
        'disputas.disputa.gestionar_proveedor',
    ];

    private const PRESET_OPERADOR = [
        'seguridad.dashboard.ver',
        'solicitudes.solicitud.ver',
        'solicitudes.cliente.ver',
        'proveedores.proveedor.ver',
        'proveedores.piloto.ver',
        'flota.dron.ver',
        'calificaciones.calificacion.ver',
        'disputas.disputa.ver',
    ];

    public function up(): void
    {
        Schema::table('sec_role', function (Blueprint $table) {
            $table->string('ambito', 10)->default('panel')->after('description');
        });

        Schema::table('sec_permission', function (Blueprint $table) {
            $table->string('ambito', 10)->default('panel')->after('description');
        });

        if (DB::getDriverName() === 'pgsql') {
            $p = DB::getTablePrefix();

            DB::statement("ALTER TABLE {$p}sec_role ADD CONSTRAINT {$p}sec_role_ambito_chk CHECK (ambito IN ('panel', 'app'))");
            DB::statement("ALTER TABLE {$p}sec_permission ADD CONSTRAINT {$p}sec_permission_ambito_chk CHECK (ambito IN ('panel', 'app'))");
        }

        DB::table('sec_permission')->whereIn('code', self::PERMISOS_DE_APP)->update(['ambito' => 'app']);
        DB::table('sec_role')->whereIn('name', self::ROLES_DE_APP)->update(['ambito' => 'app']);

        // Una instalación nueva no tiene roles: los siembra `SeguridadSeeder`.
        if (! DB::table('sec_role')->exists()) {
            return;
        }

        $ahora = now();
        $administrador = $this->asegurarRol('administrador', 'Administrador del panel: ve y hace todo, incluidos usuarios, roles y configuración.', $ahora);
        $operador = $this->asegurarRol('operador', 'Operador del panel: ve y hace lo que el administrador marca en la matriz de este rol.', $ahora);

        $this->otorgarTodoElPanel($administrador, $ahora);
        $this->otorgarPreset($operador, $ahora);
        $this->retirarRolesDelPanel($administrador, $ahora);
    }

    public function down(): void
    {
        if (DB::table('sec_role')->exists()) {
            $administrador = DB::table('sec_role')->where('name', 'administrador')->value('id');
            $ids = DB::table('sec_role')->whereIn('name', self::RETIRADOS)->pluck('id')->all();

            // Las cuentas que esta migración pasó a `administrador` son las que
            // recuperan una asignación retirada.
            $conRolRetirado = DB::table('sec_user_role')->whereIn('id_role', $ids)->whereNotNull('deleted_at')->pluck('id_user')->unique()->all();

            if ($administrador !== null && $conRolRetirado !== []) {
                DB::table('sec_user_role')
                    ->where('id_role', $administrador)
                    ->whereIn('id_user', $conRolRetirado)
                    ->whereNull('deleted_at')
                    ->update(['deleted_at' => now(), 'updated_at' => now()]);
            }

            DB::table('sec_user_role')->whereIn('id_role', $ids)->whereNotNull('deleted_at')->update(['deleted_at' => null, 'updated_at' => now()]);
            DB::table('sec_role_permission')->whereIn('id_role', $ids)->whereNotNull('deleted_at')->update(['deleted_at' => null, 'updated_at' => now()]);
            DB::table('sec_role')->whereIn('id', $ids)->whereNotNull('deleted_at')->update(['deleted_at' => null, 'updated_at' => now()]);
        }

        if (DB::getDriverName() === 'pgsql') {
            $p = DB::getTablePrefix();

            DB::statement("ALTER TABLE {$p}sec_permission DROP CONSTRAINT IF EXISTS {$p}sec_permission_ambito_chk");
            DB::statement("ALTER TABLE {$p}sec_role DROP CONSTRAINT IF EXISTS {$p}sec_role_ambito_chk");
        }

        Schema::table('sec_permission', function (Blueprint $table) {
            $table->dropColumn('ambito');
        });

        Schema::table('sec_role', function (Blueprint $table) {
            $table->dropColumn('ambito');
        });
    }

    private function asegurarRol(string $nombre, string $descripcion, mixed $ahora): int
    {
        $existente = DB::table('sec_role')->where('name', $nombre)->first();

        if ($existente !== null) {
            DB::table('sec_role')->where('id', $existente->id)->update(['deleted_at' => null, 'state' => true, 'ambito' => 'panel']);

            return (int) $existente->id;
        }

        return (int) DB::table('sec_role')->insertGetId([
            'name' => $nombre,
            'description' => $descripcion,
            'ambito' => 'panel',
            'state' => true,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }

    /** El administrador tiene todos los permisos vivos del panel. */
    private function otorgarTodoElPanel(int $rolId, mixed $ahora): void
    {
        $ids = DB::table('sec_permission')->whereNull('deleted_at')->where('ambito', 'panel')->pluck('id')->all();

        $this->otorgar($rolId, $ids, $ahora);
    }

    private function otorgarPreset(int $rolId, mixed $ahora): void
    {
        // Solo si el rol no tiene ningún otorgamiento previo (vivo o dado de baja): lo que el administrador cambie después de crearlo no se pisa.
        if (DB::table('sec_role_permission')->where('id_role', $rolId)->exists()) {
            return;
        }

        $ids = DB::table('sec_permission')->whereNull('deleted_at')->whereIn('code', self::PRESET_OPERADOR)->pluck('id')->all();

        $this->otorgar($rolId, $ids, $ahora);
    }

    /** @param  list<int|string>  $permisoIds */
    private function otorgar(int $rolId, array $permisoIds, mixed $ahora): void
    {
        $vivos = DB::table('sec_role_permission')->where('id_role', $rolId)->whereNull('deleted_at')->pluck('id_permission')->map(fn ($id): int => (int) $id)->all();
        $muertos = DB::table('sec_role_permission')->where('id_role', $rolId)->whereNotNull('deleted_at')->pluck('id_permission')->map(fn ($id): int => (int) $id)->all();

        foreach ($permisoIds as $permisoId) {
            $permisoId = (int) $permisoId;

            if (in_array($permisoId, $vivos, true)) {
                continue;
            }

            if (in_array($permisoId, $muertos, true)) {
                DB::table('sec_role_permission')->where('id_role', $rolId)->where('id_permission', $permisoId)->update(['deleted_at' => null, 'updated_at' => $ahora]);

                continue;
            }

            DB::table('sec_role_permission')->insert([
                'id_role' => $rolId,
                'id_permission' => $permisoId,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    /** Baja lógica de los roles retirados y paso de sus cuentas a `administrador`. */
    private function retirarRolesDelPanel(int $administradorId, mixed $ahora): void
    {
        $retirados = DB::table('sec_role')->whereIn('name', self::RETIRADOS)->whereNull('deleted_at')->pluck('id')->all();

        if ($retirados === []) {
            return;
        }

        $cuentas = DB::table('sec_user_role')->whereIn('id_role', $retirados)->whereNull('deleted_at')->pluck('id_user')->unique()->all();
        $yaAdministradoras = DB::table('sec_user_role')->where('id_role', $administradorId)->whereNull('deleted_at')->pluck('id_user')->map(fn ($id): int => (int) $id)->all();

        foreach ($cuentas as $cuentaId) {
            if (in_array((int) $cuentaId, $yaAdministradoras, true)) {
                continue;
            }

            DB::table('sec_user_role')->insert([
                'id_user' => $cuentaId,
                'id_role' => $administradorId,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        DB::table('sec_user_role')->whereIn('id_role', $retirados)->whereNull('deleted_at')->update(['deleted_at' => $ahora, 'updated_at' => $ahora]);
        DB::table('sec_role_permission')->whereIn('id_role', $retirados)->whereNull('deleted_at')->update(['deleted_at' => $ahora, 'updated_at' => $ahora]);
        DB::table('sec_role')->whereIn('id', $retirados)->update(['deleted_at' => $ahora, 'updated_at' => $ahora]);
    }
};
