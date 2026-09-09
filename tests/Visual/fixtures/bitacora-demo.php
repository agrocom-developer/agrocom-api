<?php

/**
 * Fixture de datos para tests/Visual/bitacora.spec.ts (tarea 63). La pantalla
 * necesita al menos tres filas de auditoría de TRES ENTIDADES distintas
 * (columna "Entidad" con variedad real) y con fecha fija (si no, la captura
 * se desactualiza sola al día siguiente — mismo criterio que `FECHA_FIJA` de
 * `tests/Visual/helpers.ts`).
 *
 * Actúa como el usuario demo (`carlos.ferrufino`, guard `interno`) ANTES de
 * mutar, para que `BitacoraObserver` capture un actor real (nombre +
 * username) en vez de "Sistema" — la pantalla necesita mostrar ambos casos,
 * y el resto de la suite visual ya deja de sobra filas con `user_id` NULL
 * (cualquier seeder de catálogo corrido sin sesión).
 *
 * `created_at`/`zona_horaria` de las filas de bitácora se fuerzan con un
 * UPDATE directo después de mutar (mismo mecanismo que
 * `tests/Feature/Seguridad/ListarBitacoraTest.php`: `Bitacora::$fillable` no
 * incluye `created_at` a propósito): sin esto, la fecha de la captura sería
 * "hoy" y el offset dependería de si `carlos.ferrufino` tiene zona horaria
 * fijada (no la toca este fixture — es un dato demo compartido por el resto
 * de la suite visual, no hay que pisarlo). Una de las cuatro filas queda con
 * `zona_horaria` distinta de UTC a propósito, para que la captura muestre la
 * línea "registrado en …"; la actualización del rol dobla como la fila con
 * diff poblado (antes/después) que la pantalla necesita mostrar.
 *
 * Bootea Laravel manualmente, mismo patrón que `combustible-demo.php` (Psy
 * Shell no ejecuta este bloque de forma confiable). Se invoca desde
 * `bitacora.spec.ts` vía `docker compose exec app php tests/Visual/fixtures/bitacora-demo.php`.
 *
 * Idempotente: `firstOrCreate`/comparación de valor antes de mutar, mismo
 * criterio que el resto de `tests/Visual/fixtures/`.
 */
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Generador;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require '/var/www/html/vendor/autoload.php';

$app = require_once '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$actor = SecUser::query()->where('username', 'carlos.ferrufino')->firstOrFail();
Auth::guard('interno')->login($actor);

function forzarInstante(string $tabla, int $registroId, string $accion, string $creadaEn, ?string $zonaHoraria): void
{
    DB::table('plt_bitacoras')
        ->where('tabla', $tabla)
        ->where('registro_id', $registroId)
        ->where('accion', $accion)
        ->latest('id')
        ->limit(1)
        ->update(['created_at' => $creadaEn, 'zona_horaria' => $zonaHoraria]);
}

// Entidad 1: per_bases (Personal) — creada, zona distinta de UTC para que la
// captura muestre "registrado en America/Asuncion".
$base = PerBase::firstOrCreate(['nombre' => 'Base Bitácora Demo'], ['ubicacion' => 'Santa Cruz de la Sierra']);
forzarInstante('per_bases', $base->id, 'creado', '2026-09-04 13:15:00', 'America/Asuncion');

// Entidad 2: man_generadores (Mantenimiento) — creada, sin zona registrada
// (caso "no hay dato", el trait la deja NULL igual que un actor sin
// preferencia fijada).
$generador = Generador::firstOrCreate(
    ['identificador' => 'GEN-BITACORA-DEMO'],
    ['base_id' => $base->id, 'estado' => 'activo'],
);
forzarInstante('man_generadores', $generador->id, 'creado', '2026-09-04 14:00:00', null);

// Entidad 3: sec_role (Seguridad) — creada y luego actualizada, para que la
// pantalla tenga al menos una fila "Actualizado" con diff antes/después
// poblado (la de creación deja `despues` completo y `antes` vacío).
$rol = SecRole::query()->firstOrCreate(
    ['name' => 'auditor_bitacora_demo'],
    ['description' => 'Rol de prueba para la captura visual de bitácora.', 'state' => true],
);
forzarInstante('sec_role', $rol->id, 'creado', '2026-09-04 15:00:00', 'America/La_Paz');

$descripcionActualizada = 'Rol de prueba para la captura visual de bitácora (editado).';

if ($rol->description !== $descripcionActualizada) {
    $rol->description = $descripcionActualizada;
    $rol->save();
}

forzarInstante('sec_role', $rol->id, 'actualizado', '2026-09-04 16:30:00', 'America/La_Paz');

Auth::guard('interno')->logout();

echo 'bitacora-demo: OK ('.Bitacora::query()->count()." filas totales)\n";
