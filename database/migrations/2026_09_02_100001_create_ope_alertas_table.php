<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — bandeja de alertas por excepción (espec §10, HU-19,
 * tarea 26). Recorte de alcance: de las 13 condiciones de la espec, solo 4
 * tienen datos reales detrás hoy — `bateria_caliente` (`ope_recargas.alerta_temperatura`,
 * tarea 23), `dron_sospechoso` (3+ recargas con esa alerta sobre el mismo
 * `ope_sesiones.dron_id`, tarea 20), `condiciones_forzadas`
 * (`ope_condiciones.autorizado = false`, tarea 17) y `suma_excedida`
 * (`EstadoCoberturaTrabajo::Observado`, tarea 20). Las 9 restantes dependen
 * de módulos que no existen (`Mezclas`, anticipos/rendiciones) o de un
 * scheduler que este proyecto todavía no tiene — ver el prompt de la tarea y
 * `docs/gestion/cola_tareas.md`.
 *
 * Referencia mínima al origen con columnas nullable (no una tabla morph
 * completa de Eloquent): cada `tipo` puebla un subconjunto distinto, nunca
 * los cinco a la vez —
 *
 *   - `bateria_caliente`: `recarga_id` + `sesion_id` + `trabajo_id` (los tres
 *     denormalizados desde la recarga, mismo criterio que `ope_condiciones.trabajo_id`).
 *   - `condiciones_forzadas`: `condiciones_id` + `sesion_id` + `trabajo_id`.
 *   - `suma_excedida`: solo `trabajo_id` — es una propiedad del trabajo
 *     (cobertura de TODAS sus sesiones vigentes), no de una sesión puntual.
 *   - `dron_sospechoso`: `dron_id` + `recarga_id` (la recarga que hizo cruzar
 *     el umbral, a título de trazabilidad) — sin `sesion_id`/`trabajo_id`
 *     porque el patrón cruza varias sesiones y potencialmente varios
 *     trabajos.
 *
 * `mensaje`: texto armado UNA VEZ al generar la alerta (`GenerarAlertaExcepcion`),
 * nunca recalculado en cada lectura — mismo criterio que `alerta_temperatura`
 * de `ope_recargas`.
 *
 * Idempotencia (invariante 1 de CLAUDE.md, extendida): CUATRO índices únicos
 * parciales, uno por `tipo`, cada uno sobre la columna que identifica el
 * HECHO que dispara ese tipo de alerta — no un único `(tipo, sesion_id)`
 * genérico, porque la clave natural difiere por tipo (una recarga para
 * batería caliente, un dron para dron sospechoso, un trabajo para suma
 * excedida, un registro de condiciones para condiciones forzadas). El caso de
 * uso que genera cada alerta intenta el `INSERT` directo y trata la violación
 * de unicidad como "ya aplicada" — nunca un `SELECT` previo (mismo patrón que
 * `Finanzas\Aplicacion\GenerarDevengosSesion::generarPara()`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ope_alertas', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 30);
            $table->foreignId('trabajo_id')->nullable()->constrained('ope_trabajos')->restrictOnDelete();
            $table->foreignId('sesion_id')->nullable()->constrained('ope_sesiones')->restrictOnDelete();
            $table->foreignId('recarga_id')->nullable()->constrained('ope_recargas')->restrictOnDelete();
            $table->foreignId('condiciones_id')->nullable()->constrained('ope_condiciones')->restrictOnDelete();
            $table->foreignId('dron_id')->nullable()->constrained('ope_drones')->restrictOnDelete();
            $table->text('mensaje');
            $table->string('estado', 20)->default('pendiente');
            $table->foreignId('atendida_por')->nullable()->constrained('per_personas')->restrictOnDelete();
            $table->dateTime('atendida_en')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['estado', 'tipo']);
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_alertas_bateria_caliente_unico
            ON {$prefijo}ope_alertas (recarga_id)
            WHERE tipo = 'bateria_caliente' AND deleted_at IS NULL
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_alertas_condiciones_forzadas_unico
            ON {$prefijo}ope_alertas (condiciones_id)
            WHERE tipo = 'condiciones_forzadas' AND deleted_at IS NULL
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_alertas_suma_excedida_unico
            ON {$prefijo}ope_alertas (trabajo_id)
            WHERE tipo = 'suma_excedida' AND deleted_at IS NULL
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_alertas_dron_sospechoso_unico
            ON {$prefijo}ope_alertas (dron_id)
            WHERE tipo = 'dron_sospechoso' AND deleted_at IS NULL
        SQL);

        // Rangos y enums en la base, no solo en la aplicación (ADR 0001).
        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_alertas
                ADD CONSTRAINT {$prefijo}ope_alertas_tipo_chk
                    CHECK (tipo IN ('bateria_caliente', 'dron_sospechoso', 'condiciones_forzadas', 'suma_excedida')),
                ADD CONSTRAINT {$prefijo}ope_alertas_estado_chk
                    CHECK (estado IN ('pendiente', 'atendida'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_alertas');
    }
};
