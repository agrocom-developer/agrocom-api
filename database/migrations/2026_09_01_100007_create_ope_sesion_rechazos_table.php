<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — corrección de sesión por rechazo (invariante 2 de
 * CLAUDE.md, HU-14, tarea 14; diseño completo en runs/14.md).
 *
 * Primera implementación real del mecanismo genérico que la invariante 2
 * describe ("las correcciones son registros nuevos con `anula_a_id`, motivo
 * y autor — nunca un UPDATE sobre lo validado"): tabla propia, no una fila
 * más de `ope_sesiones`, porque un rechazo no es "otra sesión" con sus
 * propios `piloto_id`/`hectareas_declaradas`/`uuid_cliente` — es un EVENTO
 * de decisión del jefe sobre una sesión que ya existe. Meterlo en
 * `ope_sesiones` hubiese exigido inventarle a esa fila un `uuid_cliente`
 * propio (viola la identidad de sync, invariante 1) o volver nullable medio
 * esquema pensado para hechos de vuelo.
 *
 * `anula_a_id`: FK a la sesión que rechaza — el nombre es LITERAL el de la
 * invariante 2, no una paráfrasis. `restrictOnDelete()`: nunca queda un
 * rechazo huérfano (tampoco debería poder borrarse físico una `ope_sesiones`
 * — ADR 0007 — pero la FK es la segunda barrera). Único índice parcial
 * (mismo patrón que `uuid_cliente`, skill `modelo-datos`): a lo sumo un
 * rechazo vivo por sesión — un segundo intento lo bloquea primero el caso de
 * uso (`anulada_en` ya seteada), esto es la red de seguridad a nivel de
 * esquema.
 *
 * `rechazado_por`: el autor, FK a `per_personas` — igual que `piloto_id`,
 * comparado a nivel persona (invariante 4), nunca `sec_user`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ope_sesion_rechazos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anula_a_id')->constrained('ope_sesiones')->restrictOnDelete();
            $table->string('motivo', 500);
            $table->foreignId('rechazado_por')->constrained('per_personas')->restrictOnDelete();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_sesion_rechazos_anula_a_id_unico
            ON {$prefijo}ope_sesion_rechazos (anula_a_id)
            WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_sesion_rechazos
                ADD CONSTRAINT {$prefijo}ope_sesion_rechazos_motivo_chk
                    CHECK (motivo <> '')
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_sesion_rechazos');
    }
};
