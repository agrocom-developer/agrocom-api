<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `neg_pagos_confirmados` (ADR 0034, punto 4): el proveedor informa que recibió
 * el pago, que ocurre fuera de la aplicación. Es un registro informativo, de un
 * solo escritor (el titular del proveedor adjudicado), único por solicitud y sin
 * efecto sobre el estado. El monto es una copia del monto final al confirmar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('neg_pagos_confirmados', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('solicitud_id')->constrained('sol_solicitudes_servicio')->restrictOnDelete();
            $table->foreignId('confirmado_por_usuario_id')->constrained('sec_user')->restrictOnDelete();
            $table->decimal('monto', 14, 2);
            $table->string('metodo', 20)->nullable();
            $table->string('nota', 280)->nullable();
            $table->dateTime('confirmado_en');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement("CREATE UNIQUE INDEX {$prefijo}neg_pagos_confirmados_solicitud_unico ON {$prefijo}neg_pagos_confirmados (solicitud_id) WHERE deleted_at IS NULL");
        DB::statement("CREATE UNIQUE INDEX {$prefijo}neg_pagos_confirmados_uuid_unico ON {$prefijo}neg_pagos_confirmados (uuid_cliente) WHERE deleted_at IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('neg_pagos_confirmados');
    }
};
