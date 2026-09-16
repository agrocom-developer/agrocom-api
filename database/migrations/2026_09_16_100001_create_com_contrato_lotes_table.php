<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — qué lotes concretos de la propiedad cubre un contrato
 * (pedido del dueño: el contrato elige una propiedad del cliente y uno o
 * más lotes de ella, no solo un número suelto de hectáreas). La propiedad
 * en sí no se repite como columna acá: se deriva de `com_lotes.propiedad_id`
 * de los lotes elegidos (mismo criterio de no duplicar una FK que ya se
 * puede resolver por join, ver `com_lote_campania`).
 *
 * Sin `hectareas_contratadas` propia por fila: el agregado sigue siendo
 * `com_contratos.hectareas_contratadas` — desglosarlo por lote no fue
 * pedido todavía (a diferencia de `ope_orden_lotes.hectareas_solicitadas`,
 * que existe porque una orden es un vuelo concreto, no un pacto comercial).
 *
 * Ambas FK en `restrictOnDelete()`: mismo criterio que el resto de pivotes
 * de Comercial (`com_lote_campania`, `com_contrato_alcances`,
 * `com_contrato_ventanas`, `com_cliente_contactos`) — en este módulo NINGÚN
 * pivote cascadea con su cabecera, ni siquiera los que son claramente
 * "detalle" de un contrato (`com_contrato_ventanas.contrato_id` también es
 * `restrictOnDelete`). Difiere a propósito del criterio que usa
 * `ope_orden_lotes.orden_id` en Operaciones (`cascadeOnDelete`): ese módulo
 * arrancó ese patrón para detalle-de-cabecera en HU-92, pero Comercial no lo
 * adoptó todavía, y esta migración no es el lugar para cambiarlo.
 *
 * Sin CHECK cruzado "un lote no puede estar en dos contratos vigentes de la
 * misma campaña": esa regla necesita leer `estado` y `campania_id` del
 * contrato dueño, algo que un `CHECK`/`UNIQUE INDEX` no puede expresar — la
 * implementa el caso de uso que crea/actualiza el contrato (mismo criterio
 * por el que "una orden vigente por lote" se resolvió en
 * `MaquinaEstadosOrden::activar()` y no en el esquema, ver el docblock de
 * `create_ope_orden_lotes_table`).
 *
 * `UNIQUE (contrato_id, lote_id)` parcial (`WHERE deleted_at IS NULL`): un
 * mismo lote no se repite dos veces dentro del mismo contrato — un
 * `unique()` normal chocaría con el soft delete (mismo patrón que
 * `com_lote_campania`/`ope_orden_lotes_orden_lote_unico`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('com_contrato_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('com_contratos')->restrictOnDelete();
            $table->foreignId('lote_id')->constrained('com_lotes')->restrictOnDelete();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('contrato_id');
            $table->index('lote_id');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_contrato_lotes_contrato_lote_unico
            ON {$prefijo}com_contrato_lotes (contrato_id, lote_id)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('com_contrato_lotes');
    }
};
