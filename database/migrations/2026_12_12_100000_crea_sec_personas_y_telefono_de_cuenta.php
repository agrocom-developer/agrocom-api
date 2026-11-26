<?php

use App\Dominios\Seguridad\Dominio\NumeroDeTelefono;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Persona única, teléfono y correo por tipo de cuenta (ADR 0046, puntos 7 a 9 y 24 a 26;
 * plan de cuentas, fase A3).
 *
 * - `sec_personas`: una fila por persona real, con el documento (`documento_tipo` y
 *   `documento_numero` normalizado) como identidad única. Sin teléfono, correo ni fotos:
 *   el teléfono y el correo son de la cuenta.
 * - `sec_user.persona_id` pasa a apuntar a `sec_personas` y es nulo en las cuentas de panel.
 *   Lo que tenía hasta hoy (un id de `per_personas`, el modelo de personal anterior) se
 *   conserva en `persona_legada_id`, sin llave foránea, hasta el retiro de `per_*`; así no se
 *   pierde ningún vínculo. `down()` lo devuelve a `persona_id`.
 * - `sec_user.telefono` (E.164) y `email`, únicos POR TIPO de cuenta entre cuentas vivas: el
 *   mismo teléfono puede estar en una cuenta de cliente y en una de proveedor, nunca en dos
 *   del mismo tipo. `(persona_id, tipo_cuenta)` también es único: una persona real tiene a lo
 *   sumo una cuenta de cliente y una de proveedor. Reemplazan a los únicos globales de `email`
 *   y de `persona_id`.
 * - `telefono_verificado_en` / `telefono_verificado_por` y `email_verificado_en`: nulos hasta
 *   que haya una prueba de posesión (SMS o WhatsApp, o la comprobación manual del
 *   administrador). `persona_datos_difieren` marca la cuenta cuyos datos de persona no
 *   coinciden con los que la persona ya tenía, para quien revisa.
 *
 * Los teléfonos que ya estaban en la ficha de cada cuenta (`sol_clientes.telefono`,
 * `prv_proveedores.telefono`) se copian, normalizados, a la cuenta si no chocan con otra del
 * mismo tipo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sec_personas', function (Blueprint $table) {
            $table->id();
            $table->string('documento_tipo', 20);
            $table->string('documento_numero', 40);
            $table->string('nombres', 80);
            $table->string('apellidos', 80);
            $table->date('fecha_nacimiento')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $p = DB::getTablePrefix();

        DB::statement("CREATE UNIQUE INDEX {$p}sec_personas_documento_unico ON {$p}sec_personas (documento_tipo, documento_numero) WHERE deleted_at IS NULL");

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$p}sec_personas ADD CONSTRAINT {$p}sec_personas_documento_tipo_chk CHECK (documento_tipo IN ('ci', 'pasaporte', 'licencia_conducir'))");
        }

        Schema::table('sec_user', function (Blueprint $table) {
            $table->string('telefono', 20)->nullable()->after('email');
            $table->timestamp('telefono_verificado_en')->nullable()->after('telefono');
            $table->unsignedBigInteger('telefono_verificado_por')->nullable()->after('telefono_verificado_en');
            $table->timestamp('email_verificado_en')->nullable()->after('telefono_verificado_por');
            $table->unsignedBigInteger('persona_legada_id')->nullable()->after('persona_id');
            $table->boolean('persona_datos_difieren')->default(false)->after('persona_legada_id');
        });

        $this->repuntarPersona($p);
        $this->reemplazarUnicos($p);
        $this->copiarTelefonosDeLasFichas();
    }

    public function down(): void
    {
        $p = DB::getTablePrefix();

        foreach (['sec_user_telefono_tipo_unico', 'sec_user_email_tipo_unico', 'sec_user_persona_tipo_unico'] as $indice) {
            DB::statement("DROP INDEX IF EXISTS {$p}{$indice}");
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$p}sec_user DROP CONSTRAINT IF EXISTS {$p}sec_user_persona_id_sec_personas_foreign");
        }

        // El vínculo con `per_personas` vuelve a `persona_id`; el de `sec_personas` se descarta.
        DB::table('sec_user')->update(['persona_id' => null]);
        DB::table('sec_user')->whereNotNull('persona_legada_id')->update(['persona_id' => DB::raw('persona_legada_id')]);

        DB::statement("CREATE UNIQUE INDEX {$p}sec_user_email_unico ON {$p}sec_user (email) WHERE (email IS NOT NULL) AND (deleted_at IS NULL)");
        DB::statement("CREATE UNIQUE INDEX {$p}sec_user_persona_id_unico ON {$p}sec_user (persona_id) WHERE (persona_id IS NOT NULL) AND (deleted_at IS NULL)");

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$p}sec_user ADD CONSTRAINT {$p}sec_user_persona_id_foreign FOREIGN KEY (persona_id) REFERENCES {$p}per_personas (id) ON DELETE RESTRICT");
        }

        Schema::table('sec_user', function (Blueprint $table) {
            $table->dropColumn(['telefono', 'telefono_verificado_en', 'telefono_verificado_por', 'email_verificado_en', 'persona_legada_id', 'persona_datos_difieren']);
        });

        Schema::dropIfExists('sec_personas');
    }

    /** `persona_id` deja de apuntar a `per_personas`: el valor viejo pasa a `persona_legada_id`. */
    private function repuntarPersona(string $p): void
    {
        DB::table('sec_user')->whereNotNull('persona_id')->update(['persona_legada_id' => DB::raw('persona_id')]);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$p}sec_user DROP CONSTRAINT IF EXISTS {$p}sec_user_persona_id_foreign");
        }

        DB::statement("DROP INDEX IF EXISTS {$p}sec_user_persona_id_unico");
        DB::table('sec_user')->update(['persona_id' => null]);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$p}sec_user ADD CONSTRAINT {$p}sec_user_persona_id_sec_personas_foreign FOREIGN KEY (persona_id) REFERENCES {$p}sec_personas (id) ON DELETE RESTRICT");
        }
    }

    private function reemplazarUnicos(string $p): void
    {
        DB::statement("DROP INDEX IF EXISTS {$p}sec_user_email_unico");

        DB::statement("CREATE UNIQUE INDEX {$p}sec_user_persona_tipo_unico ON {$p}sec_user (persona_id, tipo_cuenta) WHERE (persona_id IS NOT NULL) AND (deleted_at IS NULL)");
        DB::statement("CREATE UNIQUE INDEX {$p}sec_user_telefono_tipo_unico ON {$p}sec_user (telefono, tipo_cuenta) WHERE (telefono IS NOT NULL) AND (deleted_at IS NULL)");
        DB::statement("CREATE UNIQUE INDEX {$p}sec_user_email_tipo_unico ON {$p}sec_user (email, tipo_cuenta) WHERE (email IS NOT NULL) AND (deleted_at IS NULL)");
    }

    /** Lo que cada cuenta ya tenía en su ficha pasa a la cuenta, sin pisar a otra del mismo tipo. */
    private function copiarTelefonosDeLasFichas(): void
    {
        $fichas = [
            ['sol_clientes', 'usuario_id'],
            ['prv_proveedores', 'usuario_titular_id'],
        ];

        foreach ($fichas as [$tabla, $columnaCuenta]) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'telefono')) {
                continue;
            }

            foreach (DB::table($tabla)->whereNotNull($columnaCuenta)->whereNotNull('telefono')->orderBy('id')->get(['telefono', $columnaCuenta]) as $ficha) {
                $normalizado = NumeroDeTelefono::normalizar((string) $ficha->telefono);
                $cuenta = DB::table('sec_user')->where('id', $ficha->{$columnaCuenta})->whereNull('telefono')->first(['id', 'tipo_cuenta']);

                if ($normalizado === null || $cuenta === null) {
                    continue;
                }

                $ocupado = DB::table('sec_user')->where('telefono', $normalizado)->where('tipo_cuenta', $cuenta->tipo_cuenta)->whereNull('deleted_at')->exists();

                if (! $ocupado) {
                    DB::table('sec_user')->where('id', $cuenta->id)->update(['telefono' => $normalizado]);
                }
            }
        }
    }
};
