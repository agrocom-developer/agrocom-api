<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El chat deja de ser solo texto: cada mensaje tiene un `tipo` (`texto`, `foto` o `sistema`).
 * Una foto guarda su archivo en el disco privado con su hash y su peso (las reglas de las
 * evidencias). Un mensaje de sistema lo escribe el servidor al cambiar la solicitud (`evento`
 * dice cuál) y no tiene autor: `autor_usuario_id` pasa a admitir nulo y `autor_rol` suma
 * `sistema`. Los mensajes existentes quedan como `texto`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('neg_mensajes', function (Blueprint $table) {
            $table->string('tipo', 10)->default('texto')->after('autor_rol');
            $table->string('evento', 40)->nullable()->after('tipo');
            $table->string('archivo')->nullable()->after('texto');
            $table->string('hash', 64)->nullable()->after('archivo');
            $table->unsignedInteger('bytes')->nullable()->after('hash');
            $table->unsignedBigInteger('autor_usuario_id')->nullable()->change();
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $p = DB::getTablePrefix();

        DB::statement("ALTER TABLE {$p}neg_mensajes DROP CONSTRAINT {$p}neg_mensajes_rol_chk");
        DB::statement("ALTER TABLE {$p}neg_mensajes ADD CONSTRAINT {$p}neg_mensajes_rol_chk CHECK (autor_rol IN ('cliente', 'proveedor', 'sistema'))");
        DB::statement("ALTER TABLE {$p}neg_mensajes ADD CONSTRAINT {$p}neg_mensajes_tipo_chk CHECK (tipo IN ('texto', 'foto', 'sistema'))");
        DB::statement("ALTER TABLE {$p}neg_mensajes ADD CONSTRAINT {$p}neg_mensajes_coherencia_chk CHECK ((tipo = 'foto') = (archivo IS NOT NULL) AND (tipo = 'sistema') = (evento IS NOT NULL) AND (tipo = 'sistema') = (autor_rol = 'sistema') AND (autor_usuario_id IS NULL) = (tipo = 'sistema'))");
    }

    public function down(): void
    {
        if (DB::table('neg_mensajes')->where('tipo', '!=', 'texto')->exists()) {
            throw new RuntimeException('Hay fotos o eventos de sistema en neg_mensajes: no caben en el esquema anterior; retíralos antes de revertir esta migración.');
        }

        if (DB::getDriverName() === 'pgsql') {
            $p = DB::getTablePrefix();

            DB::statement("ALTER TABLE {$p}neg_mensajes DROP CONSTRAINT {$p}neg_mensajes_coherencia_chk");
            DB::statement("ALTER TABLE {$p}neg_mensajes DROP CONSTRAINT {$p}neg_mensajes_tipo_chk");
            DB::statement("ALTER TABLE {$p}neg_mensajes DROP CONSTRAINT {$p}neg_mensajes_rol_chk");
            DB::statement("ALTER TABLE {$p}neg_mensajes ADD CONSTRAINT {$p}neg_mensajes_rol_chk CHECK (autor_rol IN ('cliente', 'proveedor'))");
        }

        Schema::table('neg_mensajes', function (Blueprint $table) {
            $table->unsignedBigInteger('autor_usuario_id')->nullable(false)->change();
            $table->dropColumn(['tipo', 'evento', 'archivo', 'hash', 'bytes']);
        });
    }
};
