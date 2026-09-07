<?php

use App\Dominios\Seguridad\Infraestructura\Http\Demo\DatosDemoCapturasRc;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — `captura_rc_id` de `ope_sesiones` (espec §4.3, tabla
 * `sesiones`: "cada sesión es una unidad de trabajo continua de un piloto con
 * un dron; **se cierra con su propia captura de RC**").
 *
 * La columna estaba en la especificación desde el día uno pero quedó fuera de
 * la tarea 09 como recorte explícito de alcance —"sin `captura_rc_id`
 * (evidencias, TE-07)", docblock de `Sesion`— porque `ope_evidencias` todavía
 * no existía. Existe desde la tarea 19, con `captura_rc` ya en el CHECK de
 * `tipo`; lo único que faltaba era el punto de enganche. Esta migración lo
 * agrega y cierra el recorte: la galería de evidencias del trabajo (HU-42) y
 * el PDF del reporte técnico (HU-18) pasan a mostrar la captura de RC de cada
 * sesión, que hasta ahora solo vivía como mock en el tab "Multimedia" del
 * dashboard ({@see DatosDemoCapturasRc}).
 *
 * Nullable, igual que `dron_id` (misma razón: las sesiones ya sembradas no la
 * tienen, y una sesión abierta todavía no llegó a su captura de cierre — el
 * dispositivo la sube al cerrar). `ON DELETE SET NULL` y no `RESTRICT`: la
 * evidencia es un adjunto de la sesión, no parte de su identidad, así que
 * retirar el archivo no puede bloquear el borrado — mismo criterio que
 * `ope_trabajos.imagen_campo_evidencia_id`, y a diferencia de
 * `ope_incidencias.evidencia_foto_id`, donde la foto ES la incidencia.
 *
 * Sin índice propio: se navega desde la sesión hacia la evidencia (FK como
 * puntero, siempre por PK del lado apuntado), nunca al revés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ope_sesiones', function (Blueprint $table) {
            $table->foreignId('captura_rc_id')
                ->nullable()
                ->after('dron_id')
                ->constrained('ope_evidencias')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ope_sesiones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('captura_rc_id');
        });
    }
};
