<?php

use Symfony\Component\Yaml\Yaml;

/*
 * Humo del contrato de API: el spec versionado (docs/api/openapi.yaml, ADR
 * 0014) es lo que consume agrocom-field. Esta prueba corre en todo PR, sin
 * importar qué archivos tocó, y falla si el contrato queda ilegible o con
 * operaciones ambiguas.
 */

$rutaContrato = dirname(__DIR__, 2).'/docs/api/openapi.yaml';

test('el contrato OpenAPI existe y se puede leer', function () use ($rutaContrato) {
    expect(is_file($rutaContrato))->toBeTrue();

    $spec = Yaml::parseFile($rutaContrato);

    expect($spec)->toBeArray()
        ->and($spec)->toHaveKeys(['openapi', 'info', 'paths'])
        ->and($spec['paths'])->not->toBeEmpty();
});

test('cada operación del contrato tiene un operationId único', function () use ($rutaContrato) {
    $spec = Yaml::parseFile($rutaContrato);
    $metodos = ['get', 'put', 'post', 'delete', 'patch', 'options', 'head'];

    $ids = [];
    foreach ($spec['paths'] as $ruta => $operaciones) {
        foreach ($operaciones as $metodo => $operacion) {
            if (! in_array($metodo, $metodos, true)) {
                continue;
            }
            expect($operacion)->toHaveKey('operationId');
            $ids[] = $operacion['operationId'];
        }
    }

    expect($ids)->not->toBeEmpty()
        ->and(array_unique($ids))->toHaveCount(count($ids));
});
