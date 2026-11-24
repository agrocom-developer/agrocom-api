<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas de la negociación oferta-demanda (`docs/especificacion/negociacion-contrato.md`):
 * ofertas y contraofertas por rondas, cancelaciones tras adjudicar, mensajes del
 * chat, avance con fotos, la última ubicación del piloto y las preferencias de
 * aviso. `neg_ubicaciones` es la única tabla sin borrado lógico a propósito: la
 * ubicación se elimina de verdad al finalizar o cancelar (privacidad, §6).
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefijo = DB::getTablePrefix();

        Schema::create('neg_ofertas', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('solicitud_id')->constrained('sol_solicitudes_servicio')->restrictOnDelete();
            $table->foreignId('proveedor_id')->constrained('prv_proveedores')->restrictOnDelete();
            $table->foreignId('equipo_id')->nullable()->constrained('flo_equipos')->restrictOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->string('autor', 10);
            $table->string('tipo', 16);
            $table->decimal('precio_ha', 14, 4);
            $table->decimal('monto_estimado', 14, 2);
            $table->string('estado', 12)->default('vigente');
            $table->boolean('es_definitiva')->default(false);
            $table->text('nota')->nullable();
            $table->foreignId('oferta_anterior_id')->nullable()->constrained('neg_ofertas')->restrictOnDelete();
            $table->dateTime('vence_en');
            $table->dateTime('respondida_en')->nullable();
            $table->decimal('distancia_km', 8, 2)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['solicitud_id', 'estado'], 'neg_ofertas_solicitud_estado_index');
            $table->index(['proveedor_id', 'estado'], 'neg_ofertas_proveedor_estado_index');
            $table->index(['estado', 'vence_en'], 'neg_ofertas_estado_vence_index');
        });

        DB::statement("CREATE UNIQUE INDEX {$prefijo}neg_ofertas_uuid_cliente_unico ON {$prefijo}neg_ofertas (uuid_cliente) WHERE deleted_at IS NULL");
        DB::statement("CREATE UNIQUE INDEX {$prefijo}neg_ofertas_numero_unico ON {$prefijo}neg_ofertas (solicitud_id, proveedor_id, numero) WHERE deleted_at IS NULL");
        DB::statement("CREATE UNIQUE INDEX {$prefijo}neg_ofertas_vigente_unica ON {$prefijo}neg_ofertas (solicitud_id, proveedor_id) WHERE estado = 'vigente' AND deleted_at IS NULL");
        DB::statement("CREATE UNIQUE INDEX {$prefijo}neg_ofertas_aceptada_unica ON {$prefijo}neg_ofertas (solicitud_id) WHERE estado = 'aceptada' AND deleted_at IS NULL");

        Schema::create('neg_cancelaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('sol_solicitudes_servicio')->restrictOnDelete();
            $table->foreignId('proveedor_id')->nullable()->constrained('prv_proveedores')->restrictOnDelete();
            $table->string('parte', 10);
            $table->text('motivo');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['proveedor_id', 'created_at'], 'neg_cancelaciones_proveedor_index');
        });

        Schema::create('neg_mensajes', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('solicitud_id')->constrained('sol_solicitudes_servicio')->restrictOnDelete();
            $table->foreignId('autor_usuario_id')->constrained('sec_user')->restrictOnDelete();
            $table->string('autor_rol', 10);
            $table->string('texto', 1000);
            $table->dateTime('leido_en')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['solicitud_id', 'id'], 'neg_mensajes_solicitud_index');
        });

        DB::statement("CREATE UNIQUE INDEX {$prefijo}neg_mensajes_uuid_cliente_unico ON {$prefijo}neg_mensajes (uuid_cliente) WHERE deleted_at IS NULL");

        Schema::create('neg_avances', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('solicitud_id')->constrained('sol_solicitudes_servicio')->restrictOnDelete();
            $table->unsignedBigInteger('secuencia')->default(0);
            $table->string('tipo', 10);
            $table->decimal('hectareas_ejecutadas', 12, 4)->nullable();
            $table->decimal('porcentaje', 5, 2)->nullable();
            $table->text('nota')->nullable();
            $table->text('motivo_correccion')->nullable();
            $table->dateTime('registrado_en');
            $table->foreignId('usuario_id')->nullable()->constrained('sec_user')->restrictOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['solicitud_id', 'secuencia'], 'neg_avances_solicitud_index');
        });

        DB::statement("CREATE UNIQUE INDEX {$prefijo}neg_avances_uuid_cliente_unico ON {$prefijo}neg_avances (uuid_cliente) WHERE deleted_at IS NULL");
        DB::statement("CREATE UNIQUE INDEX {$prefijo}neg_avances_inicio_unico ON {$prefijo}neg_avances (solicitud_id) WHERE tipo = 'inicio' AND deleted_at IS NULL");

        Schema::create('neg_evidencias', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('solicitud_id')->constrained('sol_solicitudes_servicio')->restrictOnDelete();
            $table->foreignId('avance_id')->nullable()->constrained('neg_avances')->restrictOnDelete();
            $table->string('tipo', 20);
            $table->string('archivo', 255);
            $table->string('hash', 64);
            $table->unsignedInteger('bytes');
            $table->foreignId('usuario_id')->nullable()->constrained('sec_user')->restrictOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['solicitud_id', 'id'], 'neg_evidencias_solicitud_index');
        });

        DB::statement("CREATE UNIQUE INDEX {$prefijo}neg_evidencias_uuid_cliente_unico ON {$prefijo}neg_evidencias (uuid_cliente) WHERE deleted_at IS NULL");

        Schema::create('neg_ubicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->unique()->constrained('sol_solicitudes_servicio')->restrictOnDelete();
            $table->decimal('latitud', 10, 7);
            $table->decimal('longitud', 10, 7);
            $table->decimal('precision_m', 8, 1)->nullable();
            $table->dateTime('capturado_en');
            $table->timestamps();
        });

        Schema::create('neg_preferencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->unique()->constrained('sec_user')->cascadeOnDelete();
            $table->boolean('nuevas_solicitudes')->default(true);
            $table->json('zonas')->nullable();
            $table->json('macrorregiones')->nullable();
            $table->decimal('hectareas_minimas', 12, 4)->nullable();
            $table->boolean('ofertas')->default(true);
            $table->boolean('mensajes')->default(true);
            $table->boolean('avance')->default(true);
            $table->boolean('calificaciones')->default(true);
            $table->timestamps();
        });

        Schema::create('flo_dron_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dron_id')->constrained('flo_drones')->restrictOnDelete();
            $table->string('tipo', 16);
            $table->string('archivo', 255);
            $table->string('estado', 12)->default('en_revision');
            $table->text('motivo')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('sec_user')->restrictOnDelete();
            $table->dateTime('revisado_en')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['dron_id', 'estado'], 'flo_dron_fotos_dron_estado_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}neg_ofertas
                ADD CONSTRAINT {$prefijo}neg_ofertas_estado_chk CHECK (estado IN ('vigente', 'aceptada', 'reemplazada', 'rechazada', 'retirada', 'vencida', 'cerrada')),
                ADD CONSTRAINT {$prefijo}neg_ofertas_autor_chk CHECK (autor IN ('proveedor', 'cliente')),
                ADD CONSTRAINT {$prefijo}neg_ofertas_tipo_chk CHECK (tipo IN ('acepta_propuesta', 'contraoferta')),
                ADD CONSTRAINT {$prefijo}neg_ofertas_precio_chk CHECK (precio_ha > 0),
                ADD CONSTRAINT {$prefijo}neg_ofertas_numero_chk CHECK (numero >= 1)
            SQL);
            DB::statement("ALTER TABLE {$prefijo}neg_cancelaciones ADD CONSTRAINT {$prefijo}neg_cancelaciones_parte_chk CHECK (parte IN ('cliente', 'proveedor'))");
            DB::statement("ALTER TABLE {$prefijo}neg_mensajes ADD CONSTRAINT {$prefijo}neg_mensajes_rol_chk CHECK (autor_rol IN ('cliente', 'proveedor'))");
            DB::statement("ALTER TABLE {$prefijo}neg_avances ADD CONSTRAINT {$prefijo}neg_avances_tipo_chk CHECK (tipo IN ('inicio', 'avance', 'cierre'))");
            DB::statement("ALTER TABLE {$prefijo}neg_evidencias ADD CONSTRAINT {$prefijo}neg_evidencias_tipo_chk CHECK (tipo IN ('foto_avance', 'foto_final', 'foto_incidencia'))");
            DB::statement("ALTER TABLE {$prefijo}flo_dron_fotos ADD CONSTRAINT {$prefijo}flo_dron_fotos_tipo_chk CHECK (tipo IN ('frontal', 'lateral', 'placa_serie', 'otra'))");
            DB::statement("ALTER TABLE {$prefijo}flo_dron_fotos ADD CONSTRAINT {$prefijo}flo_dron_fotos_estado_chk CHECK (estado IN ('en_revision', 'aprobada', 'rechazada'))");
        }
    }

    public function down(): void
    {
        foreach (['flo_dron_fotos', 'neg_preferencias', 'neg_ubicaciones', 'neg_evidencias', 'neg_avances', 'neg_mensajes', 'neg_cancelaciones', 'neg_ofertas'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};
