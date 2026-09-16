<?php

use App\Dominios\Comercial\Dominio\ColorPropiedad;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Módulo Comercial — amplía el CHECK de `com_propiedades.color` de 13 a 16
 * valores (16/9/2026): el campo pasó a elegirse desde un modal en vez de
 * competir por espacio en la fila del formulario, y con esa columna liberada
 * el dueño pidió más opciones ("así mismo aumentamos los colores para
 * escoger"). El CHECK anterior (`2026_09_16_100008_...`) quedó fijo con la
 * lista de 13 — `ColorPropiedad::cases()` cambió, pero un CHECK ya aplicado
 * no se actualiza solo. Puramente aditivo: ningún valor viejo se saca, así
 * que ninguna fila existente puede violar el CHECK nuevo.
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
        // No hay vuelta atrás significativa: si alguna propiedad ya usa uno
        // de los 3 colores nuevos, restaurar el CHECK viejo de 13 rompería
        // esa fila. El down de la migración anterior (100008) sigue siendo
        // el punto de restauración real si hace falta revertir del todo.
    }
};
