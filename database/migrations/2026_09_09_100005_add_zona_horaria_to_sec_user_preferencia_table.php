<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarea 63: zona horaria IANA del usuario (`America/La_Paz`), nullable —
 * se fija sola en el primer login (si el navegador la declara y es un
 * identificador válido de `DateTimeZone::listIdentifiers()`) y se puede
 * cambiar a mano desde el mismo lugar que el tema (ver
 * `ActualizarPreferenciaUsuario`/`FijarZonaHorariaUsuario`). Vale para
 * `interno` y `cliente`: es la misma tabla satélite de `sec_user` para
 * ambos guards, sin CHECK en base (mismo criterio que `idioma`: la validación
 * IANA vive en el caso de uso, no acá).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sec_user_preferencia', function (Blueprint $table) {
            $table->string('zona_horaria', 64)->nullable()->after('idioma');
        });
    }

    public function down(): void
    {
        Schema::table('sec_user_preferencia', function (Blueprint $table) {
            $table->dropColumn('zona_horaria');
        });
    }
};
