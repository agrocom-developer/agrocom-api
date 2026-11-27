<?php

use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\CascaraPanel;

/*
 * La campana del header muestra solo los 3 últimos avisos y enlaza a «Mis
 * notificaciones», donde están todos los de la cuenta. «Eliminar todas» quita los
 * avisos de la cuenta (baja lógica) y no hay historial aparte.
 */

$raiz = dirname(__DIR__, 2);

it('la campana muestra 3 avisos', function () {
    $cascara = new ReflectionClass(CascaraPanel::class);

    expect($cascara->getReflectionConstant('MAXIMO_EN_CAMPANA')->getValue())->toBe(3);
});

it('la campana enlaza a Mis notificaciones y la ruta existe', function () use ($raiz) {
    $vista = (string) file_get_contents($raiz.'/resources/views/components/molecules/notifications-menu.blade.php');
    $rutas = (string) file_get_contents($raiz.'/routes/web.php');

    expect($vista)->toContain("route('panel.notificaciones.index')");
    expect($rutas)->toContain("->name('panel.notificaciones.index')");
});

it('«Mis notificaciones» no pide permiso propio: el sujeto es la cuenta que mira', function () use ($raiz) {
    $codigo = (string) file_get_contents($raiz.'/app/Dominios/Notificaciones/Infraestructura/Http/Controllers/Web/NotificacionesController.php');

    expect($codigo)
        ->toContain('->deUsuario($this->cuenta($request))')
        ->not->toContain('tienePermiso');
});
