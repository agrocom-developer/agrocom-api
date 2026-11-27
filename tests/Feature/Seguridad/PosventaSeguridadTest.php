<?php

/*
 * La API del posventa (conformidad, calificaciones y reclamos) exige token de
 * dispositivo: sin él ninguna ruta responde datos (ADR 0008). El alcance por
 * cuenta (una calificación, un servicio o un reclamo ajenos responden 404,
 * invariante 5 de CLAUDE.md) vive en los casos de uso, que parten siempre del
 * cliente o del proveedor de la cuenta, y se prueba en `tests/Unit/Calificaciones`
 * y `tests/Unit/Reclamos`.
 */

dataset('rutas del posventa', [
    ['GET', '/api/solicitudes/1/calificaciones'],
    ['POST', '/api/solicitudes/1/calificaciones'],
    ['GET', '/api/solicitudes/1/calificar-cliente'],
    ['POST', '/api/solicitudes/1/calificar-cliente'],
    ['GET', '/api/calificaciones/recibidas'],
    ['POST', '/api/calificaciones/1/reportar'],
    ['POST', '/api/solicitudes/1/reclamos'],
    ['GET', '/api/solicitudes/1/reclamo'],
    ['GET', '/api/reclamos'],
    ['GET', '/api/reclamos/1'],
    ['POST', '/api/reclamos/1/mensajes'],
    ['POST', '/api/reclamos/1/retirar'],
    ['POST', '/api/reclamos/1/apelar'],
]);

it('rechaza sin token las rutas del posventa', function (string $metodo, string $ruta) {
    test()->json($metodo, $ruta, [])->assertStatus(401);
})->with('rutas del posventa');

dataset('rutas del panel del posventa', [
    ['GET', '/panel/calificaciones'],
    ['POST', '/panel/calificaciones/1/ocultar'],
    ['POST', '/panel/calificaciones/1/restablecer'],
    ['GET', '/panel/reclamos'],
    ['GET', '/panel/reclamos/1'],
    ['POST', '/panel/reclamos/1/tomar'],
    ['POST', '/panel/reclamos/1/mensajes'],
    ['POST', '/panel/reclamos/1/resolver'],
    ['GET', '/panel/metricas'],
]);

it('no entrega el panel del posventa sin sesión del panel', function (string $metodo, string $ruta) {
    $respuesta = test()->call($metodo, $ruta);

    expect($respuesta->getStatusCode())->toBeIn([302, 401, 403, 419]);
})->with('rutas del panel del posventa');
