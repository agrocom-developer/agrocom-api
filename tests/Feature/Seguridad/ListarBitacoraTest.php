<?php

use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Seguridad\Aplicacion\ListarBitacora;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/*
 * Tarea 63 — ListarBitacora: listado paginado con filtros y fechas ya
 * convertidas a la zona de quien mira. `created_at` se fuerza con un UPDATE
 * directo (bypassa Eloquent, no dispara el observer de nuevo) porque
 * `Bitacora::$fillable` no incluye esa columna a propósito — la migración
 * usa `useCurrent()`, nadie la fija a mano en producción.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->caso = new ListarBitacora;
});

function crearFilaBitacora(array $atributos = [], ?string $creadaEn = null): Bitacora
{
    $fila = Bitacora::query()->create([
        'tabla' => 'sec_role',
        'registro_id' => 1,
        'accion' => AccionBitacora::Creado,
        'despues' => ['name' => 'auditor'],
        ...$atributos,
    ]);

    if ($creadaEn !== null) {
        DB::table('plt_bitacoras')->where('id', $fila->id)->update(['created_at' => $creadaEn]);
        $fila->refresh();
    }

    return $fila;
}

it('filtra por usuario', function () {
    $usuarioA = SecUser::factory()->create();
    $usuarioB = SecUser::factory()->create();

    crearFilaBitacora(['user_id' => $usuarioA->id]);
    crearFilaBitacora(['user_id' => $usuarioB->id]);

    $resultado = $this->caso->ejecutar(zonaQueVe: 'UTC', usuarioId: $usuarioA->id);

    expect($resultado->total())->toBe(1)
        ->and($resultado->items()[0]->actorNombre)->toBe($usuarioA->name);
});

it('filtra por entidad (tabla)', function () {
    crearFilaBitacora(['tabla' => 'sec_role']);
    crearFilaBitacora(['tabla' => 'com_clientes', 'registro_id' => 2]);

    $resultado = $this->caso->ejecutar(zonaQueVe: 'UTC', tabla: 'com_clientes');

    expect($resultado->total())->toBe(1)
        ->and($resultado->items()[0]->tabla)->toBe('com_clientes');
});

it('filtra por acción', function () {
    crearFilaBitacora(['accion' => AccionBitacora::Creado]);
    crearFilaBitacora(['accion' => AccionBitacora::Eliminado, 'antes' => ['deleted_at' => null], 'despues' => ['deleted_at' => '2026-09-01 00:00:00']]);

    $resultado = $this->caso->ejecutar(zonaQueVe: 'UTC', accion: AccionBitacora::Eliminado);

    expect($resultado->total())->toBe(1)
        ->and($resultado->items()[0]->accion)->toBe(AccionBitacora::Eliminado);
});

it('el filtro por registro_id + tabla devuelve solo el historial de ese registro', function () {
    crearFilaBitacora(['tabla' => 'sec_role', 'registro_id' => 1]);
    crearFilaBitacora(['tabla' => 'sec_role', 'registro_id' => 1, 'accion' => AccionBitacora::Actualizado, 'antes' => ['name' => 'a'], 'despues' => ['name' => 'b']]);
    crearFilaBitacora(['tabla' => 'sec_role', 'registro_id' => 2]);
    crearFilaBitacora(['tabla' => 'com_clientes', 'registro_id' => 1]);

    $resultado = $this->caso->ejecutar(zonaQueVe: 'UTC', tabla: 'sec_role', registroId: 1);

    expect($resultado->total())->toBe(2)
        ->and(collect($resultado->items())->every(fn ($fila) => $fila->tabla === 'sec_role' && $fila->registroId === 1))->toBeTrue();
});

it('el diff muestra únicamente los campos que cambiaron', function () {
    crearFilaBitacora([
        'accion' => AccionBitacora::Actualizado,
        'antes' => ['description' => 'Antes'],
        'despues' => ['description' => 'Después'],
    ]);

    $resultado = $this->caso->ejecutar(zonaQueVe: 'UTC');
    $fila = $resultado->items()[0];

    expect($fila->diff)->toBe([
        ['campo' => 'description', 'antes' => 'Antes', 'despues' => 'Después'],
    ]);
});

it('un secreto excluido por el observer (nunca guardado) no aparece en el diff', function () {
    // Simula lo que ya hace BitacoraObserver con Configuracion::columnasSensiblesBitacora():
    // `valor` nunca llega a antes/despues, así que el diff jamás puede mostrarlo.
    crearFilaBitacora([
        'tabla' => 'plt_configuraciones',
        'despues' => ['clave' => 'mapas.google_maps_key', 'grupo' => 'mapas'],
    ]);

    $resultado = $this->caso->ejecutar(zonaQueVe: 'UTC', tabla: 'plt_configuraciones');
    $campos = collect($resultado->items()[0]->diff)->pluck('campo');

    expect($campos)->not->toContain('valor')
        ->and($campos->all())->toBe(['clave', 'grupo']);
});

it('interpreta el rango de fechas en la zona de quien mira, no en UTC', function () {
    // 04/09/2026 23:30 en America/La_Paz (UTC-4) es 05/09/2026 03:30 UTC.
    // Un filtro "hasta 04/09/2026" en La_Paz tiene que seguir incluyendo esta
    // fila aunque su created_at UTC ya sea 05/09.
    crearFilaBitacora(creadaEn: '2026-09-05 03:30:00');

    $incluida = $this->caso->ejecutar(zonaQueVe: 'America/La_Paz', hasta: '2026-09-04');
    $excluidaEnUtc = $this->caso->ejecutar(zonaQueVe: 'UTC', hasta: '2026-09-04');

    expect($incluida->total())->toBe(1)
        ->and($excluidaEnUtc->total())->toBe(0);
});

it('actor null (seeder/comando) deja actorNombre y actorUsername en null', function () {
    crearFilaBitacora();

    $fila = $this->caso->ejecutar(zonaQueVe: 'UTC')->items()[0];

    expect($fila->actorNombre)->toBeNull()
        ->and($fila->actorUsername)->toBeNull();
});

/*
 * Criterio de aceptación de la tarea: la MISMA fila (guardada con una zona
 * de actor cualquiera) se muestra con offset +2 respecto de UTC en julio y
 * +1 en enero para un usuario con preferencia Europe/Madrid — horario de
 * verano/invierno resuelto por la base IANA, nunca un offset fijo.
 */
it('resuelve el offset de Europe/Madrid con horario de verano en julio', function () {
    crearFilaBitacora(creadaEn: '2026-07-15 10:00:00');

    $fila = $this->caso->ejecutar(zonaQueVe: 'Europe/Madrid')->items()[0];

    expect($fila->offset)->toBe('UTC+2')
        ->and($fila->instante->format('H:i'))->toBe('12:00');
});

it('resuelve el offset de Europe/Madrid con horario de invierno en enero', function () {
    crearFilaBitacora(creadaEn: '2026-01-15 10:00:00');

    $fila = $this->caso->ejecutar(zonaQueVe: 'Europe/Madrid')->items()[0];

    expect($fila->offset)->toBe('UTC+1')
        ->and($fila->instante->format('H:i'))->toBe('11:00');
});

it('muestra en qué zona ocurrió la mutación solo si es distinta de la de quien mira', function () {
    crearFilaBitacora(['zona_horaria' => 'America/Asuncion']);

    $paraOtro = $this->caso->ejecutar(zonaQueVe: 'Europe/Madrid')->items()[0];
    $paraElMismo = $this->caso->ejecutar(zonaQueVe: 'America/Asuncion')->items()[0];

    expect($paraOtro->zonaRegistrada)->toBe('America/Asuncion')
        ->and($paraElMismo->zonaRegistrada)->toBeNull();
});

it('sin zona_horaria registrada, zonaRegistrada siempre es null', function () {
    crearFilaBitacora(['zona_horaria' => null]);

    $fila = $this->caso->ejecutar(zonaQueVe: 'Europe/Madrid')->items()[0];

    expect($fila->zonaRegistrada)->toBeNull();
});

it('resuelve el nombre legible de la entidad desde lang/es/seguridad.php', function () {
    crearFilaBitacora(['tabla' => 'sec_role']);

    $fila = $this->caso->ejecutar(zonaQueVe: 'UTC')->items()[0];

    expect($fila->tablaLegible)->toBe(__('seguridad.bitacora.entidades.sec_role'));
});

it('lista del más reciente al más viejo', function () {
    $vieja = crearFilaBitacora(creadaEn: '2026-01-01 00:00:00');
    $nueva = crearFilaBitacora(creadaEn: '2026-06-01 00:00:00');

    $ids = collect($this->caso->ejecutar(zonaQueVe: 'UTC')->items())->pluck('id');

    expect($ids->all())->toBe([$nueva->id, $vieja->id]);
});
