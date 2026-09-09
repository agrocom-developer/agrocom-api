<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — pausas con causa atribuible (HU-44, tarea 58;
 * `docs/gestion/respuestas_campo/analisis_clasificacion.md:52`, DS-01: "las
 * pausas y demoras nunca se registran... es la información que falta
 * siempre"). A diferencia de `ope_incidencias`/`ope_condiciones`, esta tabla
 * NO nace en la app de campo: la HU dice "jefe de campo" desde el panel, sin
 * mencionar el motor de sync — así que no lleva `uuid_cliente` ni pasa por
 * `EscrituraSincronizacionEloquent` (mismo criterio que `fin_gastos`,
 * cargado a mano desde el panel).
 *
 * `causa`: catálogo cerrado (DS-01 + maqueta del dashboard),
 * `CHECK` en Postgres — mismo patrón que `tipo` en `ope_incidencias`. Incluye
 * `imprevisto_del_cliente` (mención textual de DS-01: "insumos del cliente
 * que no llegan").
 *
 * `duracion_minutos` se GUARDA (no se calcula al leer): el agregado por
 * causa del tablero (CA de la HU) es un `SUM` directo en SQL, portable entre
 * SQLite (tests) y Postgres (prod) sin depender de funciones de diferencia de
 * fechas específicas de cada motor — mismo criterio de exactitud que
 * invariante 6 de CLAUDE.md aplica a dinero/hectáreas. Lo calcula
 * `Aplicacion/RegistrarPausa` a partir de `inicio`/`fin`, nunca al vuelo.
 *
 * `inicio`/`fin` son `dateTime` SIN tz (mismo patrón que `ope_sesiones`): el
 * caso de uso normaliza a UTC con `->utc()` antes de guardar (mismo cuidado
 * que `MaquinaEstadosSesion`, tarea 29 — ver su docblock para el porqué).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ope_pausas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_id')->constrained('ope_sesiones')->restrictOnDelete();
            $table->string('causa', 30);
            $table->dateTime('inicio');
            $table->dateTime('fin');
            $table->unsignedInteger('duracion_minutos');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('sesion_id');
            $table->index('causa');
        });

        $prefijo = DB::getTablePrefix();

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_pausas
                ADD CONSTRAINT {$prefijo}ope_pausas_causa_chk
                    CHECK (causa IN ('clima', 'imprevisto_del_cliente', 'cambio_lote_cliente', 'falla_equipo', 'logistica')),
                ADD CONSTRAINT {$prefijo}ope_pausas_fin_posterior_a_inicio_chk
                    CHECK (fin > inicio),
                ADD CONSTRAINT {$prefijo}ope_pausas_duracion_minutos_chk
                    CHECK (duracion_minutos > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_pausas');
    }
};
