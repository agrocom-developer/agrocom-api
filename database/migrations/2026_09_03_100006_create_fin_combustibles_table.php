<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Finanzas (`fin_`, ADR 0011) — combustible del generador y de
 * vehículos (espec §4.4, línea 146; HU-35, tarea 49): "como encargado,
 * quiero registrar el combustible del generador y de los vehículos, para
 * imputarlo a la campaña". Cierra Sprint 10 ("gastos y rendiciones").
 *
 * Entidad independiente de `ope_recargas.litros_combustible_generador`
 * (tarea 23, HU-13, "informativo, sin costeo — Fase 3" según el docblock de
 * `SyncController`): esa columna son litros sueltos ligados a una recarga
 * puntual dentro de una sesión de vuelo. `fin_combustibles` es el costeo
 * real — no se lee ni se escribe desde `ope_recargas`, y no hay FK entre
 * ambas.
 *
 * `monto` se carga DIRECTO por el encargado, no derivado de `litros ×
 * precio_unitario`: a diferencia de `fin_gastos.monto` (`cantidad ×
 * precio_unitario`), el CA esencial de esta HU (`plan_sprints.md` Sprint
 * 10, §220) no trae una columna de precio unitario — `litros` y `monto` son
 * ambos datos de entrada.
 *
 * `destino` (`generador`/`vehiculo`) es un `string` con `CHECK`, no una FK:
 * no existe módulo `Vehículo` todavía (llega con HU-40, sin prompt
 * todavía) — cuando exista, se agrega `vehiculo_id` por `ALTER TABLE`,
 * mismo criterio que `rendicion_id` se agregó a `fin_gastos` (tarea 48).
 *
 * `base_id` es FK plana y OBLIGATORIA a `per_bases` (ADR 0003 regla 3,
 * mismo criterio que `Gasto::base_id`, sin `belongsTo` cross-módulo): a
 * diferencia de `fin_gastos.base_id` (nullable, "imputable a trabajo, base
 * o general"), acá el CA esencial es literal "carga por base y fecha" — sin
 * caso de combustible general.
 *
 * Sin comprobante ni vínculo con `fin_gastos`/`fondos_caja` — fuera del CA
 * esencial de esta HU (ver el prompt de la tarea 49).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_combustibles', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->unsignedBigInteger('base_id');
            $table->foreign('base_id')->references('id')->on('per_bases')->restrictOnDelete();
            $table->string('destino', 20);
            $table->decimal('litros', 10, 2);
            $table->decimal('monto', 12, 2);
            $table->text('descripcion')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('base_id');
            $table->index('fecha');
        });

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_combustibles
                ADD CONSTRAINT {$prefijo}fin_combustibles_destino_chk
                    CHECK (destino IN ('generador', 'vehiculo'))
            SQL);

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_combustibles
                ADD CONSTRAINT {$prefijo}fin_combustibles_litros_chk
                    CHECK (litros > 0)
            SQL);

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_combustibles
                ADD CONSTRAINT {$prefijo}fin_combustibles_monto_chk
                    CHECK (monto > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_combustibles');
    }
};
