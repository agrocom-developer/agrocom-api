<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — propiedades del cliente (ADR 0018).
 *
 * Nivel de negocio por encima de `Campo`: un cliente tiene varias propiedades
 * ("Gamelera"), y una propiedad puede tener uno o más campos físicos
 * delimitados (dos mitades separadas por una carretera, cada una con su
 * propia campaña) — antes de este ADR, `com_campos` hacía las dos cosas a
 * la vez y no podía representar ese caso.
 *
 * `ubicacion` es la localidad física del predio — departamento, provincia,
 * municipio o pueblo (ej. Cuatro Cañadas, Roboré, San Matías) —, texto libre
 * igual que antes en `com_campos.ubicacion` (de donde se muda: la dirección
 * es de la propiedad, no de cada campo dentro de ella).
 *
 * created_by/updated_by sin FK a propósito, mismo criterio que el resto de
 * las tablas de Comercial creadas el 26/8/2026 (ver `com_clientes`): la FK a
 * `sec_user` llega después vía el retrofit de `add_fk_autoria_a_tablas_dominio`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('com_propiedades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('com_clientes')->restrictOnDelete();
            $table->string('nombre', 150);
            $table->string('ubicacion', 255)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('cliente_id');
        });

        $prefijo = DB::getTablePrefix();

        // Nombre único por cliente entre propiedades activas (índice parcial).
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_propiedades_nombre_unico
            ON {$prefijo}com_propiedades (cliente_id, nombre)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('com_propiedades');
    }
};
