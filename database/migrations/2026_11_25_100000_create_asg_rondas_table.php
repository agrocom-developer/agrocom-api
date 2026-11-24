<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `asg_rondas` (especificación del marketplace §4.5, ADR 0030 y 0038): cada
 * vuelta del motor de asignación sobre una solicitud. Un `ciclo` agrupa las
 * rondas de una misma entrada a `en_asignacion` (la inicial y cada reasignación
 * tras una cancelación del proveedor), así `(solicitud, ciclo, ronda)` es único
 * y reintentar el avance de una ronda no abre una segunda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asg_rondas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('sol_solicitudes_servicio')->restrictOnDelete();
            $table->unsignedInteger('ciclo');
            $table->unsignedInteger('ronda');
            $table->string('grupo', 10);
            $table->decimal('radio_km', 8, 2)->nullable();
            $table->unsignedInteger('proveedores_ofrecidos')->default(0);
            $table->dateTime('iniciada_en');
            $table->dateTime('cerrada_en')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement("CREATE UNIQUE INDEX {$prefijo}asg_rondas_solicitud_ciclo_ronda_unico ON {$prefijo}asg_rondas (solicitud_id, ciclo, ronda) WHERE deleted_at IS NULL");

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}asg_rondas
                ADD CONSTRAINT {$prefijo}asg_rondas_grupo_chk CHECK (grupo IN ('propio', 'tercero')),
                ADD CONSTRAINT {$prefijo}asg_rondas_ronda_chk CHECK (ronda >= 1 AND ciclo >= 1)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asg_rondas');
    }
};
