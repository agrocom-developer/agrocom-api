<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El cliente pasa al ciclo único de aprobación (ADR 0046, punto 11; plan de cuentas, fase A4):
 * `pendiente → aprobado ⇄ suspendido`, `pendiente → rechazado → pendiente` y cualquiera → `baja`.
 * Un cliente nuevo nace `pendiente`; solo uno `aprobado` publica solicitudes y acepta o contraoferta.
 *
 * - `sol_clientes.estado` deja de ser `activo | suspendido`: los clientes `activo` pasan a `aprobado`
 *   (ya operaban, no se les retira nada) y `suspendido` queda igual. El valor por defecto es `pendiente`.
 * - `aprobado_por` y `aprobado_en`: quién aprobó y cuándo, como en proveedores y pilotos.
 * - `sol_cliente_historial_estados`: cada transición con autor, motivo y momento (solo se inserta), como
 *   `prv_historial_estados` lo hace para proveedores y pilotos. Es propia de `Solicitudes`: un módulo solo
 *   escribe sus tablas.
 *
 * `down()` devuelve el esquema anterior: `aprobado` vuelve a `activo` y lo demás (`pendiente`, `rechazado`,
 * `baja`), que no tenía equivalente, a `suspendido`, el estado que no opera.
 */
return new class extends Migration
{
    public function up(): void
    {
        $p = DB::getTablePrefix();

        Schema::table('sol_clientes', function (Blueprint $table) {
            $table->unsignedBigInteger('aprobado_por')->nullable();
            $table->timestamp('aprobado_en')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$p}sol_clientes DROP CONSTRAINT IF EXISTS {$p}sol_clientes_estado_chk");
        }

        DB::table('sol_clientes')->where('estado', 'activo')->update(['estado' => 'aprobado', 'aprobado_en' => DB::raw('created_at')]);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$p}sol_clientes ADD CONSTRAINT {$p}sol_clientes_estado_chk CHECK (estado IN ('pendiente', 'aprobado', 'suspendido', 'rechazado', 'baja'))");
            DB::statement("ALTER TABLE {$p}sol_clientes ALTER COLUMN estado SET DEFAULT 'pendiente'");
        }

        Schema::create('sol_cliente_historial_estados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('sol_clientes')->restrictOnDelete();
            $table->string('estado_anterior', 12)->nullable();
            $table->string('estado_nuevo', 12);
            $table->string('accion', 12);
            $table->string('origen', 14);
            $table->foreignId('actor_usuario_id')->nullable()->constrained('sec_user')->restrictOnDelete();
            $table->text('motivo')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['cliente_id', 'id'], 'sol_cliente_historial_estados_cliente_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$p}sol_cliente_historial_estados ADD CONSTRAINT {$p}sol_cliente_historial_estados_origen_chk CHECK (origen IN ('administrador', 'solicitante', 'sistema'))");
        }
    }

    public function down(): void
    {
        $p = DB::getTablePrefix();

        Schema::dropIfExists('sol_cliente_historial_estados');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$p}sol_clientes DROP CONSTRAINT IF EXISTS {$p}sol_clientes_estado_chk");
        }

        DB::table('sol_clientes')->where('estado', 'aprobado')->update(['estado' => 'activo']);
        DB::table('sol_clientes')->whereIn('estado', ['pendiente', 'rechazado', 'baja'])->update(['estado' => 'suspendido']);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$p}sol_clientes ADD CONSTRAINT {$p}sol_clientes_estado_chk CHECK (estado IN ('activo', 'suspendido'))");
            DB::statement("ALTER TABLE {$p}sol_clientes ALTER COLUMN estado SET DEFAULT 'activo'");
        }

        Schema::table('sol_clientes', function (Blueprint $table) {
            $table->dropColumn(['aprobado_por', 'aprobado_en']);
        });
    }
};
