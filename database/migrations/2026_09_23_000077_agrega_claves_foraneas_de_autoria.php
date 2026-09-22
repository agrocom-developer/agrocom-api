<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Claves foráneas de autoría (`created_by` / `updated_by` → `sec_user.id`,
 * `ON DELETE SET NULL`) de todas las tablas de dominio, consolidadas al
 * 2026_09_23 (ADR 0024). Van al final, y no en cada `create`, porque
 * `sec_user` apunta a `per_personas` y `per_personas` tiene autoría: el ciclo
 * solo se cierra después de crear todas las tablas — mismo criterio que la
 * legada `2026_09_02_100003_add_fk_autoria_a_tablas_dominio`. Las tablas sin
 * fila acá todavía no tienen la FK (retrofit pendiente, ver skill modelo-datos).
 *
 * En una base legada las restricciones ya existen: se saltea cada una que exista.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> tabla => columnas con FK de autoría */
    private array $tablas = [
        'com_clientes' => ['created_by', 'updated_by'],
        'com_cliente_contactos' => ['created_by', 'updated_by'],
        'com_contratos' => ['created_by', 'updated_by'],
        'com_propiedades' => ['created_by', 'updated_by'],
        'com_lotes' => ['created_by', 'updated_by'],
        'per_bases' => ['created_by', 'updated_by'],
        'per_personas' => ['created_by', 'updated_by'],
        'ope_evidencias' => ['created_by', 'updated_by'],
        'ope_ordenes_aplicacion' => ['created_by', 'updated_by'],
        'ope_trabajos' => ['created_by', 'updated_by'],
        'ope_actas' => ['created_by', 'updated_by'],
        'dis_versiones_apk' => ['created_by', 'updated_by'],
        'ope_drones' => ['created_by', 'updated_by'],
        'ope_sesiones' => ['created_by', 'updated_by'],
        'fin_devengos_personal' => ['created_by', 'updated_by'],
        'sec_user' => ['created_by', 'updated_by'],
        'ope_condiciones' => ['created_by', 'updated_by'],
        'ope_recargas' => ['created_by', 'updated_by'],
        'ope_alertas' => ['created_by', 'updated_by'],
        'ope_incidencias' => ['created_by', 'updated_by'],
        'ope_recepciones_caldo' => ['created_by', 'updated_by'],
        'ope_reportes_tecnicos' => ['created_by', 'updated_by'],
        'ope_sesion_rechazos' => ['created_by', 'updated_by'],
        'sec_permission' => ['created_by', 'updated_by'],
        'sec_menu' => ['created_by', 'updated_by'],
        'sec_role' => ['created_by', 'updated_by'],
        'sec_role_permission' => ['created_by', 'updated_by'],
        'sec_token_dispositivo' => ['created_by', 'updated_by'],
        'sec_user_preferencia' => ['created_by', 'updated_by'],
        'sec_user_role' => ['created_by', 'updated_by'],
    ];

    public function up(): void
    {
        foreach ($this->tablas as $tabla => $columnas) {
            foreach ($columnas as $columna) {
                if ($this->existe("{$tabla}_{$columna}_foreign")) {
                    continue;
                }

                Schema::table($tabla, function (Blueprint $table) use ($columna): void {
                    $table->foreign($columna)->references('id')->on('sec_user')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tablas as $tabla => $columnas) {
            Schema::table($tabla, function (Blueprint $table) use ($columnas): void {
                foreach ($columnas as $columna) {
                    $table->dropForeign([$columna]);
                }
            });
        }
    }

    private function existe(string $restriccion): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        return DB::table('pg_constraint')->where('conname', DB::getTablePrefix().$restriccion)->exists();
    }
};
