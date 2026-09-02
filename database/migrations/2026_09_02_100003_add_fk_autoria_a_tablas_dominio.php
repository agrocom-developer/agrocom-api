<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retrofit de integridad — FK real de `created_by`/`updated_by` a
 * `sec_user.id` en toda tabla de dominio (gap señalado en
 * `docs/gestion/estado_proyecto.md`, tarea 30).
 *
 * Deliberadamente en un solo pase para las ~30 tablas existentes, no
 * módulo por módulo: es lo que HU-01 dejó pendiente cuando creó `sec_user`
 * (las tablas de Comercial/Operaciones/Personal ya existían y su
 * `created_by` quedó como bigint suelto — ver comentario de
 * `com_clientes` y `per_bases`).
 *
 * `nullOnDelete()`, no `restrictOnDelete()`: la columna ya es nullable y
 * borrar un `sec_user` no debería bloquear ni arrastrar todo lo que ese
 * usuario auditó. `sec_user` es autorreferencial (el `created_by` de un
 * usuario puede apuntar a otro usuario, o a sí mismo en el seed inicial) —
 * caso válido, no se excluye.
 *
 * `plt_bitacoras` queda fuera: no tiene columnas `created_by`/`updated_by`
 * (el actor de la mutación ya vive en `user_id`, ver su migración) — no
 * hay nada a lo que agregarle la FK.
 *
 * SQLite no soporta agregar una FK a una tabla existente sin reconstruirla
 * (tabla temporal, copia de datos, drop, rename) — es lo que hace el propio
 * grammar de Laravel ante un comando `foreign` en `Schema::table()`. Esa
 * reconstrucción reintroduce los índices que detecta previos (vía
 * `PRAGMA index_list`, sin predicado), así que un índice único **parcial**
 * (`... WHERE deleted_at IS NULL`, el patrón de todo el esquema, ver skill
 * `modelo-datos`) queda aplanado a único pleno — bloqueando en silencio el
 * re-alta tras una baja lógica. En Postgres no aplica: `ALTER TABLE ... ADD
 * CONSTRAINT` es nativo, no reconstruye la tabla ni toca otros índices. Por
 * eso, solo en SQLite, se captura el SQL original de cada índice desde
 * `sqlite_master` antes de tocar la tabla y se restaura tal cual después.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tablas = [
        'com_clientes',
        'com_cliente_contactos',
        'com_contratos',
        'com_contrato_ventanas',
        'com_campos',
        'com_lotes',
        'ope_ordenes_aplicacion',
        'per_bases',
        'per_personas',
        'sec_role',
        'sec_permission',
        'sec_user',
        'sec_user_role',
        'sec_role_permission',
        'sec_user_preferencia',
        'sec_menu',
        'sec_token_dispositivo',
        'dis_versiones_apk',
        'ope_trabajos',
        'ope_sesiones',
        'ope_sesion_rechazos',
        'ope_condiciones',
        'ope_recepciones_caldo',
        'ope_evidencias',
        'ope_drones',
        'ope_incidencias',
        'ope_recargas',
        'fin_devengos_personal',
        'ope_actas',
        'ope_alertas',
        'ope_reportes_tecnicos',
    ];

    public function up(): void
    {
        foreach ($this->tablas as $tabla) {
            $indicesOriginales = $this->indicesSqliteDe($tabla);

            Schema::table($tabla, function (Blueprint $table) {
                $table->foreign('created_by')->references('id')->on('sec_user')->nullOnDelete();
                $table->foreign('updated_by')->references('id')->on('sec_user')->nullOnDelete();
            });

            $this->restaurarIndicesSqlite($indicesOriginales);
        }
    }

    public function down(): void
    {
        foreach ($this->tablas as $tabla) {
            $indicesOriginales = $this->indicesSqliteDe($tabla);

            Schema::table($tabla, function (Blueprint $table) {
                $table->dropForeign(['created_by']);
                $table->dropForeign(['updated_by']);
            });

            $this->restaurarIndicesSqlite($indicesOriginales);
        }
    }

    /**
     * @return list<object{name: string, sql: string}>
     */
    private function indicesSqliteDe(string $tabla): array
    {
        if (DB::getDriverName() !== 'sqlite') {
            return [];
        }

        $prefijo = DB::getTablePrefix();

        /** @var list<object{name: string, sql: string}> */
        return DB::select(
            "select name, sql from sqlite_master where type = 'index' and tbl_name = ? and sql is not null",
            ["{$prefijo}{$tabla}"]
        );
    }

    /**
     * @param  list<object{name: string, sql: string}>  $indices
     */
    private function restaurarIndicesSqlite(array $indices): void
    {
        foreach ($indices as $indice) {
            DB::statement("drop index if exists \"{$indice->name}\"");
            DB::statement($indice->sql);
        }
    }
};
