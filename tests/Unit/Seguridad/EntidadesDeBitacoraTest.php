<?php

use App\Dominios\Seguridad\Aplicacion\ListarBitacora;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/*
 * La columna «Elemento» de la bitácora nunca muestra el nombre técnico de la tabla
 * (`neg_evidencias`, `ntf_notificaciones`): cada tabla del marketplace tiene su etiqueta en
 * `lang/es/seguridad.php` y, si una tabla nueva no la tiene, se muestra una frase legible.
 */

uses(TestCase::class);

dataset('tablas del marketplace', [
    'neg_evidencias', 'neg_avances', 'neg_ofertas', 'neg_cancelaciones', 'neg_mensajes', 'neg_ubicaciones',
    'ntf_notificaciones', 'ntf_dispositivos', 'cal_calificaciones', 'flo_drones', 'flo_equipos',
    'prv_proveedores', 'prv_pilotos', 'prv_documentos', 'prv_historial_estados',
    'rcl_reclamos', 'rcl_mensajes', 'rcl_historial', 'sec_user', 'sec_role', 'sec_datos_empresa',
    'sol_clientes', 'sol_cliente_contactos', 'sol_solicitudes_servicio', 'sol_solicitud_historial', 'sol_terrenos',
]);

test('cada tabla del marketplace tiene etiqueta propia en español', function (string $tabla) {
    $clave = "seguridad.bitacora.entidades.{$tabla}";

    expect(Lang::has($clave))->toBeTrue("falta la etiqueta de {$tabla}")
        ->and(__($clave))->not->toContain('_')
        ->and(__($clave))->not->toBe($tabla);
})->with('tablas del marketplace');

test('una tabla sin etiqueta se muestra como frase legible, nunca con su nombre técnico', function () {
    $nombre = (new ReflectionMethod(ListarBitacora::class, 'nombreLegibleTabla'))
        ->invoke(app(ListarBitacora::class), 'neg_tabla_nueva_sin_etiqueta');

    expect($nombre)->toBe('Tabla nueva sin etiqueta');
});
