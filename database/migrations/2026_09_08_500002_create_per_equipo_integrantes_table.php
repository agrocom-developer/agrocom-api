<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Personal (`per_`) — quién integraba un equipo de trabajo, con
 * vigencia (tarea 72, HU-49, ADR 0015 punto 3): un gasto de marzo tiene que
 * quedar atribuido a quienes integraban el equipo EN MARZO, no a la
 * formación de hoy. Por eso no hay dos columnas fijas `piloto_id`/
 * `auxiliar_id` en `per_equipos_trabajo`: cada asignación es una fila propia
 * con su rango de fechas.
 *
 * `equipo_trabajo_id` / `persona_id`: FKs reales dentro del mismo módulo
 * (`per_equipos_trabajo`, `per_personas`) — `belongsTo` legítimo en el
 * modelo Eloquent (ADR 0003 regla 3 solo prohíbe cruzar módulos).
 *
 * `rol_equipo` (`piloto`/`auxiliar`): rol DENTRO del equipo, distinto de
 * `per_personas.rol` (la clasificación operativa de la persona, que admite
 * más valores). Una persona con `per_personas.rol = 'jefe_campo'` podría
 * figurar acá como `auxiliar` de una cuadrilla puntual; son dos preguntas
 * distintas.
 *
 * Sin bloqueo de solapamiento (ADR 0015 punto 3, corrección del dueño del
 * 7/9/2026: "ese personal puede realizar diferentes trabajos... se acoplará
 * el que se tiene disponible"): la pertenencia a un equipo NO es exclusiva,
 * una persona puede integrar varios equipos vigentes a la vez. Por eso NO
 * hay índice único que impida dos filas de la misma persona superpuestas en
 * el tiempo — igual que `com_contrato_ventanas` no puede expresar "sin
 * solape parcial" con un índice, esa regla vive en
 * `Personal\Dominio\ValidadorSolapamientoVigencias`, y ahí es un AVISO, no un
 * rechazo. Lo único que sí se rechaza —misma persona, mismo equipo,
 * vigencias que se pisan— lo hace el caso de uso que asigna, no la base.
 *
 * `CHECK (hasta IS NULL OR hasta >= desde)`: mismo criterio que
 * `cpn_campanias_fechas_chk`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('per_equipo_integrantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_trabajo_id')->constrained('per_equipos_trabajo')->restrictOnDelete();
            $table->foreignId('persona_id')->constrained('per_personas')->restrictOnDelete();
            $table->string('rol_equipo', 20);
            $table->date('desde');
            $table->date('hasta')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('equipo_trabajo_id');
            $table->index('persona_id');
        });

        $prefijo = DB::getTablePrefix();

        // Rangos y enums en la base, no solo en la aplicación (ADR 0001).
        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}per_equipo_integrantes
                ADD CONSTRAINT {$prefijo}per_equipo_integrantes_rol_chk
                    CHECK (rol_equipo IN ('piloto', 'auxiliar')),
                ADD CONSTRAINT {$prefijo}per_equipo_integrantes_fechas_chk
                    CHECK (hasta IS NULL OR hasta >= desde)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('per_equipo_integrantes');
    }
};
