<?php

use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\CampanaDeAvisos;
use Illuminate\Support\Carbon;

/*
 * Tarea 141 (ADR 0025, punto 7) — qué avisos entran en la campana cuando se
 * mezclan las dos fuentes (el motor de `Notificaciones` y las alertas técnicas
 * de `ope_alertas`) y en qué orden. Pura: sin base de datos.
 *
 * Lo que importa: el badge de la campana cuenta lo no leído de la lista que
 * recibe, así que la lista tiene que incluir todo lo no leído (hasta el tope)
 * aunque haya leídos más nuevos.
 */

/** @return array{id: int|null, icon: string, title: string, momento: Carbon, unread: bool, href: string} */
function aviso(string $titulo, string $hace, bool $sinLeer, ?int $id = 1, string $icono = 'description'): array
{
    return [
        'id' => $id,
        'icon' => $icono,
        'title' => $titulo,
        'momento' => Carbon::parse('2026-09-23 12:00:00')->sub($hace),
        'unread' => $sinLeer,
        'href' => "http://localhost/panel/notificaciones/{$titulo}/abrir",
    ];
}

/** @param  list<array{title: string}>  $lista */
function titulos(array $lista): array
{
    return array_column($lista, 'title');
}

beforeEach(function () {
    Carbon::setTestNow('2026-09-23 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('va del más nuevo al más viejo, mezclando las dos fuentes', function () {
    $lista = CampanaDeAvisos::elegir([
        aviso('viejo', '3 hours', true),
        aviso('alerta', '1 hour', true, null, 'warning'),
        aviso('nuevo', '5 minutes', true),
    ], 10);

    expect(titulos($lista))->toBe(['nuevo', 'alerta', 'viejo']);
});

test('un no leído viejo no queda afuera por culpa de leídos más nuevos', function () {
    $candidatas = [
        aviso('sin-leer-viejo', '3 days', true),
        ...array_map(fn (int $n): array => aviso("leido-{$n}", "{$n} minutes", false), range(1, 5)),
    ];

    $lista = CampanaDeAvisos::elegir($candidatas, 3);

    expect($lista)->toHaveCount(3)
        ->and(titulos($lista))->toContain('sin-leer-viejo')
        ->and(titulos($lista))->toBe(['leido-1', 'leido-2', 'sin-leer-viejo']);
});

test('el badge es exacto: la lista incluye todos los no leídos hasta el tope', function (int $sinLeer, int $leidas, int $esperadosSinLeer) {
    $candidatas = [
        ...array_map(fn (int $n): array => aviso("nuevo-{$n}", "{$n} days", true), $sinLeer > 0 ? range(1, $sinLeer) : []),
        ...array_map(fn (int $n): array => aviso("leido-{$n}", "{$n} minutes", false), $leidas > 0 ? range(1, $leidas) : []),
    ];

    $lista = CampanaDeAvisos::elegir($candidatas, 10);

    expect(count(array_filter($lista, fn (array $a): bool => $a['unread'])))->toBe($esperadosSinLeer)
        ->and(count($lista))->toBeLessThanOrEqual(10);
})->with([
    'pocos sin leer y muchos leídos' => [3, 30, 3],
    'justo el tope' => [10, 5, 10],
    'más sin leer que el tope: se corta en el tope' => [14, 0, 10],
    'ninguno sin leer' => [0, 4, 0],
]);

test('con no leídos de sobra, los leídos no ocupan lugar; con lugar de sobra, completan la lista', function () {
    $lista = CampanaDeAvisos::elegir([
        aviso('a', '1 day', true),
        aviso('b', '1 hour', false),
        aviso('c', '2 hours', false),
    ], 2);

    expect(titulos($lista))->toBe(['b', 'a']);
});

test('conserva el id, el ícono, el destino y el estado de cada aviso, y arma la hora legible', function () {
    $lista = CampanaDeAvisos::elegir([
        aviso('del-motor', '2 hours', true, 7, 'task_alt'),
        aviso('alerta', '5 minutes', true, null, 'warning'),
    ], 10);

    expect($lista[0])->toMatchArray(['id' => null, 'icon' => 'warning', 'title' => 'alerta', 'unread' => true])
        ->and($lista[1])->toMatchArray(['id' => 7, 'icon' => 'task_alt', 'title' => 'del-motor', 'unread' => true])
        ->and($lista[1]['href'])->toBe('http://localhost/panel/notificaciones/del-motor/abrir')
        ->and($lista[1]['time'])->toBeString()->not->toBeEmpty()
        ->and(array_keys($lista[0]))->toBe(['id', 'alerta_id', 'icon', 'title', 'time', 'unread', 'href']);
});

test('una alerta técnica conserva su alerta_id y un aviso del motor no lo tiene', function () {
    $alerta = aviso('alerta', '5 minutes', true, null, 'warning');
    $alerta['alerta_id'] = 3;

    $lista = CampanaDeAvisos::elegir([aviso('del-motor', '2 hours', true, 7), $alerta], 10);

    expect($lista[0])->toMatchArray(['id' => null, 'alerta_id' => 3])
        ->and($lista[1])->toMatchArray(['id' => 7, 'alerta_id' => null]);
});

test('sin candidatas, la lista está vacía', function () {
    expect(CampanaDeAvisos::elegir([], 10))->toBe([]);
});
