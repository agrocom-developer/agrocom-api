<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Personal (`per_`) — catálogo de accesorios que una cuadrilla lleva
 * al campo (tarea "cuadrillas-estadias", pedido del dueño 19/9/2026): machete,
 * palas, linternas… Catálogo simple, mismo criterio que `com_cultivos`
 * (`activo` boolean, sin máquina de estados) — se ofrece o se deja de ofrecer
 * sin perder el historial de las cuadrillas que ya lo llevaban.
 *
 * `nombre` único entre accesorios activos, SIN distinguir mayúsculas ni
 * espacios (índice parcial sobre `lower(nombre)`): el alta desde el diálogo
 * de equipamiento busca por nombre antes de crear uno nuevo
 * (`AgregarAccesorioEquipo`), y dos filas que solo difieren en "Pala"/"pala"
 * serían el mismo accesorio dos veces.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('per_accesorios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            $table->boolean('activo')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                CREATE UNIQUE INDEX {$prefijo}per_accesorios_nombre_unico
                ON {$prefijo}per_accesorios (lower(nombre))
                WHERE deleted_at IS NULL
            SQL);
        } else {
            // SQLite (tests locales rápidos): índice simple equivalente, sin
            // `lower()` — no hay migraciones que corran ahí en CI, es solo
            // para que un `migrate` local no reviente.
            DB::statement(<<<SQL
                CREATE UNIQUE INDEX {$prefijo}per_accesorios_nombre_unico
                ON {$prefijo}per_accesorios (nombre)
                WHERE deleted_at IS NULL
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('per_accesorios');
    }
};
