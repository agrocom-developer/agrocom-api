<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — evidencia (espec §4.3, fila `evidencias`; TE-07 parte
 * servidor, tarea 19). Registra el HECHO de que un archivo (captura de RC,
 * foto de campo, foto de incidencia, comprobante, firma de acta) quedó
 * subido y verificado — nunca la compresión ni la cola de reintentos, que son
 * de `agrocom-field` (espec §2 punto 4, fuera de este repo).
 *
 * Vive en `Operaciones` y no en un módulo propio: sin lógica de negocio,
 * sin máquina de estados, sin ciclo de vida — mismo criterio que
 * `ope_condiciones` (tarea 17) y `ope_recepciones_caldo` (tarea 18), ver
 * runs/19.md "decisión de módulo". Cuando `Finanzas` necesite `comprobante` o
 * algo necesite `firma_acta`, referencia esta tabla por `id`/`uuid_cliente`
 * plano (ADR 0003, regla 3) — no hace falta mover la tabla.
 *
 * Nace en la app de campo con su `uuid_cliente` (invariante 1), mismo
 * `UNIQUE` parcial que el resto de las tablas de sync — aunque este registro
 * NO viaja en el lote de `POST /api/sync` (el "sobre" de ese endpoint es JSON
 * puro, sin binarios): llega por `POST /api/evidencias`,
 * `multipart/form-data`, endpoint dedicado (espec §2.1 punto 7: "evidencias
 * en cola separada"). El mecanismo de idempotencia es el mismo índice único
 * de siempre — lo que cambia es el transporte, no el contrato de
 * `uuid_cliente`.
 *
 * `hash`: SHA-256 del contenido, calculado por el SERVIDOR al recibir el
 * archivo — nunca se confía en un hash que declare el cliente.
 *
 * `subido_por` referencia `per_personas` solo por FK + entero plano (ADR
 * 0003, regla 3), nullable: igual criterio que `Condiciones`/`RecepcionCaldo`
 * (la espec no fija un dueño obligatorio para este registro — cualquier
 * operario legítimo puede subir evidencia). Se resuelve del token del
 * dispositivo que firma el request (`IdentidadOperarioToken`), nunca de un
 * campo que declare el propio formulario — evita que el cliente falsifique
 * quién subió el archivo.
 *
 * `tipo`: catálogo cerrado de la espec (`captura_rc`, `imagen_campo`,
 * `foto_incidencia`, `comprobante`, `firma_acta`), `CHECK` en Postgres —
 * mismo patrón que `momento` en `ope_condiciones`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ope_evidencias', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->string('tipo', 20);
            $table->string('archivo_url');
            $table->string('hash', 64);
            $table->foreignId('subido_por')->nullable()->constrained('per_personas')->nullOnDelete();
            $table->dateTime('fecha');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tipo');
        });

        $prefijo = DB::getTablePrefix();

        // Idempotencia real (invariante 1): un reintento del mismo
        // uuid_cliente choca acá, nunca con un SELECT previo en el caso de
        // uso — es lo que permite decidir "duplicado" sin volver a escribir
        // el archivo ya guardado (ver RegistrarEvidencia).
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_evidencias_uuid_cliente_unico
            ON {$prefijo}ope_evidencias (uuid_cliente)
            WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_evidencias
                ADD CONSTRAINT {$prefijo}ope_evidencias_tipo_chk
                    CHECK (tipo IN ('captura_rc', 'imagen_campo', 'foto_incidencia', 'comprobante', 'firma_acta'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_evidencias');
    }
};
