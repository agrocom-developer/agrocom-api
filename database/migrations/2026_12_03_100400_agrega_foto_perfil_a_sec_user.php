<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `sec_user.foto_perfil_ref`: la foto de perfil de la persona de la cuenta (cliente,
 * titular, piloto o auxiliar). Es una referencia al archivo en el disco privado
 * (menos de 300 KB); nunca una URL pública.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sec_user', function (Blueprint $table) {
            $table->string('foto_perfil_ref', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sec_user', function (Blueprint $table) {
            $table->dropColumn('foto_perfil_ref');
        });
    }
};
