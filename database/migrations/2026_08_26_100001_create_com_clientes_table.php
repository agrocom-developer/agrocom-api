<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — clientes (espec §4.1).
 *
 * Los contactos (dueño, agrónomo, encargado de la propiedad) NO viven acá:
 * van en com_cliente_contactos (insumos §4 — el "encargado de la propiedad"
 * es un rol operativo del cliente que la espec aún no consolidó). El dueño
 * de una sociedad se registra ahí como contacto tipo `dueno`, no acá
 * (ADR 0018): no hace falta una entidad propia para sus datos.
 *
 * tipo_persona (física / jurídica, ADR 0018): distingue el cliente
 * unipersonal de la sociedad (ej. "Frigosis") — sin impacto en el resto del
 * modelo, es un dato descriptivo del cliente.
 *
 * created_by/updated_by son bigint sin FK a propósito: el modelo sec_* (ADR 0004)
 * aún no migró y la tabla users actual será reemplazada por sec_user; la FK se
 * agrega cuando el módulo Seguridad exista (coordinar con modulos-roles).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('com_clientes', function (Blueprint $table) {
            $table->id();
            $table->string('razon_social', 200);
            $table->string('nit', 20)->nullable();
            $table->string('tipo_persona', 10);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        // NIT único entre clientes activos: un cliente dado de baja lógica no
        // bloquea el re-alta con el mismo NIT (índice parcial, ADR 0001/0007).
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_clientes_nit_unico
            ON {$prefijo}com_clientes (nit)
            WHERE nit IS NOT NULL AND deleted_at IS NULL
        SQL);

        // Enum en la base, no solo en la aplicación (ADR 0001).
        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_clientes
                ADD CONSTRAINT {$prefijo}com_clientes_tipo_persona_chk
                CHECK (tipo_persona IN ('fisica', 'juridica'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('com_clientes');
    }
};
