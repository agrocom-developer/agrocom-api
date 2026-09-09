<?php

use App\Dominios\Seguridad\Aplicacion\ObtenerMenuPorRolActivo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Seguridad — `requiere_persona` de `sec_menu`.
 *
 * El menú se filtra por el PERMISO del rol activo (ADR 0004; CLAUDE.md
 * invariante 10), y eso alcanza para casi todo el panel: quien tiene el
 * permiso, puede abrir la pantalla. Hay una excepción, y es la que motiva
 * esta columna: las pantallas de "lo mío" no se resuelven por rol sino por
 * PERSONA. `GET /panel/devengos` (HU-28) redirige a los devengos de la
 * persona del usuario autenticado y hace `abort(404)` si esa persona no
 * existe — un usuario interno sin `persona_id` tiene el permiso
 * `finanzas.devengo.ver` (lo tienen `piloto`, `auxiliar` y `dueno`), ve el
 * ítem en el sidebar, y al hacer clic recibe un 404. El permiso no miente:
 * el actor SÍ puede ver devengos. Lo que falta es el sujeto.
 *
 * La alternativa era un `if` con el nombre de la ruta adentro de
 * {@see ObtenerMenuPorRolActivo} —
 * conocimiento de una pantalla concreta metido en el filtro genérico del
 * menú, que además habría que ampliar con cada pantalla nueva de "lo mío"
 * (mis anticipos, mis sesiones). Esta columna deja la regla donde ya vive el
 * resto de la visibilidad del menú: en el catálogo, como dato.
 *
 * `false` por default y NOT NULL: la enorme mayoría de los ítems se gobierna
 * solo por permiso, y el default conserva ese comportamiento para todo lo ya
 * sembrado. Hoy `SecMenuSeeder` lo pone en `true` en un único ítem
 * (Financiero › Devengos).
 *
 * No reemplaza al chequeo del controlador: `DevengosController` sigue
 * haciendo su propio `abort_if($personaId === null, 404)`. Ocultar el ítem es
 * cortesía de navegación, nunca la autorización — un ítem oculto no protege
 * una URL tipeada a mano (invariante 2 del modelo de seguridad).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sec_menu', function (Blueprint $table) {
            $table->boolean('requiere_persona')
                ->default(false)
                ->after('permission_id');
        });
    }

    public function down(): void
    {
        Schema::table('sec_menu', function (Blueprint $table) {
            $table->dropColumn('requiere_persona');
        });
    }
};
