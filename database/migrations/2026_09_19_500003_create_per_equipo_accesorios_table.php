<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Personal (`per_`) — accesorios que lleva una cuadrilla, con su
 * cantidad (tarea "cuadrillas-estadias", pedido del dueño 19/9/2026): a
 * diferencia de `per_equipo_recursos` (dron/vehículo/generador/batería, cada
 * unidad identificada con su propia vigencia), un accesorio es una cantidad
 * sobre un ítem del catálogo — "2 palas", no "la pala #7" — y sin vigencia
 * propia: se agrega o se quita, no se presta entre cuadrillas por fecha.
 *
 * `equipo_trabajo_id`/`accesorio_id`: FKs reales dentro del mismo módulo
 * (`per_equipos_trabajo`, `per_accesorios`) — `belongsTo` legítimo (ADR 0003
 * regla 3 solo prohíbe cruzar módulos).
 *
 * Único parcial `(equipo_trabajo_id, accesorio_id) WHERE deleted_at IS NULL`:
 * una cuadrilla no repite el mismo accesorio en dos filas — agregarlo de
 * nuevo actualiza la cantidad de la fila existente
 * (`AgregarAccesorioEquipo`), nunca duplica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('per_equipo_accesorios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_trabajo_id')->constrained('per_equipos_trabajo')->restrictOnDelete();
            $table->foreignId('accesorio_id')->constrained('per_accesorios')->restrictOnDelete();
            $table->unsignedInteger('cantidad')->default(1);
            $table->string('observacion')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('equipo_trabajo_id');
            $table->index('accesorio_id');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}per_equipo_accesorios_unico
            ON {$prefijo}per_equipo_accesorios (equipo_trabajo_id, accesorio_id)
            WHERE deleted_at IS NULL
        SQL);

        // Rangos y enums en la base, no solo en la aplicación (ADR 0001).
        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}per_equipo_accesorios
                ADD CONSTRAINT {$prefijo}per_equipo_accesorios_cantidad_chk
                    CHECK (cantidad >= 1)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('per_equipo_accesorios');
    }
};
