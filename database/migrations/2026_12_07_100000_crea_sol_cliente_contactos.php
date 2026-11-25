<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contactos de referencia del cliente (`sol_cliente_contactos`): las personas con
 * quienes el proveedor coordina el servicio. Un cliente persona puede tener los
 * suyos; una empresa exige al menos uno activo (regla del caso de uso, no de la
 * base). `sol_solicitudes_servicio.contacto_id` guarda cuál se eligió para cada
 * solicitud. Solo crea: no toca ni borra nada existente. Cada empresa que ya
 * existe recibe como contacto principal a la persona de su cuenta, para que
 * siga pudiendo pedir servicios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sol_cliente_contactos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('sol_clientes')->restrictOnDelete();
            $table->string('nombre', 150);
            $table->string('telefono', 30);
            $table->string('cargo', 80)->nullable();
            $table->boolean('es_principal')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['cliente_id', 'deleted_at'], 'sol_cliente_contactos_cliente_idx');
        });

        $prefijo = DB::getTablePrefix();

        // Un solo principal vivo por cliente.
        DB::statement("CREATE UNIQUE INDEX {$prefijo}sol_cliente_contactos_principal_unico ON {$prefijo}sol_cliente_contactos (cliente_id) WHERE es_principal AND deleted_at IS NULL");

        Schema::table('sol_solicitudes_servicio', function (Blueprint $table) {
            $table->foreignId('contacto_id')->nullable()->constrained('sol_cliente_contactos')->restrictOnDelete();
        });

        // Las empresas existentes quedan con la persona de su cuenta como contacto principal.
        if (Schema::hasTable('sec_user')) {
            DB::statement(<<<SQL
                INSERT INTO {$prefijo}sol_cliente_contactos (cliente_id, nombre, telefono, es_principal, created_at, updated_at)
                SELECT c.id, u.name, COALESCE(NULLIF(c.telefono, ''), 'Sin teléfono'), true, c.created_at, c.updated_at
                FROM {$prefijo}sol_clientes c
                JOIN {$prefijo}sec_user u ON u.id = c.usuario_id
                WHERE c.tipo = 'empresa' AND c.deleted_at IS NULL
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('sol_solicitudes_servicio', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contacto_id');
        });

        Schema::dropIfExists('sol_cliente_contactos');
    }
};
