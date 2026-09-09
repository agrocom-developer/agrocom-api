<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — estadía del equipo de trabajo en una hacienda (espec
 * §2.1, HU-51, tarea 74). Nace en la app de campo con su `uuid_cliente`
 * (invariante 1 de CLAUDE.md) al llegar el equipo (`estadia_entrada`) y se
 * cierra con un segundo evento (`estadia_salida`) que referencia esta fila
 * por su propio `uuid_cliente` — mismo patrón que `ope_trabajos`/`ope_sesiones`
 * (ver docblock de `create_ope_trabajos_table`), con `cierre_uuid_cliente`
 * para la idempotencia de la mutación de cierre (mismo mecanismo que
 * `Trabajo::$cierre_uuid_cliente`/`Sesion::$cierre_uuid_cliente`).
 *
 * `equipo_trabajo_id`, `campo_id` y `vehiculo_id` referencian tablas de otros
 * módulos (`Personal`, `Comercial`, `Mantenimiento` respectivamente) solo por
 * FK + entero plano (ADR 0003, regla 3) — nunca una relación Eloquent
 * cruzada. `vehiculo_id` es nullable: el equipo puede llegar sin declarar
 * vehículo.
 *
 * Sin `campania_id` (corrección del dueño del 8/9/2026, ver prompt de la
 * tarea 74): la estadía es del campo, no de una campaña — el piloto no elige
 * campañas desde el celular, y con varias abiertas por cliente deducir una
 * sola inventaría un dato.
 *
 * Sin columna `estado`: `salida IS NULL` significa "en curso" — una columna
 * aparte solo podría desincronizarse de la fecha real, y obligaría a una
 * máquina de estados por la invariante 7 para algo que no es una transición
 * de dominio (mismo criterio que `man_vehiculos.estado`, campo descriptivo
 * libre sin guardas).
 *
 * Dos índices únicos parciales:
 *   - `uuid_cliente` (`WHERE deleted_at IS NULL`): idempotencia real del
 *     evento `estadia_entrada` (invariante 1) — nunca un `SELECT` previo.
 *   - `equipo_trabajo_id` (`WHERE salida IS NULL AND deleted_at IS NULL`): un
 *     equipo no puede tener dos estadías abiertas a la vez.
 *
 * `CHECK (salida IS NULL OR salida > entrada)`: solo pgsql (SQLite, el motor
 * de los tests, no soporta `ADD CONSTRAINT`) — red de seguridad adicional; el
 * rechazo determinista de `estadia_salida` con fecha inválida vive en
 * `EscrituraSincronizacionEloquent::cerrarEstadia()`, no depende del motor de
 * base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ope_estadias_hacienda', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('equipo_trabajo_id')->constrained('per_equipos_trabajo')->restrictOnDelete();
            $table->foreignId('campo_id')->constrained('com_campos')->restrictOnDelete();
            $table->dateTime('entrada');
            $table->dateTime('salida')->nullable();
            $table->unsignedBigInteger('vehiculo_id')->nullable();
            $table->foreign('vehiculo_id')->references('id')->on('man_vehiculos')->restrictOnDelete();
            $table->text('observacion')->nullable();
            $table->string('cierre_uuid_cliente', 36)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('equipo_trabajo_id');
            $table->index('campo_id');
        });

        $prefijo = DB::getTablePrefix();

        // Idempotencia real (invariante 1): un reintento del evento de
        // entrada choca acá, nunca con un SELECT previo en el caso de uso.
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_estadias_hacienda_uuid_cliente_unico
            ON {$prefijo}ope_estadias_hacienda (uuid_cliente)
            WHERE deleted_at IS NULL
        SQL);

        // Un equipo no puede tener dos estadías abiertas a la vez.
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_estadias_hacienda_equipo_abierta_unico
            ON {$prefijo}ope_estadias_hacienda (equipo_trabajo_id)
            WHERE salida IS NULL AND deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_estadias_hacienda
                ADD CONSTRAINT {$prefijo}ope_estadias_hacienda_salida_chk
                    CHECK (salida IS NULL OR salida > entrada)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_estadias_hacienda');
    }
};
