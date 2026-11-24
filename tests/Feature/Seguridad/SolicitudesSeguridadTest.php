<?php

/*
 * La API del cliente (terrenos y solicitudes de servicio) exige token de
 * dispositivo: sin él ninguna ruta responde datos (ADR 0008). El alcance por
 * cliente (un id ajeno es 404, invariante 5 de CLAUDE.md) vive en los casos de
 * uso, que parten siempre de `cliente_id`.
 */

dataset('rutas del cliente', [
    ['GET', '/api/terrenos'],
    ['POST', '/api/terrenos'],
    ['GET', '/api/terrenos/1'],
    ['PUT', '/api/terrenos/1'],
    ['GET', '/api/solicitudes'],
    ['POST', '/api/solicitudes'],
    ['GET', '/api/solicitudes/1'],
    ['GET', '/api/solicitudes/1/estimacion'],
    ['POST', '/api/solicitudes/1/publicar'],
    ['POST', '/api/solicitudes/1/cancelar'],
]);

it('rechaza sin token las rutas del cliente', function (string $metodo, string $ruta) {
    test()->json($metodo, $ruta, [])->assertStatus(401);
})->with('rutas del cliente');
