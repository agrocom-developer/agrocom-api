<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Personal (`per_`) — equipamiento asignado a un equipo de trabajo,
 * con vigencia (tarea 72, HU-49, ADR 0015 punto 3): qué dron, vehículo o
 * generador tenía un equipo en una fecha dada. Es lo que cierra el problema
 * del combustible que no se puede imputar a ningún trabajo — "ya el equipo
 * está asociado a equipos de inventario... así sabemos qué vehículo solicitó
 * nuevo combustible".
 *
 * `recurso_tipo` (`dron`/`vehiculo`/`generador`) + `recurso_id` es
 * polimórfico A PROPÓSITO Y SIN FK — la ÚNICA excepción declarada a "FK
 * real" de todo el ADR 0015 (punto 3, y su sección "en contra, y asumido").
 * El motivo no es pereza: el destino de `recurso_id` depende de
 * `recurso_tipo` y apunta a tres tablas de DOS módulos distintos
 * (`ope_drones` en Operaciones; `man_vehiculos` y `man_generadores` en
 * Mantenimiento), mientras que esta tabla vive en Personal. Una columna no
 * puede llevar tres FKs alternativas, y "resolver el destino en la
 * aplicación antes de declarar la FK" es exactamente lo que ADR 0003 regla 3
 * prohíbe (una FK cross-módulo real habilitaría un `belongsTo` cross-módulo,
 * que rompe el aislamiento por el que un módulo solo escribe sus propias
 * tablas). La integridad la sostiene el CASO DE USO que asigna el recurso:
 * verifica que `recurso_id` exista y esté activo en la tabla que le
 * corresponde según `recurso_tipo` ANTES de guardar. Un test la cubre
 * (rechazar un `recurso_id` inexistente), pero la base no puede.
 *
 * `equipo_trabajo_id`: FK real a `per_equipos_trabajo` (mismo módulo,
 * `belongsTo` legítimo).
 *
 * Sin bloqueo de solapamiento, mismo criterio que
 * `per_equipo_integrantes` (ADR 0015 punto 3): un dron o una camioneta
 * prestados entre cuadrillas son operación normal, no un error de carga. El
 * aviso —no el rechazo— vive en
 * `Personal\Dominio\ValidadorSolapamientoVigencias`, igual que para
 * integrantes.
 *
 * `CHECK (hasta IS NULL OR hasta >= desde)`: mismo criterio que
 * `per_equipo_integrantes_fechas_chk`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('per_equipo_recursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_trabajo_id')->constrained('per_equipos_trabajo')->restrictOnDelete();
            $table->string('recurso_tipo', 20);
            $table->unsignedBigInteger('recurso_id');
            $table->date('desde');
            $table->date('hasta')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('equipo_trabajo_id');
            $table->index(['recurso_tipo', 'recurso_id']);
        });

        $prefijo = DB::getTablePrefix();

        // Rangos y enums en la base, no solo en la aplicación (ADR 0001).
        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}per_equipo_recursos
                ADD CONSTRAINT {$prefijo}per_equipo_recursos_tipo_chk
                    CHECK (recurso_tipo IN ('dron', 'vehiculo', 'generador')),
                ADD CONSTRAINT {$prefijo}per_equipo_recursos_fechas_chk
                    CHECK (hasta IS NULL OR hasta >= desde)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('per_equipo_recursos');
    }
};
