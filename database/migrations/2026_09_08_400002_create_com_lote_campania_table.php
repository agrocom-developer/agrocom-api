<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — qué se sembró en cada lote, en cada campaña (HU-48,
 * tarea 71, etapa 2; ADR 0015 punto 4). El cultivo NO es columna de
 * `com_lotes`: un lote se siembra de soya esta campaña y de maíz la
 * siguiente, así que la dimensión vive en esta tabla intermedia.
 *
 * `campania_id` — FK real + entero plano, nunca `belongsTo` cruzando
 * módulos (ADR 0003 regla 3), mismo criterio que
 * `com_contratos.campania_id` (tarea 69): `Campania` es de otro módulo.
 *
 * `UNIQUE (lote_id, campania_id)` parcial (`WHERE deleted_at IS NULL`): un
 * cultivo por lote y campaña. El caso real de dos ciclos en el mismo año
 * agronómico (soya de verano, maíz de invierno) se modela como DOS
 * campañas del mismo cliente, no como dos cultivos acá (ADR 0015 punto 4)
 * — por eso no hace falta que `cultivo_id` entre en la unicidad.
 *
 * `hectareas_sembradas` DECIMAL, jamás float (invariante 6). El `CHECK` de
 * fechas admite cualquiera de las dos en blanco: solo exige el orden
 * cuando las dos están cargadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('com_lote_campania', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->constrained('com_lotes')->restrictOnDelete();
            $table->foreignId('campania_id')->constrained('cpn_campanias')->restrictOnDelete();
            $table->foreignId('cultivo_id')->constrained('com_cultivos')->restrictOnDelete();
            $table->decimal('hectareas_sembradas', 10, 2);
            $table->date('fecha_siembra')->nullable();
            $table->date('fecha_cosecha_estimada')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('lote_id');
            $table->index('campania_id');
            $table->index('cultivo_id');
        });

        $prefijo = DB::getTablePrefix();

        // Un cultivo por lote y campaña (índice parcial, mismo patrón que
        // com_lotes_codigo_unico): un unique() normal chocaría con el soft
        // delete, porque una siembra borrada seguiría ocupando el lugar.
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_lote_campania_lote_campania_unico
            ON {$prefijo}com_lote_campania (lote_id, campania_id)
            WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_lote_campania
                ADD CONSTRAINT {$prefijo}com_lote_campania_hectareas_chk
                    CHECK (hectareas_sembradas > 0),
                ADD CONSTRAINT {$prefijo}com_lote_campania_fechas_chk
                    CHECK (
                        fecha_cosecha_estimada IS NULL
                        OR fecha_siembra IS NULL
                        OR fecha_cosecha_estimada >= fecha_siembra
                    )
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('com_lote_campania');
    }
};
