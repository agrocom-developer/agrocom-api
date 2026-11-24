<?php

use Tests\Soporte\ContratoOpenApi;

/*
 * Pruebas de contrato para las apps (cliente, piloto, auxiliar): cada fixture
 * de `docs/api/ejemplos/` es un intercambio REAL (request y response) capturado
 * contra la API con `bin/generar-ejemplos`, y tiene que seguir cumpliendo el
 * contrato versionado (`docs/api/openapi.yaml`, ADR 0014). Si el código cambia
 * una respuesta sin regenerar el contrato y los ejemplos, esta prueba falla.
 *
 * Las apps pueden usar los mismos fixtures para sus propias pruebas de
 * contrato: son lo que el servidor devuelve de verdad, no una maqueta.
 */

/** @return array<string, array<string, mixed>> */
function ejemplosDeApi(): array
{
    $ejemplos = [];

    foreach (glob(dirname(__DIR__, 3).'/docs/api/ejemplos/*.json') ?: [] as $archivo) {
        $ejemplos[basename($archivo, '.json')] = json_decode((string) file_get_contents($archivo), true, 512, JSON_THROW_ON_ERROR);
    }

    return $ejemplos;
}

function contratoDeApi(): ContratoOpenApi
{
    static $contrato = null;

    return $contrato ??= new ContratoOpenApi;
}

/** Convierte `/api/solicitudes/{id}/publicar` en una expresión que acepta `/api/solicitudes/12/publicar`. */
function expresionDeRuta(string $plantilla): string
{
    return '#^'.preg_replace('#\\\\\{[^/]+\\\\\}#', '[^/?]+', preg_quote($plantilla, '#')).'$#';
}

dataset('ejemplos de la API', function () {
    $ejemplos = ejemplosDeApi();

    return array_map(fn (array $ejemplo) => [$ejemplo], $ejemplos);
});

test('hay ejemplos reales publicados', function () {
    expect(count(ejemplosDeApi()))->toBeGreaterThanOrEqual(40);
});

test('cada ejemplo trae la forma completa', function (array $ejemplo) {
    expect($ejemplo)->toHaveKeys(['operationId', 'descripcion', 'peticion', 'respuesta'])
        ->and($ejemplo['peticion'])->toHaveKeys(['metodo', 'ruta', 'autenticada'])
        ->and($ejemplo['respuesta'])->toHaveKeys(['estado', 'cuerpo'])
        ->and($ejemplo['descripcion'])->toBeString()->not->toBeEmpty();
})->with('ejemplos de la API');

test('cada ejemplo corresponde a una operación del contrato, con su método y su ruta', function (array $ejemplo) {
    $operaciones = contratoDeApi()->operaciones();

    expect($operaciones)->toHaveKey($ejemplo['operationId']);

    $operacion = $operaciones[$ejemplo['operationId']];
    $ruta = explode('?', $ejemplo['peticion']['ruta'])[0];

    expect($ejemplo['peticion']['metodo'])->toBe($operacion['metodo'])
        ->and($ruta)->toMatch(expresionDeRuta($operacion['ruta']));
})->with('ejemplos de la API');

test('el estado de cada ejemplo está documentado en el contrato', function (array $ejemplo) {
    $operacion = contratoDeApi()->operaciones()[$ejemplo['operationId']]['operacion'];

    expect(contratoDeApi()->esquemaDeRespuesta($operacion, $ejemplo['respuesta']['estado']))
        ->not->toBeNull('El estado '.$ejemplo['respuesta']['estado'].' no figura en las respuestas de '.$ejemplo['operationId']);
})->with('ejemplos de la API');

test('el cuerpo de cada respuesta cumple su esquema del contrato', function (array $ejemplo) {
    $contrato = contratoDeApi();
    $operacion = $contrato->operaciones()[$ejemplo['operationId']]['operacion'];
    $esquema = $contrato->esquemaDeRespuesta($operacion, $ejemplo['respuesta']['estado']) ?? [];
    $cuerpo = $ejemplo['respuesta']['cuerpo'];

    if ($esquema === [] || $cuerpo === null) {
        expect(true)->toBeTrue();

        return;
    }

    expect($contrato->validar($cuerpo, $esquema))->toBe([]);
})->with('ejemplos de la API');

test('el cuerpo de cada petición exitosa cumple el esquema de entrada del contrato', function (array $ejemplo) {
    $contrato = contratoDeApi();
    $operacion = $contrato->operaciones()[$ejemplo['operationId']]['operacion'];
    $esquema = $contrato->esquemaDePeticion($operacion);
    $exitosa = $ejemplo['respuesta']['estado'] >= 200 && $ejemplo['respuesta']['estado'] < 300;

    if (! $exitosa || $esquema === null || ! isset($ejemplo['peticion']['cuerpo']) || isset($ejemplo['peticion']['multipart'])) {
        expect(true)->toBeTrue();

        return;
    }

    expect($contrato->validar($ejemplo['peticion']['cuerpo'], $esquema))->toBe([]);
})->with('ejemplos de la API');

test('ningún ejemplo guarda una credencial viva', function (array $ejemplo) {
    $texto = json_encode($ejemplo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Un token de Sanctum completo es `{id}|{40 caracteres}`; el marcador no lo es.
    expect($texto)->not->toMatch('/\d+\|[A-Za-z0-9]{30,}/');

    if (isset($ejemplo['respuesta']['cuerpo']['token'])) {
        expect($ejemplo['respuesta']['cuerpo']['token'])->toBe('12|<token-del-dispositivo>');
    }
})->with('ejemplos de la API');

test('hectáreas, litros y montos viajan como decimal exacto en string, nunca como número', function (array $ejemplo) {
    $recorrer = function (mixed $valor, string $ruta = '$') use (&$recorrer): void {
        if (! is_array($valor)) {
            return;
        }

        foreach ($valor as $clave => $interno) {
            $esMedida = is_string($clave) && preg_match('/^(hectareas|litros|monto|tarifa|pago|comision|reembolso)/', $clave) === 1;

            if ($esMedida && ! in_array($clave, ['hectareas_conteo', 'litros_conteo'], true)) {
                expect($interno === null || is_string($interno) || is_array($interno))
                    ->toBeTrue("{$ruta}.{$clave} debe ser string decimal exacto, no ".get_debug_type($interno));
            }

            $recorrer($interno, "{$ruta}.{$clave}");
        }
    };

    $recorrer($ejemplo['respuesta']['cuerpo']);

    expect($ejemplo['respuesta']['estado'])->toBeInt();
})->with('ejemplos de la API');

test('los ejemplos cubren las operaciones que las apps necesitan', function () {
    $cubiertas = array_unique(array_column(ejemplosDeApi(), 'operationId'));
    $necesarias = [
        // Acceso y sesión de las tres apps.
        'emitirTokenDispositivo', 'verSesionCampo', 'cerrarSesionCampo', 'obtenerVersionVigente',
        // Sincronización del lote (piloto y auxiliar).
        'obtenerCatalogoSincronizacion', 'sincronizarLote', 'subirEvidencia',
        // Negociación: mapa de abiertas, ofertas y contraofertas (requieren red).
        'listarSolicitudesAbiertas', 'resumenZonasAbiertas', 'verSolicitudAbierta', 'ofertarSolicitud', 'compararOfertas',
        'listarOfertas', 'verOferta', 'contraofertarOferta', 'aceptarOferta', 'verReputacion', 'cambiarPrecioSolicitud',
        // Ejecución: avance con fotos, ubicación del piloto y chat.
        'iniciarServicio', 'registrarAvance', 'cerrarServicio', 'verAvance', 'subirEvidenciaDeSolicitud',
        'enviarUbicacion', 'verUbicacionDelPiloto', 'enviarMensaje', 'listarMensajes',
        'verPreferenciasDeAviso', 'guardarPreferenciasDeAviso',
        // Cliente: terrenos y solicitudes.
        'listarTerrenos', 'crearTerreno', 'actualizarTerreno', 'listarSolicitudes', 'crearSolicitud', 'estimarSolicitud',
        'publicarSolicitud', 'verSolicitud', 'cancelarSolicitud',
        // Posventa y avisos.
        'verCalificacionesDelServicio', 'listarDisputas', 'listarNotificaciones',
    ];

    expect(array_values(array_diff($necesarias, $cubiertas)))->toBe([]);
});

test('el replay del lote responde duplicado para cada registro y no cambia el resultado', function () {
    $ejemplos = ejemplosDeApi();
    $primero = $ejemplos['sync-lote-primero']['respuesta']['cuerpo']['resultados'];
    $replay = $ejemplos['sync-lote-replay']['respuesta']['cuerpo']['resultados'];

    expect(array_unique(array_column($primero, 'estado')))->toBe(['aplicado'])
        ->and(array_unique(array_column($replay, 'estado')))->toBe(['duplicado'])
        ->and(array_column($replay, 'uuid_cliente'))->toBe(array_column($primero, 'uuid_cliente'))
        // El servidor responde un resultado por registro y en el mismo orden del arreglo recibido.
        ->and(array_column($primero, 'uuid_cliente'))->toBe(array_column($ejemplos['sync-lote-primero']['peticion']['cuerpo']['registros'], 'uuid_cliente'));
});

test('un registro inválido se rechaza con motivo sin frenar el resto del lote', function () {
    $resultados = ejemplosDeApi()['sync-lote-rechazo-parcial']['respuesta']['cuerpo']['resultados'];

    expect(array_column($resultados, 'estado'))->toBe(['rechazado', 'duplicado'])
        ->and($resultados[0]['motivo'])->toBeString()->not->toBeEmpty();
});

test('reintentar una solicitud con el mismo uuid_cliente no la duplica', function () {
    $ejemplos = ejemplosDeApi();

    expect($ejemplos['solicitudes-crear']['respuesta']['estado'])->toBe(201)
        ->and($ejemplos['solicitudes-crear-reintento']['respuesta']['estado'])->toBe(200)
        ->and($ejemplos['solicitudes-crear-reintento']['respuesta']['cuerpo']['solicitud']['id'])
        ->toBe($ejemplos['solicitudes-crear']['respuesta']['cuerpo']['solicitud']['id']);
});

test('un recurso ajeno responde 404 y un rol equivocado 403', function () {
    $ejemplos = ejemplosDeApi();

    expect($ejemplos['solicitudes-ajena']['respuesta']['estado'])->toBe(404)
        ->and($ejemplos['terrenos-rol-equivocado']['respuesta']['estado'])->toBe(403)
        ->and($ejemplos['seguridad-sin-token']['respuesta']['estado'])->toBe(401)
        ->and($ejemplos['seguridad-token-revocado']['respuesta']['estado'])->toBe(401);
});
