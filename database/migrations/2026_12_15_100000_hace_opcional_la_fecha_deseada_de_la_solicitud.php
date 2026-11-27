<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La solicitud no vence sola y la fecha deseada de fumigación es opcional (ADR 0045, punto 20):
 * `ventana_desde` y `ventana_hasta` admiten nulo. El CHECK `ventana_hasta >= ventana_desde` sigue
 * valiendo (un nulo no lo viola). Cambio aditivo: no se borra ni se transforma ningún dato; las
 * columnas `vence_en` y `expirada_en` quedan sin uso y se retiran en la reconsolidación final.
 *
 * SQLite (las pruebas) reconstruye la tabla al cambiar una columna y falla si una vista la referencia:
 * ahí `vw_metricas_solicitudes` sale antes y vuelve después, con la definición vigente (por departamento).
 * PostgreSQL no lo necesita: soltar el NOT NULL no toca a las vistas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->soltarVistaSiHaceFalta();

        Schema::table('sol_solicitudes_servicio', function (Blueprint $table) {
            $table->date('ventana_desde')->nullable()->change();
            $table->date('ventana_hasta')->nullable()->change();
        });

        $this->recrearVistaSiHaceFalta();
    }

    public function down(): void
    {
        if (DB::table('sol_solicitudes_servicio')->where(fn ($q) => $q->whereNull('ventana_desde')->orWhereNull('ventana_hasta'))->exists()) {
            throw new RuntimeException('Hay solicitudes sin fecha deseada en sol_solicitudes_servicio: no caben en el esquema anterior; ponles fecha antes de revertir esta migración.');
        }

        $this->soltarVistaSiHaceFalta();

        Schema::table('sol_solicitudes_servicio', function (Blueprint $table) {
            $table->date('ventana_desde')->nullable(false)->change();
            $table->date('ventana_hasta')->nullable(false)->change();
        });

        $this->recrearVistaSiHaceFalta();
    }

    private function soltarVistaSiHaceFalta(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP VIEW IF EXISTS '.DB::getTablePrefix().'vw_metricas_solicitudes');
        }
    }

    /** La definición de `retira_zonas`: el departamento de la solicitud, o el de su terreno si aún es borrador. */
    private function recrearVistaSiHaceFalta(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        $p = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE VIEW {$p}vw_metricas_solicitudes AS
            SELECT s.id AS solicitud_id, s.publicada_en, s.adjudicada_en, s.estado, s.proveedor_id,
                   COALESCE(s.departamento_codigo, t.departamento_codigo) AS departamento_codigo,
                   s.hectareas_solicitadas, s.hectareas_ejecutadas, s.monto_final
            FROM {$p}sol_solicitudes_servicio s
            LEFT JOIN {$p}sol_terrenos t ON t.id = s.terreno_id
            WHERE s.deleted_at IS NULL
        SQL);
    }
};
