<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extensión `unaccent` de Postgres (consolidada al 2026_09_23, ADR 0024;
 * reemplaza a la legada `2026_09_09_120000_create_extension_unaccent`): la
 * usan los índices y las búsquedas sin acentos (`BusquedaTexto`). Va antes
 * que toda tabla porque algunos índices la necesitan. `IF NOT EXISTS`: en una
 * base legada ya está y no pasa nada.
 *
 * Sin `DROP EXTENSION` en `down()`: si otra cosa empezó a usarla, tirarla
 * rompería más de lo que revierte.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        }
    }

    public function down(): void
    {
        // Deliberadamente vacío.
    }
};
