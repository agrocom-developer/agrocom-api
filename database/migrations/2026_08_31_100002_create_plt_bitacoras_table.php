<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de auditoría transversal (ADR 0007, invariante 9 de CLAUDE.md;
 * prefijo `plt_` — "plataforma" — asignado en ADR 0011, extensión 31/8/2026).
 *
 * No es una tabla de dominio de ningún módulo de negocio: vive en
 * `Compartido/` porque la escribe el observer de plataforma
 * `App\Dominios\Compartido\Infraestructura\Eloquent\BitacoraObserver`, nunca
 * un caso de uso a mano (eso es justo lo que el ADR 0007 descartó — ver sus
 * "Alternativas descartadas"). Por eso NO sigue el molde de
 * `ModeloDominio` que usa el resto del esquema:
 *
 * - Sin `deleted_at`: es un libro de solo-inserción (append-only). Admitir
 *   borrado lógico de una entrada de auditoría abriría la puerta a que una
 *   fila "desaparezca" de los listados por defecto y quede indistinguible de
 *   una bitácora incompleta — exactamente lo que esta tabla existe para
 *   impedir. Ningún caso de uso hace UPDATE ni DELETE sobre `plt_bitacoras`.
 * - Sin `created_by`/`updated_by`: el actor de la mutación auditada ya vive
 *   en `user_id` (la pregunta "quién" de la invariante 9); agregarle autoría
 *   propia a la fila que YA ES un registro de autoría es un bucle sin
 *   sentido de negocio.
 * - Un solo timestamp (`created_at`, sin `updated_at`): la fila no se
 *   actualiza jamás.
 *
 * `user_id` (nullable): un seeder o un comando no autentican a nadie
 * (`Auth::id()` devuelve null), y la mutación sigue siendo real y digna de
 * quedar registrada.
 *
 * `tabla` + `registro_id`: la entidad afectada, como pide la tarea — nombre
 * físico de tabla (no el modelo/FQCN, que puede moverse de módulo sin que la
 * fila física cambie) más el id de la fila.
 *
 * `antes`/`despues` (jsonb, nullable): solo las columnas que cambiaron, nunca
 * la fila entera — y nunca `password`/`token`/`remember_token` (ver
 * `BitacoraObserver::COLUMNAS_SENSIBLES`). En una creación `antes` es NULL
 * (no había fila); en un borrado/restauración solo llevan `deleted_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plt_bitacoras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('sec_user')->restrictOnDelete();
            $table->string('tabla', 63);
            $table->unsignedBigInteger('registro_id');
            $table->string('accion', 20);
            $table->jsonb('antes')->nullable();
            $table->jsonb('despues')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tabla', 'registro_id']);
            $table->index('user_id');
        });

        // Rangos y enums en la base, no solo en la aplicación (ADR 0001).
        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}plt_bitacoras
                ADD CONSTRAINT {$prefijo}plt_bitacoras_accion_chk
                    CHECK (accion IN ('creado', 'actualizado', 'eliminado'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plt_bitacoras');
    }
};
