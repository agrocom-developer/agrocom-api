<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retira del esquema de trabajo el modelo de contratos que el marketplace no usa
 * (ADR 0027, 0035 y 0037): contratos, campañas, siembra por etapas, facturas por
 * contrato, órdenes de aplicación, órdenes de trabajo, estadías en hacienda y la
 * cartera comercial que los sostenía (clientes, contactos, propiedades, lotes,
 * cultivos y geografía).
 *
 * Migración destructiva con respaldo dentro de la propia base: ninguna tabla se
 * borra. Cada una se MUEVE al esquema `respaldo_contratos_y_ordenes`
 * (`ALTER TABLE … SET SCHEMA`, operación de catálogo que conserva filas, índices,
 * restricciones y secuencias) y desaparece de `public`, que es lo que ve la
 * aplicación. `down()` las devuelve a `public` con sus datos.
 *
 * Qué se mueve (16 tablas):
 *   - Contratos y facturación: `com_contratos`, `com_contrato_lotes`, `com_facturas`.
 *   - Campañas y siembra: `cpn_campanias`, `com_lote_campania`, `com_cultivos`.
 *   - Cartera: `com_clientes`, `com_cliente_contactos`, `com_propiedades`,
 *     `com_lotes`, `com_departamentos`, `com_provincias`, `com_municipios`.
 *   - Operación: `ope_ordenes_aplicacion`, `ope_orden_lotes`, `ope_ordenes_trabajo`,
 *     `ope_estadias_hacienda`.
 *
 * Efectos colaterales sobre tablas que se quedan, todos con respaldo:
 *   - `sec_user.contrato_id` (la cuenta de portal atada a un contrato) se elimina;
 *     los pares `(id, contrato_id)` quedan en
 *     `respaldo_contratos_y_ordenes.sec_user_contratos`.
 *   - Los avisos de la campana de los tipos retirados (`contrato_creado`,
 *     `orden_trabajo_creada`) se dan de baja lógica (invariante 8); sus ids quedan
 *     en `respaldo_contratos_y_ordenes.ntf_notificaciones_retiradas`.
 *
 * Ninguna fila de `plt_bitacoras` se toca: el historial de auditoría de lo retirado
 * sigue legible. Los permisos `comercial.*`, `campania.*`, `operaciones.orden.*` y
 * `operaciones.estadia.*` y las entradas de menú las da de baja `SeguridadSeeder` /
 * `SecMenuSeeder` en cada despliegue.
 *
 * Retiro definitivo del respaldo (cuando el dueño lo confirme): volcar el esquema
 * (`pg_dump --schema=respaldo_contratos_y_ordenes`), guardar el archivo y recién
 * entonces `DROP SCHEMA respaldo_contratos_y_ordenes CASCADE`. Ver
 * `docs/decisiones/0037-solicitud-y-trabajo.md`.
 *
 * Solo PostgreSQL (los tests de `tests/Unit` no corren migraciones sobre esquemas):
 * en otro motor no hace nada.
 */
return new class extends Migration
{
    private const ESQUEMA = 'respaldo_contratos_y_ordenes';

    /** En el orden en que se mueven; `down()` los devuelve al revés. */
    private const TABLAS = [
        'com_facturas',
        'ope_estadias_hacienda',
        'ope_orden_lotes',
        'ope_ordenes_trabajo',
        'ope_ordenes_aplicacion',
        'com_lote_campania',
        'com_contrato_lotes',
        'com_contratos',
        'cpn_campanias',
        'com_cultivos',
        'com_cliente_contactos',
        'com_lotes',
        'com_propiedades',
        'com_clientes',
        'com_municipios',
        'com_provincias',
        'com_departamentos',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $p = DB::getTablePrefix();
        $esquema = self::ESQUEMA;

        DB::statement("CREATE SCHEMA IF NOT EXISTS {$esquema}");

        $this->respaldarYQuitarContratoDeCuentas($p, $esquema);
        $this->darDeBajaAvisosRetirados($p, $esquema);

        foreach (self::TABLAS as $tabla) {
            if (Schema::hasTable($tabla)) {
                DB::statement("ALTER TABLE {$p}{$tabla} SET SCHEMA {$esquema}");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $p = DB::getTablePrefix();
        $esquema = self::ESQUEMA;

        foreach (array_reverse(self::TABLAS) as $tabla) {
            if ($this->existeEnRespaldo($p.$tabla)) {
                DB::statement("ALTER TABLE {$esquema}.{$p}{$tabla} SET SCHEMA public");
            }
        }

        $this->restaurarAvisosRetirados($p, $esquema);
        $this->restaurarContratoDeCuentas($p, $esquema);
    }

    private function respaldarYQuitarContratoDeCuentas(string $p, string $esquema): void
    {
        if (! Schema::hasColumn('sec_user', 'contrato_id')) {
            return;
        }

        DB::statement("CREATE TABLE IF NOT EXISTS {$esquema}.{$p}sec_user_contratos AS SELECT id, contrato_id FROM {$p}sec_user WHERE contrato_id IS NOT NULL");

        Schema::table('sec_user', function (Blueprint $table) {
            $table->dropForeign(['contrato_id']);
            $table->dropColumn('contrato_id');
        });
    }

    private function restaurarContratoDeCuentas(string $p, string $esquema): void
    {
        if (Schema::hasColumn('sec_user', 'contrato_id')) {
            return;
        }

        Schema::table('sec_user', function (Blueprint $table) {
            $table->foreignId('contrato_id')->nullable()->constrained('com_contratos')->restrictOnDelete();
        });

        if ($this->existeEnRespaldo($p.'sec_user_contratos')) {
            DB::statement("UPDATE {$p}sec_user u SET contrato_id = r.contrato_id FROM {$esquema}.{$p}sec_user_contratos r WHERE r.id = u.id");
            DB::statement("DROP TABLE {$esquema}.{$p}sec_user_contratos");
        }
    }

    private function darDeBajaAvisosRetirados(string $p, string $esquema): void
    {
        if (! Schema::hasTable('ntf_notificaciones')) {
            return;
        }

        DB::statement("CREATE TABLE IF NOT EXISTS {$esquema}.{$p}ntf_notificaciones_retiradas AS SELECT id FROM {$p}ntf_notificaciones WHERE tipo IN ('contrato_creado', 'orden_trabajo_creada') AND deleted_at IS NULL");
        DB::statement("UPDATE {$p}ntf_notificaciones SET deleted_at = now(), updated_at = now() WHERE tipo IN ('contrato_creado', 'orden_trabajo_creada') AND deleted_at IS NULL");
    }

    private function restaurarAvisosRetirados(string $p, string $esquema): void
    {
        if (! $this->existeEnRespaldo($p.'ntf_notificaciones_retiradas')) {
            return;
        }

        DB::statement("UPDATE {$p}ntf_notificaciones SET deleted_at = NULL, updated_at = now() WHERE id IN (SELECT id FROM {$esquema}.{$p}ntf_notificaciones_retiradas)");
        DB::statement("DROP TABLE {$esquema}.{$p}ntf_notificaciones_retiradas");
    }

    private function existeEnRespaldo(string $tabla): bool
    {
        return DB::table('information_schema.tables')
            ->where('table_schema', self::ESQUEMA)
            ->where('table_name', $tabla)
            ->exists();
    }
};
