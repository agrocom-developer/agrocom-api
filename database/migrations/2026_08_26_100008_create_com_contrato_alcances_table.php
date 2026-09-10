<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — alcance de terreno del contrato (ADR 0018).
 *
 * Qué propiedades/campos cubre un contrato: `campo_id` en NULL significa
 * "toda la propiedad", con valor significa "solo ese campo dentro de la
 * propiedad". N filas por contrato — así se representa tanto un contrato de
 * una propiedad entera, como uno de un campo suelto, como uno mixto que
 * suma varias propiedades hasta cubrir `com_contratos.hectareas_contratadas`.
 *
 * Sin CHECK cruzado (no se puede expresar en un solo `CHECK` que `campo_id`
 * pertenezca a `propiedad_id`, o que `propiedad_id` sea del mismo cliente
 * que el contrato): esas guardas viven en el caso de uso que crea/actualiza
 * el contrato, mismo patrón que `CampaniaDeOtroCliente` (ADR 0015).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('com_contrato_alcances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('com_contratos')->restrictOnDelete();
            $table->foreignId('propiedad_id')->constrained('com_propiedades')->restrictOnDelete();
            $table->foreignId('campo_id')->nullable()->constrained('com_campos')->restrictOnDelete();
            $table->decimal('hectareas', 10, 2);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('contrato_id');
            $table->index('propiedad_id');
            $table->index('campo_id');
        });

        // Sin fila repetida (misma propiedad + campo) dentro de un contrato,
        // entre alcances activos (índice parcial, mismo patrón que el resto
        // del esquema). COALESCE(campo_id, 0): un unique() normal no
        // distingue "NULL, toda la propiedad" repetido dos veces, porque
        // Postgres trata cada NULL como distinto de cualquier otro.
        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_contrato_alcances_unico
            ON {$prefijo}com_contrato_alcances (contrato_id, propiedad_id, COALESCE(campo_id, 0))
            WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_contrato_alcances
                ADD CONSTRAINT {$prefijo}com_contrato_alcances_hectareas_chk
                CHECK (hectareas > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('com_contrato_alcances');
    }
};
