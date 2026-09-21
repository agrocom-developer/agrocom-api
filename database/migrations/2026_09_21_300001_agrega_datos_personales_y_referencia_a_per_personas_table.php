<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La persona de campo deja de ser un solo «nombre» (pedido del dueño,
 * 21/9/2026): quien opera un dron de 20 mil dólares tiene que quedar bien
 * identificado y con referencias. Se agregan:
 *
 * - Datos personales: `nombres`, `apellido_paterno`, `apellido_materno`, `ci`.
 * - Datos de referencia: `celular`, `correo`, `direccion`.
 *
 * `nombre` NO se va: es el nombre completo que leen el resto del panel, los
 * otros módulos (por contrato) y la app de campo. Desde ahora lo componen
 * `CrearPersona`/`ActualizarPersona` a partir de las tres partes.
 *
 * Datos existentes: el `nombre` entero pasa a `nombres` y los apellidos quedan
 * vacíos. No se adivina el corte — «Luis Fernando Justiniano» y «Marcelo Vaca
 * Roca» tienen tres palabras y se parten distinto —; el formulario pide
 * completarlos la próxima vez que se edite la persona. Por eso todo lo nuevo
 * es nullable en la base y obligatorio recién en la validación del panel.
 *
 * `ci` es único entre las personas vivas (índice parcial, por el soft delete).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('per_personas', function (Blueprint $table) {
            $table->string('nombres', 80)->nullable()->after('nombre');
            $table->string('apellido_paterno', 80)->nullable()->after('nombres');
            $table->string('apellido_materno', 80)->nullable()->after('apellido_paterno');
            $table->string('ci', 20)->nullable()->after('apellido_materno');
            $table->string('celular', 20)->nullable()->after('ci');
            $table->string('correo', 150)->nullable()->after('celular');
            $table->string('direccion', 255)->nullable()->after('correo');
        });

        DB::table('per_personas')->update(['nombres' => DB::raw('nombre')]);

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}per_personas_ci_unico
            ON {$prefijo}per_personas (ci)
            WHERE deleted_at IS NULL AND ci IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        $prefijo = DB::getTablePrefix();

        DB::statement("DROP INDEX IF EXISTS {$prefijo}per_personas_ci_unico");

        Schema::table('per_personas', function (Blueprint $table) {
            $table->dropColumn(['nombres', 'apellido_paterno', 'apellido_materno', 'ci', 'celular', 'correo', 'direccion']);
        });
    }
};
