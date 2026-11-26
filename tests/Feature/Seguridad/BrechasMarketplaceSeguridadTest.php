<?php

/*
 * Las rutas que cierran las brechas de la app de campo (token de push, pago confirmado, fotos
 * de terreno y de perfil) exigen token de dispositivo: sin él ninguna
 * responde datos (ADR 0008). Las dos que sirven imágenes por URL firmada rechazan una URL sin
 * firma o con la firma alterada. El alcance por cuenta (un id ajeno es 404) vive en los casos
 * de uso y lo cubren sus pruebas de unidad.
 */

dataset('rutas con token', [
    ['POST', '/api/dispositivos'],
    ['DELETE', '/api/dispositivos/fcm:abc'],
    ['POST', '/api/solicitudes/1/pago-confirmado'],
    ['GET', '/api/solicitudes/1/pago-confirmado'],
    ['POST', '/api/terrenos/1/fotos'],
    ['DELETE', '/api/terrenos/1/fotos/1'],
    ['PUT', '/api/perfil/foto'],
    ['DELETE', '/api/perfil/foto'],
    ['GET', '/api/proveedores/mi'],
    ['GET', '/api/proveedores/drones'],
]);

it('rechaza sin token las rutas de las brechas del marketplace', function (string $metodo, string $ruta) {
    test()->json($metodo, $ruta, [])->assertStatus(401);
})->with('rutas con token');

dataset('rutas firmadas', [
    ['/api/terrenos/fotos/1/archivo'],
    ['/api/personas/1/foto'],
]);

it('rechaza una imagen sin firma o con la firma alterada', function (string $ruta) {
    test()->getJson($ruta)->assertStatus(403);
    test()->getJson("{$ruta}?expires=".(time() + 600).'&signature=falsa')->assertStatus(403);
})->with('rutas firmadas');
