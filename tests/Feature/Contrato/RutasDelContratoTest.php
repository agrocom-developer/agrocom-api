<?php

use Illuminate\Support\Facades\Route;
use Tests\Soporte\ContratoOpenApi;

/*
 * El contrato versionado (`docs/api/openapi.yaml`, ADR 0014) y las rutas que
 * el código registra tienen que ser la misma superficie: ningún endpoint de la
 * API sin documentar y ningún endpoint documentado que ya no exista. Corre en
 * todo PR que toque rutas, controladores o el contrato.
 */

/** @return array<string, array{protegida: bool}> clave `MÉTODO /ruta/{}` */
function rutasDeLaApi(): array
{
    $rutas = [];

    foreach (Route::getRoutes() as $ruta) {
        $uri = $ruta->uri();

        // La interfaz de documentación (l5-swagger) no es API de las apps.
        if (! str_starts_with($uri, 'api/') || str_starts_with($uri, 'api/documentation') || $uri === 'api/oauth2-callback') {
            continue;
        }

        $plantilla = '/'.preg_replace('/\{[^}]+\}/', '{}', $uri);
        $protegida = collect($ruta->gatherMiddleware())->contains(fn ($middleware) => is_string($middleware) && preg_match('/^auth(:|$)|Authenticate/', $middleware) === 1);

        foreach ($ruta->methods() as $metodo) {
            if (in_array($metodo, ['HEAD', 'OPTIONS'], true)) {
                continue;
            }
            $rutas["{$metodo} {$plantilla}"] = ['protegida' => $protegida];
        }
    }

    return $rutas;
}

/** @return array<string, array{protegida: bool, operationId: string}> */
function operacionesDocumentadas(): array
{
    $documentadas = [];

    foreach ((new ContratoOpenApi)->operaciones() as $id => $operacion) {
        $plantilla = preg_replace('/\{[^}]+\}/', '{}', $operacion['ruta']);
        $documentadas["{$operacion['metodo']} {$plantilla}"] = [
            'protegida' => ! empty($operacion['operacion']['security']),
            'operationId' => $id,
        ];
    }

    return $documentadas;
}

test('cada ruta de la API está documentada en el contrato', function () {
    $sinDocumentar = array_values(array_diff(array_keys(rutasDeLaApi()), array_keys(operacionesDocumentadas())));

    expect($sinDocumentar)->toBe([]);
});

test('cada operación del contrato existe como ruta de la API', function () {
    $sinRuta = array_values(array_diff(array_keys(operacionesDocumentadas()), array_keys(rutasDeLaApi())));

    expect($sinRuta)->toBe([]);
});

test('el contrato declara seguridad exactamente en las rutas que exigen token', function () {
    $rutas = rutasDeLaApi();
    $documentadas = operacionesDocumentadas();
    $discrepancias = [];

    foreach ($rutas as $clave => $ruta) {
        if (isset($documentadas[$clave]) && $documentadas[$clave]['protegida'] !== $ruta['protegida']) {
            $discrepancias[] = $clave.($ruta['protegida'] ? ' exige token pero el contrato no declara seguridad' : ' no exige token pero el contrato declara seguridad');
        }
    }

    expect($discrepancias)->toBe([]);
});
