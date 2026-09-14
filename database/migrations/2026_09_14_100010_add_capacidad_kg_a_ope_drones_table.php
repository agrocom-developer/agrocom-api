<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — `capacidad_kg` de `ope_drones` (HU-81, tarea 96).
 * HU-79 (tipo sólido/líquido en la orden, todavía sin implementar) va a
 * necesitar saber cuánto puede llevar un dron por vuelo cuando la orden es
 * sólida; `capacidad_l` (HU-27, tarea 36) solo cubre líquidos.
 *
 * A diferencia de `capacidad_l`, sin `CHECK` de valores fijos: la espec
 * (`REcursos.docx`, Sprint 17) dice explícitamente que no hay un catálogo
 * cerrado de kilos conocido todavía. La validación de "positivo" va en el
 * Request (`CrearDronRequest`/`ActualizarDronRequest`), no en la base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ope_drones', function (Blueprint $table) {
            $table->decimal('capacidad_kg', 6, 2)->nullable()->after('capacidad_l');
        });
    }

    public function down(): void
    {
        Schema::table('ope_drones', function (Blueprint $table) {
            $table->dropColumn('capacidad_kg');
        });
    }
};
