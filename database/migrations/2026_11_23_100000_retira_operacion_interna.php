<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retira del esquema de trabajo la operación interna que el marketplace no
 * usa (ADR 0029, punto 4; ADR 0035): finanzas internas, inventario y
 * mantenimiento de flota. De Mantenimiento se conservan `man_baterias` (ciclos
 * de batería) y `man_drones` (ficha técnica del dron).
 *
 * Migración destructiva con respaldo dentro de la propia base: ninguna tabla se
 * borra. Cada una se MUEVE al esquema `respaldo_operacion_interna`
 * (`ALTER TABLE … SET SCHEMA`, operación de catálogo que conserva filas,
 * índices, restricciones y secuencias) y desaparece de `public`, que es lo que
 * ve la aplicación. `down()` las devuelve a `public` con sus datos.
 *
 * Qué se mueve (18 tablas):
 *   - Finanzas: `fin_anticipos`, `fin_combustibles`, `fin_devengos_personal`,
 *     `fin_gastos`, `fin_planilla_detalles`, `fin_planillas`,
 *     `fin_rendiciones`, `fin_rubros`, `fin_subrubros`, `fin_tarifas`.
 *   - Inventario: `inv_movimientos`, `inv_repuestos`, `inv_stock`.
 *   - Mantenimiento: `man_generadores`, `man_ordenes_mantenimiento`,
 *     `man_planes_mantenimiento`, `man_vehiculos`.
 *   - Operaciones: `ope_orden_trabajo_equipos` (solo guardaba la condición de
 *     pago de cada equipo en una orden de trabajo, ADR 0023).
 *
 * Efectos colaterales sobre tablas que se quedan, todos con respaldo:
 *   - `ope_estadias_hacienda.vehiculo_id` (FK a `man_vehiculos`) se elimina; los
 *     pares `(id, vehiculo_id)` quedan en
 *     `respaldo_operacion_interna.ope_estadias_vehiculo`.
 *   - Las filas de `per_equipo_recursos` de tipo `vehiculo` y `generador` se
 *     dan de baja lógica (invariante 8); sus ids quedan en
 *     `respaldo_operacion_interna.per_equipo_recursos_retirados`.
 *   - Los permisos `finanzas.*`, `inventario.*` y
 *     `mantenimiento.(vehiculo|generador|orden|plan).*`, sus asignaciones a
 *     roles y las entradas de menú asociadas se dan de baja lógica; las filas
 *     afectadas quedan copiadas en el mismo esquema de respaldo.
 *
 * Ninguna fila de `plt_bitacoras` se toca: el historial de auditoría de lo
 * retirado sigue legible.
 *
 * Retiro definitivo del respaldo (cuando el dueño lo confirme): volcar el
 * esquema (`pg_dump --schema=respaldo_operacion_interna`), guardar el archivo y
 * recién entonces `DROP SCHEMA respaldo_operacion_interna CASCADE`. Ver
 * `docs/decisiones/0035-retiro-de-la-operacion-interna.md`.
 *
 * Solo PostgreSQL (los tests de `tests/Unit` no corren migraciones sobre
 * esquemas): en otro motor no hace nada.
 */
return new class extends Migration
{
    private const ESQUEMA = 'respaldo_operacion_interna';

    /** @var list<string> */
    private const TABLAS = [
        'fin_devengos_personal',
        'fin_planilla_detalles',
        'fin_planillas',
        'fin_anticipos',
        'fin_combustibles',
        'fin_gastos',
        'fin_rendiciones',
        'fin_subrubros',
        'fin_rubros',
        'inv_movimientos',
        'inv_stock',
        'inv_repuestos',
        'man_ordenes_mantenimiento',
        'man_planes_mantenimiento',
        'man_generadores',
        'man_vehiculos',
        'ope_orden_trabajo_equipos',
        'fin_tarifas',
    ];

    /** @var list<string> */
    private const PERMISOS = [
        'finanzas.anticipo.crear', 'finanzas.anticipo.eliminar', 'finanzas.anticipo.ver',
        'finanzas.combustible.crear', 'finanzas.combustible.eliminar', 'finanzas.combustible.ver',
        'finanzas.devengo.ver',
        'finanzas.gasto.crear', 'finanzas.gasto.eliminar', 'finanzas.gasto.ver',
        'finanzas.planilla.aprobar', 'finanzas.planilla.generar', 'finanzas.planilla.ver',
        'finanzas.rendicion.aprobar', 'finanzas.rendicion.crear', 'finanzas.rendicion.presentar', 'finanzas.rendicion.ver',
        'finanzas.tarifa.crear', 'finanzas.tarifa.editar', 'finanzas.tarifa.eliminar', 'finanzas.tarifa.ver',
        'inventario.movimiento.crear', 'inventario.movimiento.ver',
        'inventario.repuesto.crear', 'inventario.repuesto.editar', 'inventario.repuesto.eliminar', 'inventario.repuesto.ver',
        'mantenimiento.generador.crear', 'mantenimiento.generador.editar', 'mantenimiento.generador.eliminar', 'mantenimiento.generador.ver',
        'mantenimiento.orden.cerrar', 'mantenimiento.orden.crear', 'mantenimiento.orden.editar', 'mantenimiento.orden.ver',
        'mantenimiento.plan.crear', 'mantenimiento.plan.editar', 'mantenimiento.plan.eliminar', 'mantenimiento.plan.ver',
        'mantenimiento.vehiculo.crear', 'mantenimiento.vehiculo.editar', 'mantenimiento.vehiculo.eliminar', 'mantenimiento.vehiculo.ver',
    ];

    /** @var list<string> */
    private const MENU = [
        'menu.recursos.items.vehiculos',
        'menu.recursos.items.generadores',
        'menu.mantenimiento.items.ordenes',
        'menu.mantenimiento.items.planes',
        'menu.mantenimiento.items.repuestos',
        'menu.mantenimiento.items.stock',
        'menu.financiero.items.gastos',
        'menu.financiero.items.combustible',
        'menu.financiero.items.rendiciones',
        'menu.financiero.items.devengos',
        'menu.financiero.items.planilla',
        'menu.financiero.items.anticipos',
        'menu.financiero.items.tarifas',
        'menu.mantenimiento.label',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $p = DB::getTablePrefix();
        $esquema = self::ESQUEMA;

        DB::statement("CREATE SCHEMA IF NOT EXISTS {$esquema}");

        $this->respaldarYQuitarVehiculoDeEstadias($p, $esquema);
        $this->darDeBajaRecursosDeCuadrilla($p, $esquema);
        $this->darDeBajaCatalogoDeSeguridad($p, $esquema);

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

        // `fin_tarifas` y las demás vuelven en el orden inverso al de `up()`.
        foreach (array_reverse(self::TABLAS) as $tabla) {
            if ($this->existeEnRespaldo($p.$tabla)) {
                DB::statement("ALTER TABLE {$esquema}.{$p}{$tabla} SET SCHEMA public");
            }
        }

        $this->restaurarCatalogoDeSeguridad($p, $esquema);
        $this->restaurarRecursosDeCuadrilla($p, $esquema);
        $this->restaurarVehiculoDeEstadias($p, $esquema);

        DB::statement("DROP SCHEMA IF EXISTS {$esquema}");
    }

    private function respaldarYQuitarVehiculoDeEstadias(string $p, string $esquema): void
    {
        if (! Schema::hasColumn('ope_estadias_hacienda', 'vehiculo_id')) {
            return;
        }

        DB::statement("CREATE TABLE IF NOT EXISTS {$esquema}.{$p}ope_estadias_vehiculo AS SELECT id, vehiculo_id FROM {$p}ope_estadias_hacienda WHERE vehiculo_id IS NOT NULL");
        DB::statement("ALTER TABLE {$p}ope_estadias_hacienda DROP CONSTRAINT IF EXISTS {$p}ope_estadias_hacienda_vehiculo_id_foreign");
        DB::statement("ALTER TABLE {$p}ope_estadias_hacienda DROP COLUMN vehiculo_id");
    }

    private function restaurarVehiculoDeEstadias(string $p, string $esquema): void
    {
        if (Schema::hasColumn('ope_estadias_hacienda', 'vehiculo_id')) {
            return;
        }

        DB::statement("ALTER TABLE {$p}ope_estadias_hacienda ADD COLUMN vehiculo_id bigint NULL");

        if ($this->existeEnRespaldo($p.'ope_estadias_vehiculo')) {
            DB::statement("UPDATE {$p}ope_estadias_hacienda e SET vehiculo_id = r.vehiculo_id FROM {$esquema}.{$p}ope_estadias_vehiculo r WHERE r.id = e.id");
            DB::statement("DROP TABLE {$esquema}.{$p}ope_estadias_vehiculo");
        }

        DB::statement("ALTER TABLE {$p}ope_estadias_hacienda ADD CONSTRAINT {$p}ope_estadias_hacienda_vehiculo_id_foreign FOREIGN KEY (vehiculo_id) REFERENCES {$p}man_vehiculos (id) ON DELETE RESTRICT");
    }

    private function darDeBajaRecursosDeCuadrilla(string $p, string $esquema): void
    {
        DB::statement("CREATE TABLE IF NOT EXISTS {$esquema}.{$p}per_equipo_recursos_retirados AS SELECT id FROM {$p}per_equipo_recursos WHERE recurso_tipo IN ('vehiculo', 'generador') AND deleted_at IS NULL");
        DB::statement("UPDATE {$p}per_equipo_recursos SET deleted_at = now(), updated_at = now() WHERE recurso_tipo IN ('vehiculo', 'generador') AND deleted_at IS NULL");
    }

    private function restaurarRecursosDeCuadrilla(string $p, string $esquema): void
    {
        if (! $this->existeEnRespaldo($p.'per_equipo_recursos_retirados')) {
            return;
        }

        DB::statement("UPDATE {$p}per_equipo_recursos SET deleted_at = NULL, updated_at = now() WHERE id IN (SELECT id FROM {$esquema}.{$p}per_equipo_recursos_retirados)");
        DB::statement("DROP TABLE {$esquema}.{$p}per_equipo_recursos_retirados");
    }

    private function darDeBajaCatalogoDeSeguridad(string $p, string $esquema): void
    {
        $codigos = $this->lista(self::PERMISOS);
        $etiquetas = $this->lista(self::MENU);

        DB::statement("CREATE TABLE IF NOT EXISTS {$esquema}.{$p}sec_permission_retirados AS SELECT id FROM {$p}sec_permission WHERE code IN ({$codigos}) AND deleted_at IS NULL");
        DB::statement("CREATE TABLE IF NOT EXISTS {$esquema}.{$p}sec_role_permission_retirados AS SELECT id FROM {$p}sec_role_permission WHERE deleted_at IS NULL AND id_permission IN (SELECT id FROM {$p}sec_permission WHERE code IN ({$codigos}))");
        DB::statement("CREATE TABLE IF NOT EXISTS {$esquema}.{$p}sec_menu_retirados AS SELECT id FROM {$p}sec_menu WHERE label IN ({$etiquetas}) AND deleted_at IS NULL");

        DB::statement("UPDATE {$p}sec_role_permission SET deleted_at = now(), updated_at = now() WHERE id IN (SELECT id FROM {$esquema}.{$p}sec_role_permission_retirados)");
        DB::statement("UPDATE {$p}sec_permission SET deleted_at = now(), updated_at = now() WHERE id IN (SELECT id FROM {$esquema}.{$p}sec_permission_retirados)");
        DB::statement("UPDATE {$p}sec_menu SET deleted_at = now(), updated_at = now() WHERE id IN (SELECT id FROM {$esquema}.{$p}sec_menu_retirados)");
    }

    private function restaurarCatalogoDeSeguridad(string $p, string $esquema): void
    {
        foreach ([
            'sec_menu' => 'sec_menu_retirados',
            'sec_permission' => 'sec_permission_retirados',
            'sec_role_permission' => 'sec_role_permission_retirados',
        ] as $tabla => $respaldo) {
            if (! $this->existeEnRespaldo($p.$respaldo)) {
                continue;
            }

            DB::statement("UPDATE {$p}{$tabla} SET deleted_at = NULL, updated_at = now() WHERE id IN (SELECT id FROM {$esquema}.{$p}{$respaldo})");
            DB::statement("DROP TABLE {$esquema}.{$p}{$respaldo}");
        }
    }

    private function existeEnRespaldo(string $tabla): bool
    {
        return DB::table('information_schema.tables')
            ->where('table_schema', self::ESQUEMA)
            ->where('table_name', $tabla)
            ->exists();
    }

    /** @param list<string> $valores */
    private function lista(array $valores): string
    {
        return implode(', ', array_map(fn (string $valor): string => "'".str_replace("'", "''", $valor)."'", $valores));
    }
};
