<?php

use App\Dominios\Comercial\Dominio\ColorPropiedad;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Módulo Comercial — amplía el CHECK de `com_propiedades.color` de 16 a 23
 * valores (16/9/2026, misma tarde que la ampliación anterior de 13 a 16 —
 * ver `2026_09_16_100009_...`): el campo pasó de modal a popup anclado y el
 * dueño pidió más opciones dos veces seguidas ("cierto quiero más colores",
 * después "no tenga miedo de usar también el color negro"). Puramente
 * aditivo — 7 valores nuevos (Caqui/Salvia/Acero/Aciano/Vino/Terracota/Negro),
 * ningún valor anterior cambia, así que ninguna fila existente puede violar
 * el CHECK nuevo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $prefijo = DB::getTablePrefix();
        $listaColores = collect(ColorPropiedad::cases())
            ->map(fn ($color) => "'{$color->value}'")
            ->implode(', ');

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}com_propiedades
            DROP CONSTRAINT {$prefijo}com_propiedades_color_chk
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}com_propiedades
            ADD CONSTRAINT {$prefijo}com_propiedades_color_chk
                CHECK (color IS NULL OR color IN ({$listaColores}))
        SQL);
    }

    public function down(): void
    {
        // Sin vuelta atrás significativa, mismo criterio que la migración
        // anterior de ampliación (100009): si alguna propiedad ya usa uno
        // de los 7 colores nuevos, restaurar el CHECK de 16 rompería esa
        // fila.
    }
};
