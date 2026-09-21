<?php

use App\Dominios\Comercial\Dominio\CicloVidaCultivo;
use App\Dominios\Comercial\Dominio\TipoCultivo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — atributos agronómicos de `com_cultivos` (HU-48, tarea
 * 71, ampliación 16/9/2026): el catálogo era solo nombre + activo; ahora
 * distingue nombre común de nombre científico (dos cultivos de la zona
 * pueden compartir nombre vulgar) y clasifica por tipo y ciclo de vida —
 * base para agrupar el catálogo en el formulario y en un futuro resumen por
 * cultivo.
 *
 * `nombre` → `nombre_comun`: ya no hay un solo "nombre", hay uno común y uno
 * científico — el rename evita que la columna vieja quede ambigua al lado
 * de la nueva. El índice único parcial se recrea con el nombre de columna
 * actualizado.
 *
 * `tipo_cultivo`/`ciclo_vida` nullable (los cultivos demo ya sembrados no lo
 * tenían al momento de esta migración) con CHECK pgsql-only restringido a
 * los valores de {@see TipoCultivo}/{@see CicloVidaCultivo} — mismo patrón
 * que `com_clientes.tipo_persona`/`com_propiedades.color`. El seeder de
 * catálogo completa el dato real para las filas ya sembradas.
 *
 * `notas_agronomicas`: texto libre, informativo — sugerencias de un
 * agrónomo (ej. mínimo de aplicaciones recomendado por campaña, tipo de
 * calda que tolera). No es una regla del sistema ni responsabilidad de
 * Agrocom (memoria "la mezcla es del cliente"): el motor de sesiones no lee
 * esta columna, es solo referencia para quien carga la siembra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('com_cultivos', function (Blueprint $table) {
            $table->renameColumn('nombre', 'nombre_comun');
        });

        Schema::table('com_cultivos', function (Blueprint $table) {
            $table->string('nombre_cientifico', 150)->nullable()->after('nombre_comun');
            $table->string('tipo_cultivo', 20)->nullable()->after('nombre_cientifico');
            $table->string('ciclo_vida', 20)->nullable()->after('tipo_cultivo');
            $table->text('notas_agronomicas')->nullable()->after('activo');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement("DROP INDEX {$prefijo}com_cultivos_nombre_unico");

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_cultivos_nombre_comun_unico
            ON {$prefijo}com_cultivos (nombre_comun)
            WHERE deleted_at IS NULL
        SQL);

        if (DB::getDriverName() === 'pgsql') {
            $tipos = collect(TipoCultivo::cases())->map(fn ($tipo) => "'{$tipo->value}'")->implode(', ');
            $ciclos = collect(CicloVidaCultivo::cases())->map(fn ($ciclo) => "'{$ciclo->value}'")->implode(', ');

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_cultivos
                ADD CONSTRAINT {$prefijo}com_cultivos_tipo_cultivo_chk
                    CHECK (tipo_cultivo IS NULL OR tipo_cultivo IN ({$tipos}))
            SQL);

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_cultivos
                ADD CONSTRAINT {$prefijo}com_cultivos_ciclo_vida_chk
                    CHECK (ciclo_vida IS NULL OR ciclo_vida IN ({$ciclos}))
            SQL);
        }
    }

    public function down(): void
    {
        $prefijo = DB::getTablePrefix();

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$prefijo}com_cultivos DROP CONSTRAINT {$prefijo}com_cultivos_ciclo_vida_chk");
            DB::statement("ALTER TABLE {$prefijo}com_cultivos DROP CONSTRAINT {$prefijo}com_cultivos_tipo_cultivo_chk");
        }

        DB::statement("DROP INDEX {$prefijo}com_cultivos_nombre_comun_unico");

        Schema::table('com_cultivos', function (Blueprint $table) {
            $table->dropColumn(['nombre_cientifico', 'tipo_cultivo', 'ciclo_vida', 'notas_agronomicas']);
        });

        Schema::table('com_cultivos', function (Blueprint $table) {
            $table->renameColumn('nombre_comun', 'nombre');
        });

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_cultivos_nombre_unico
            ON {$prefijo}com_cultivos (nombre)
            WHERE deleted_at IS NULL
        SQL);
    }
};
