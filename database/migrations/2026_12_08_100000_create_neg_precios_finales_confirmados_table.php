<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `neg_precios_finales_confirmados`: el cliente, que es quien paga, confirma el precio
 * final de un servicio cerrado por el piloto (hectáreas ejecutadas por precio acordado)
 * o abre una disputa. Registro de un solo escritor (el cliente de la solicitud), único
 * por solicitud y sin efecto sobre el estado: se copian hectáreas, precio y monto al
 * confirmar para que lo confirmado no cambie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('neg_precios_finales_confirmados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('sol_solicitudes_servicio')->restrictOnDelete();
            $table->foreignId('confirmado_por_usuario_id')->constrained('sec_user')->restrictOnDelete();
            $table->decimal('hectareas_ejecutadas', 12, 4);
            $table->decimal('precio_ha_acordado', 14, 4);
            $table->decimal('monto', 14, 2);
            $table->dateTime('confirmado_en');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $p = DB::getTablePrefix();

        DB::statement("CREATE UNIQUE INDEX {$p}neg_precios_finales_confirmados_solicitud_unico ON {$p}neg_precios_finales_confirmados (solicitud_id) WHERE deleted_at IS NULL");

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared("CREATE TRIGGER {$p}neg_precios_finales_confirmados_bitacora AFTER INSERT OR UPDATE ON {$p}neg_precios_finales_confirmados FOR EACH ROW EXECUTE FUNCTION {$p}plt_registrar_bitacora('{$p}')");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $p = DB::getTablePrefix();
            DB::unprepared("DROP TRIGGER IF EXISTS {$p}neg_precios_finales_confirmados_bitacora ON {$p}neg_precios_finales_confirmados");
        }

        Schema::dropIfExists('neg_precios_finales_confirmados');
    }
};
