<?php

use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/*
 * El estándar de acciones de fila del panel: el orden es Ver, Editar, Aprobar,
 * transiciones de la máquina de estados y Eliminar, lo fija `organisms/row-actions`
 * (no quien lo usa) y Eliminar es siempre peligro. Renderiza el componente real; sin
 * base de datos.
 */

uses(TestCase::class);

function filaConAcciones(string $slots): string
{
    return Blade::render('<x-organisms.row-actions>'.$slots.'</x-organisms.row-actions>');
}

/** @return list<string> los `kind` de las acciones, en el orden en que salen en el HTML */
function ordenDeAcciones(string $html): array
{
    // El contenido se pinta dos veces (visibles + menú «⋮»): se cuenta una sola mitad.
    $corte = strpos($html, 'ag-row-actions__more');
    $mitad = $corte === false ? $html : substr($html, 0, $corte);
    preg_match_all('/data-ag-row-action="(\w+)"/', $mitad, $coincidencias);

    return $coincidencias[1];
}

const SLOT_VER = '<x-slot:ver><x-molecules.row-action kind="ver" href="/ver" icon="visibility" label="Ver" /></x-slot:ver>';
const SLOT_EDITAR = '<x-slot:editar><x-molecules.row-action kind="editar" href="/editar" icon="edit" label="Editar" /></x-slot:editar>';
const SLOT_PERMISOS = '<x-slot:permisos><x-molecules.row-action kind="permisos" href="/permisos" icon="key" label="Permisos" /></x-slot:permisos>';
const SLOT_APROBAR = '<x-slot:aprobar><x-molecules.row-action kind="aprobar" modal="m-aprobar" icon="check_circle" label="Aprobar" /></x-slot:aprobar>';
const SLOT_TRANSICIONES = '<x-slot:transiciones><x-molecules.row-action kind="transicion" tone="warning" modal="m-suspender" icon="pause_circle" label="Suspender" /></x-slot:transiciones>';
const SLOT_ELIMINAR = '<x-slot:eliminar><x-molecules.row-action kind="eliminar" modal="m-eliminar" icon="delete" label="Eliminar" /></x-slot:eliminar>';

test('el orden es Ver, Editar, Aprobar, transiciones y Eliminar, sin importar cómo se declaren', function () {
    $desordenadas = SLOT_ELIMINAR.SLOT_TRANSICIONES.SLOT_APROBAR.SLOT_EDITAR.SLOT_VER;

    expect(ordenDeAcciones(filaConAcciones($desordenadas)))
        ->toBe(['ver', 'editar', 'aprobar', 'transicion', 'eliminar']);
});

test('Permisos va después de Editar y antes de Eliminar, con su tono propio y sin el rojo vino', function () {
    $html = filaConAcciones(SLOT_ELIMINAR.SLOT_PERMISOS.SLOT_EDITAR.SLOT_VER);

    $menu = substr($html, (int) strpos($html, 'ag-row-actions__menu'));

    expect(ordenDeAcciones($html))->toBe(['ver', 'editar', 'permisos', 'eliminar'])
        ->and(ordenDeAcciones($menu))->toBe(['ver', 'editar', 'permisos', 'eliminar'])
        ->and($html)->toContain('ag-button--distintivo-1-outline')
        ->and($html)->toContain('title="Permisos"')
        ->and(substr_count($html, 'ag-button--danger-outline'))->toBe(2);
});

test('Eliminar va siempre al final, también con varias transiciones', function () {
    $transiciones = '<x-slot:transiciones>'
        .'<x-molecules.row-action kind="transicion" tone="danger" modal="m1" icon="cancel" label="Rechazar" />'
        .'<x-molecules.row-action kind="transicion" tone="success" modal="m2" icon="lock_open" label="Rehabilitar" />'
        .'</x-slot:transiciones>';

    $orden = ordenDeAcciones(filaConAcciones(SLOT_ELIMINAR.$transiciones.SLOT_VER));

    expect($orden)->toBe(['ver', 'transicion', 'transicion', 'eliminar'])
        ->and(end($orden))->toBe('eliminar');
});

test('cada tipo de acción lleva su color fijo y Eliminar el de peligro', function () {
    $html = filaConAcciones(SLOT_VER.SLOT_EDITAR.SLOT_ELIMINAR);

    expect($html)
        ->toContain('ag-button--info-outline')
        ->toContain('ag-button--warning-outline')
        ->toContain('ag-button--danger-outline');

    expect(filaConAcciones(SLOT_APROBAR))->toContain('ag-button--success-outline');
    expect(filaConAcciones(SLOT_TRANSICIONES))->toContain('ag-button--warning-outline');
});

test('las acciones llevan ícono y texto visibles, nunca solo ícono', function () {
    $html = filaConAcciones(SLOT_VER.SLOT_EDITAR.SLOT_ELIMINAR);

    expect($html)
        ->toContain('ag-row-actions__visible')
        ->toContain('ag-row-actions__menu')
        ->toContain('title="Eliminar"')
        ->toContain('aria-label="Eliminar"')
        ->not->toContain('ag-row-actions--iconos');
});

test('el contenido va en las dos mitades, visibles y menú, en el mismo orden', function () {
    $html = filaConAcciones(SLOT_ELIMINAR.SLOT_TRANSICIONES.SLOT_APROBAR.SLOT_EDITAR.SLOT_VER);

    $menu = substr($html, strpos($html, 'ag-row-actions__menu'));
    preg_match_all('/data-ag-row-action="(\w+)"/', $menu, $enMenu);

    expect(ordenDeAcciones($html))->toBe(['ver', 'editar', 'aprobar', 'transicion', 'eliminar'])
        ->and($enMenu[1])->toBe(['ver', 'editar', 'aprobar', 'transicion', 'eliminar']);
});

test('sin acciones no se pinta nada', function () {
    expect(trim(filaConAcciones('')))->toBe('');
});

test('una acción puede abrir un modal o enlazar, y Eliminar nunca queda fuera de peligro', function () {
    $modal = filaConAcciones(SLOT_ELIMINAR);

    expect($modal)->toContain('data-bs-target="#m-eliminar"')->toContain('ag-button--danger-outline');
    expect(filaConAcciones(SLOT_VER))->toContain('href="/ver"');
});

test('la última acción de la fila es Eliminar y lleva el rojo vino; ninguna otra lo lleva', function () {
    $todas = SLOT_ELIMINAR.SLOT_TRANSICIONES.SLOT_APROBAR.SLOT_EDITAR.SLOT_VER;
    $html = filaConAcciones($todas);
    $orden = ordenDeAcciones($html);

    expect(end($orden))->toBe('eliminar')
        ->and(substr_count($html, 'ag-button--danger-outline'))->toBe(2);

    // Aun si el llamador pasa una transición con tono de peligro (rechazar), no hereda el rojo vino.
    $rechazar = '<x-slot:transiciones><x-molecules.row-action kind="transicion" tone="danger" modal="m" icon="cancel" label="Rechazar" /></x-slot:transiciones>';
    $conRechazo = filaConAcciones($rechazar.SLOT_ELIMINAR);

    expect(substr_count($conRechazo, 'ag-button--danger-outline'))->toBe(2)
        ->and($conRechazo)->toContain('ag-button--alert-outline');
});

test('Suspender, bloquear y ocultar van en gris (neutral), no en rojo', function () {
    $suspender = '<x-slot:transiciones><x-molecules.row-action kind="transicion" tone="neutral" modal="m" icon="pause_circle" label="Suspender" /></x-slot:transiciones>';
    $html = filaConAcciones($suspender);

    expect($html)->toContain('ag-button--neutral-outline')
        ->not->toContain('danger');

    // El estado de llegada de «suspender» es gris: así lo toman la fila, el paso de la máquina y el badge.
    expect(EstadoAprobacion::Suspendido->tono())->toBe('neutral')
        ->and(EstadoAprobacion::Rechazado->tono())->toBe('danger');
});

test('la máquina de estados de una ficha no ofrece la baja como paso y el encabezado trae Eliminar', function () {
    $raiz = dirname(__DIR__, 2).'/app/Dominios';
    $maquina = (string) file_get_contents($raiz.'/Proveedores/Infraestructura/Http/Views/pages/_maquina-estado-aprobacion.blade.php');

    expect($maquina)->toContain('! in_array($hacia, [EstadoAprobacion::Pendiente, EstadoAprobacion::Baja], true)');

    foreach (['Proveedores/Infraestructura/Http/Views/pages/proveedores/show', 'Proveedores/Infraestructura/Http/Views/pages/pilotos/show', 'Flota/Infraestructura/Http/Views/pages/drones/show'] as $ficha) {
        $contenido = (string) file_get_contents($raiz.'/'.$ficha.'.blade.php');
        $volver = strpos($contenido, 'boton-volver');
        $eliminar = strpos($contenido, '_boton-eliminar-aprobacion');

        expect($volver)->not->toBeFalse()
            ->and($eliminar)->not->toBeFalse()
            ->and($eliminar)->toBeGreaterThan($volver)
            ->and($eliminar)->toBeLessThan((int) strpos($contenido, '</x-slot:actions>'));
    }

    $boton = (string) file_get_contents($raiz.'/Proveedores/Infraestructura/Http/Views/pages/_boton-eliminar-aprobacion.blade.php');
    expect($boton)->toContain('variant="danger"')->toContain('AccionAprobacion::DarDeBaja');
});

test('el CSS conserva el cupo de dos acciones sueltas y el «⋮» en móvil', function () {
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/components/row-actions.css');

    expect($css)
        ->toContain('.ag-row-actions__visible')
        ->toContain('min-width: 768px')
        ->toContain('nth-child(n + 3)')
        ->not->toContain('ag-row-actions--iconos')
        ->not->toContain('ag-row-action-size');
});
