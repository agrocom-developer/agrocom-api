<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retira las zonas del esquema (ADR 0045, punto 1: el mercado no tiene zonas, regiones ni radio
 * de operación; la ubicación de una solicitud es su departamento y su provincia).
 *
 * Qué sale:
 *
 * - `sol_zonas` (el catálogo, con su `macrorregion`) y sus índices y restricciones.
 * - `sol_terrenos.zona_id` (FK) y su índice.
 * - `prv_proveedores.zona_base_id` (FK) y `radio_operacion_km` (con su CHECK) y el índice de la base.
 * - `neg_preferencias.zonas` y `.macrorregiones` (los avisos ya no se filtran por zona).
 * - `sol_precios_referencia`: el precio de referencia ya se retiró del código (api #364) y la tabla
 *   quedaba en el esquema; lleva una FK a `sol_zonas` y no puede quedar sola, así que sale con ellas.
 * - Los permisos `solicitudes.zona.ver` y `solicitudes.zona.gestionar` (baja lógica con sus
 *   asignaciones, igual que `retira_permisos_de_actas_y_reportes`).
 *
 * Qué cambia: `vw_metricas_solicitudes` agrupaba por `region` (la macrorregión de la zona del
 * terreno); ahora trae `departamento_codigo` (el de la solicitud, o el de su terreno mientras es un
 * borrador). La vista se recrea ANTES de tocar las tablas de las que dependía.
 *
 * Exención del invariante 15 (ADR 0045, punto 26): no pasa por un esquema `respaldo_*` porque beta
 * solo tiene datos de prueba y `sol_zonas` y `sol_precios_referencia` son semillas. Si antes de
 * integrarla existe un dato real, la exención caduca y esta migración no se aplica.
 *
 * `down()` recrea las estructuras VACÍAS (sin filas ni catálogo sembrado) y la vista anterior; las
 * columnas `zona_id` y `zona_base_id` vuelven nulas, y el permiso vuelve a vivo.
 */
return new class extends Migration
{
    private const PERMISOS = ['solicitudes.zona.ver', 'solicitudes.zona.gestionar'];

    public function up(): void
    {
        // La vista sale primero (depende de `sol_zonas` y de `sol_terrenos.zona_id`) y vuelve al final,
        // ya por departamento.
        $this->soltarVista();

        Schema::dropIfExists('sol_precios_referencia');

        if (Schema::hasTable('prv_proveedores')) {
            $this->soltarIndice('prv_proveedores_zona_base_idx');
            Schema::table('prv_proveedores', function (Blueprint $table) {
                if ($this->puedeSoltarLlaves() && Schema::hasColumn('prv_proveedores', 'zona_base_id')) {
                    $table->dropConstrainedForeignId('zona_base_id');
                }
            });
            // Soltar la columna suelta también su CHECK (`radio_operacion_km > 0`).
            Schema::table('prv_proveedores', function (Blueprint $table) {
                if (Schema::hasColumn('prv_proveedores', 'radio_operacion_km')) {
                    $table->dropColumn('radio_operacion_km');
                }
            });
        }

        if ($this->puedeSoltarLlaves() && Schema::hasTable('sol_terrenos') && Schema::hasColumn('sol_terrenos', 'zona_id')) {
            $this->soltarIndice('sol_terrenos_zona_idx');
            Schema::table('sol_terrenos', function (Blueprint $table) {
                $table->dropConstrainedForeignId('zona_id');
            });
        }

        if (Schema::hasTable('neg_preferencias') && Schema::hasColumn('neg_preferencias', 'zonas')) {
            Schema::table('neg_preferencias', function (Blueprint $table) {
                $table->dropColumn(['zonas', 'macrorregiones']);
            });
        }

        Schema::dropIfExists('sol_zonas');

        $this->recrearVistaPorDepartamento();
        $this->marcarPermisos(retirados: true);
    }

    public function down(): void
    {
        $this->soltarVista();
        $this->marcarPermisos(retirados: false);

        $prefijo = DB::getTablePrefix();

        Schema::create('sol_zonas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            $table->decimal('centroide_lat', 10, 7);
            $table->decimal('centroide_lng', 10, 7);
            $table->decimal('radio_km', 6, 1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->string('macrorregion', 20)->nullable();
        });

        DB::statement("CREATE UNIQUE INDEX {$prefijo}sol_zonas_nombre_unico ON {$prefijo}sol_zonas (nombre) WHERE deleted_at IS NULL");

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}sol_zonas
                ADD CONSTRAINT {$prefijo}sol_zonas_radio_km_chk CHECK (radio_km > 0),
                ADD CONSTRAINT {$prefijo}sol_zonas_latitud_chk CHECK (centroide_lat BETWEEN -90 AND 90),
                ADD CONSTRAINT {$prefijo}sol_zonas_longitud_chk CHECK (centroide_lng BETWEEN -180 AND 180)
            SQL);
        }

        if (Schema::hasTable('neg_preferencias')) {
            Schema::table('neg_preferencias', function (Blueprint $table) {
                $table->json('zonas')->nullable()->after('nuevas_solicitudes');
                $table->json('macrorregiones')->nullable()->after('zonas');
            });
        }

        if ($this->puedeSoltarLlaves() && Schema::hasTable('sol_terrenos')) {
            Schema::table('sol_terrenos', function (Blueprint $table) {
                $table->foreignId('zona_id')->nullable()->constrained('sol_zonas')->restrictOnDelete();
            });
            $this->crearIndice('sol_terrenos_zona_idx', 'sol_terrenos', 'zona_id');
        }

        if (Schema::hasTable('prv_proveedores')) {
            Schema::table('prv_proveedores', function (Blueprint $table) {
                if ($this->puedeSoltarLlaves()) {
                    $table->foreignId('zona_base_id')->nullable()->constrained('sol_zonas')->restrictOnDelete();
                }
                $table->decimal('radio_operacion_km', 6, 1)->default(50);
            });
            $this->crearIndice('prv_proveedores_zona_base_idx', 'prv_proveedores', 'zona_base_id');

            if (DB::getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE {$prefijo}prv_proveedores ADD CONSTRAINT {$prefijo}prv_proveedores_radio_chk CHECK (radio_operacion_km > 0)");
            }
        }

        Schema::create('sol_precios_referencia', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_aplicacion', 30);
            $table->foreignId('zona_id')->nullable()->constrained('sol_zonas')->restrictOnDelete();
            $table->string('macrorregion', 30)->nullable();
            $table->decimal('precio_ha', 14, 4);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tipo_aplicacion'], 'sol_precios_referencia_tipo_index');
        });

        DB::statement("CREATE UNIQUE INDEX {$prefijo}sol_precios_ref_zona_unico ON {$prefijo}sol_precios_referencia (tipo_aplicacion, zona_id) WHERE zona_id IS NOT NULL AND deleted_at IS NULL");
        DB::statement("CREATE UNIQUE INDEX {$prefijo}sol_precios_ref_macro_unico ON {$prefijo}sol_precios_referencia (tipo_aplicacion, macrorregion) WHERE zona_id IS NULL AND macrorregion IS NOT NULL AND deleted_at IS NULL");
        DB::statement("CREATE UNIQUE INDEX {$prefijo}sol_precios_ref_general_unico ON {$prefijo}sol_precios_referencia (tipo_aplicacion) WHERE zona_id IS NULL AND macrorregion IS NULL AND deleted_at IS NULL");
        $this->crearIndice('sol_precios_referencia_zona_idx', 'sol_precios_referencia', 'zona_id');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$prefijo}sol_precios_referencia ADD CONSTRAINT {$prefijo}sol_precios_referencia_precio_chk CHECK (precio_ha > 0)");
        }

        $this->recrearVistaPorRegion();
    }

    /**
     * Solo PostgreSQL. SQLite (las pruebas) no quita una columna con llave foránea sin reconstruir la
     * tabla, y la reconstrucción pierde los índices únicos parciales: ahí `zona_id` y `zona_base_id`
     * quedan inertes (nadie los lee ni los escribe) y la verificación de esas dos columnas es la de
     * PostgreSQL (subida, bajada y subida, ver el PR).
     */
    private function puedeSoltarLlaves(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }

    private function soltarVista(): void
    {
        DB::statement('DROP VIEW IF EXISTS '.DB::getTablePrefix().'vw_metricas_solicitudes');
    }

    /** La vista sin la zona: el departamento de la solicitud (o el de su terreno si aún es borrador). */
    private function recrearVistaPorDepartamento(): void
    {
        $p = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE VIEW {$p}vw_metricas_solicitudes AS
            SELECT s.id AS solicitud_id, s.publicada_en, s.adjudicada_en, s.estado, s.proveedor_id,
                   COALESCE(s.departamento_codigo, t.departamento_codigo) AS departamento_codigo,
                   s.hectareas_solicitadas, s.hectareas_ejecutadas, s.monto_final
            FROM {$p}sol_solicitudes_servicio s
            LEFT JOIN {$p}sol_terrenos t ON t.id = s.terreno_id
            WHERE s.deleted_at IS NULL
        SQL);
    }

    /** La definición anterior, para `down()` (necesita `sol_zonas.macrorregion` y `sol_terrenos.zona_id`). */
    private function recrearVistaPorRegion(): void
    {
        $p = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE VIEW {$p}vw_metricas_solicitudes AS
            SELECT s.id AS solicitud_id, s.publicada_en, s.adjudicada_en, s.estado, s.proveedor_id,
                   COALESCE(z.macrorregion, 'sin_region') AS region,
                   s.hectareas_solicitadas, s.hectareas_ejecutadas, s.monto_final
            FROM {$p}sol_solicitudes_servicio s
            LEFT JOIN {$p}sol_terrenos t ON t.id = s.terreno_id
            LEFT JOIN {$p}sol_zonas z ON z.id = t.zona_id
            WHERE s.deleted_at IS NULL
        SQL);
    }

    /** Los índices de claves foráneas solo existen en PostgreSQL (`agrega_indices_de_consulta`). */
    private function soltarIndice(string $nombre): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS '.DB::getTablePrefix().$nombre);
        }
    }

    private function crearIndice(string $nombre, string $tabla, string $columna): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $p = DB::getTablePrefix();

            DB::statement("CREATE INDEX IF NOT EXISTS {$p}{$nombre} ON {$p}{$tabla} ({$columna}) WHERE deleted_at IS NULL");
        }
    }

    /** Baja lógica (o restauración) de los permisos y de sus asignaciones a roles. */
    private function marcarPermisos(bool $retirados): void
    {
        if (! Schema::hasTable('sec_permission') || ! Schema::hasTable('sec_role_permission')) {
            return;
        }

        $codigos = implode(', ', array_map(fn (string $codigo): string => DB::getPdo()->quote($codigo), self::PERMISOS));
        $p = DB::getTablePrefix();

        if ($retirados) {
            DB::statement("UPDATE {$p}sec_role_permission SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE deleted_at IS NULL AND id_permission IN (SELECT id FROM {$p}sec_permission WHERE code IN ({$codigos}))");
            DB::statement("UPDATE {$p}sec_permission SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE deleted_at IS NULL AND code IN ({$codigos})");

            return;
        }

        DB::statement("UPDATE {$p}sec_permission SET deleted_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE code IN ({$codigos})");
        DB::statement("UPDATE {$p}sec_role_permission SET deleted_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE deleted_at IS NOT NULL AND id_permission IN (SELECT id FROM {$p}sec_permission WHERE code IN ({$codigos}))");
    }
};
