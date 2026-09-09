<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `Compartido` (plataforma, no negocio) — configuración del sistema: llaves y
 * tokens con que se parametriza (mapas, correo, integraciones), tarea 78
 * (HU-55). Separada a propósito de `sec_datos_fiscales` (misma tarea): esto
 * es infraestructura configurable, no un dato de la empresa.
 *
 * `valor` es TEXT porque lleva el cast `encrypted` de Eloquent (cifrado en
 * reposo, catálogo `references/palette` no aplica acá — ver
 * `App\Dominios\Compartido\Infraestructura\Eloquent\Configuracion`): el
 * ciphertext de Laravel (base64 de un JSON con IV+MAC) no entra en un
 * `VARCHAR` corto. Nullable a propósito: guardar vacío NO borra un secreto ya
 * configurado (la pantalla lo exige explícito), así que `valor` puede ser
 * NULL sin que eso signifique "sin fila".
 *
 * `clave` única SOLO entre vivas (índice parcial, mismo patrón que
 * `sec_user_preferencia_user_id_unico`): un soft delete libera el nombre para
 * una fila nueva, sin arrastrar la vieja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plt_configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 150);
            $table->text('valor')->nullable();
            $table->string('grupo', 30);
            $table->string('descripcion', 255)->nullable();
            $table->boolean('es_secreto')->default(false);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}plt_configuraciones_clave_unico
            ON {$prefijo}plt_configuraciones (clave)
            WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite (tests locales) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}plt_configuraciones
                ADD CONSTRAINT {$prefijo}plt_configuraciones_grupo_chk
                    CHECK (grupo IN ('mapas', 'correo', 'integraciones'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plt_configuraciones');
    }
};
