<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Mantenimiento (`man_`) — ficha de inventario del dron (HU-82,
 * tarea 97): "como encargado, quiero llevar el activo completo del dron
 * (serie, chasis, versión de software, región, serie del control,
 * accesorios), para tener el inventario completo". `ope_drones` nació
 * "deliberadamente mínima" (docblock de
 * `2026_09_01_100013_create_ope_drones_table.php`) porque `Operaciones`
 * solo necesita lo operativo (identificador, modelo, capacidad); este dato
 * es de `Mantenimiento`, ABM nuevo sin máquina de estados.
 *
 * `identificador_dron`: índice único PARCIAL sobre `deleted_at IS NULL`,
 * mismo patrón que `man_baterias.identificador` — una ficha dada de baja
 * lógica no bloquea el re-alta con el mismo identificador. Es también la
 * clave de correlación de TEXTO (no FK) contra `ope_drones.identificador`,
 * mismo criterio y mismo porqué que `ope_recargas.bateria_saliente_id` (ver
 * docblock de
 * `App\Dominios\Operaciones\Contratos\LecturaAlertasTemperaturaBateria`):
 * `ope_drones` es de otro módulo, sin `belongsTo` cross-módulo (ADR 0003).
 * La validación de que el identificador corresponde a un dron activo real
 * vive en el Request (`Rule::exists('ope_drones', 'identificador')`), no acá
 * ni en el caso de uso.
 *
 * El resto de las columnas son datos de activo, todas opcionales salvo el
 * identificador: una ficha puede darse de alta incompleta y completarse
 * después. `tiene_cargador_control`/`tiene_modem`/`tiene_maletin`:
 * accesorios del equipo, booleanos con default `false`.
 *
 * Sin `estado` (invariante 7 de CLAUDE.md no aplica: no hay máquina de
 * estados, mismo criterio que `man_baterias`/`man_vehiculos`) y sin
 * `base_id` (la HU no pide asignación a base).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('man_drones', function (Blueprint $table) {
            $table->id();
            $table->string('identificador_dron', 40);
            $table->string('numero_serie')->nullable();
            $table->string('chasis')->nullable();
            $table->string('version_software')->nullable();
            $table->string('region')->nullable();
            $table->string('serie_control')->nullable();
            $table->boolean('tiene_cargador_control')->default(false);
            $table->boolean('tiene_modem')->default(false);
            $table->boolean('tiene_maletin')->default(false);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}man_drones_identificador_dron_unico
            ON {$prefijo}man_drones (identificador_dron)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('man_drones');
    }
};
