<?php

use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use L5Swagger\GeneratorFactory;

/*
 * Documentación OpenAPI code-first (ADR 0014): el spec se genera desde los
 * atributos OA\... de los módulos y es el contrato de agrocom-field — un
 * atributo roto tiene que romper la suite, no descubrirse en la app.
 */

it('genera el spec OpenAPI desde los atributos de los módulos', function () {
    app(GeneratorFactory::class)->make('default')->generateDocs();

    $rutaJson = storage_path('api-docs/api-docs.json');

    expect(file_exists($rutaJson))->toBeTrue();

    /** @var array<string, mixed> $spec */
    $spec = json_decode((string) file_get_contents($rutaJson), true, 512, JSON_THROW_ON_ERROR);

    expect($spec['openapi'])->toStartWith('3.')
        ->and($spec['info']['title'])->toBe('Agrocom API')
        ->and($spec['info']['version'])->toBe('0.1.0')
        ->and($spec['paths'])->toHaveKey('/api/ordenes')
        ->and(collect($spec['tags'])->pluck('name')->all())->toContain('Operaciones')
        ->and($spec['components']['schemas'])->toHaveKeys([
            'OrdenAplicacion',
            'PaginacionLinks',
            'PaginacionMeta',
            'ErrorValidacion',
        ]);

    // El filtro estado expande el enum de dominio real, no una lista tipeada a mano.
    $parametroEstado = collect($spec['paths']['/api/ordenes']['get']['parameters'])
        ->firstWhere('name', 'estado');

    expect($parametroEstado['schema']['enum'])
        ->toBe(array_column(EstadoOrdenAplicacion::cases(), 'value'));

    // Los DECIMAL viajan como string también en el contrato (invariante 6).
    expect($spec['components']['schemas']['OrdenAplicacion']['properties']['litros_ha']['type'])
        ->toBe('string');

    // La copia YAML existe: es la que alimenta docs/api/openapi.yaml (composer openapi).
    expect(file_exists(storage_path('api-docs/api-docs.yaml')))->toBeTrue();
});

it('mantiene versionado el contrato en docs/api/openapi.yaml', function () {
    $rutaContrato = base_path('docs/api/openapi.yaml');

    expect(file_exists($rutaContrato))->toBeTrue()
        ->and((string) file_get_contents($rutaContrato))->toContain('/api/ordenes');
});

it('sirve la UI de documentación en entornos no productivos', function () {
    $this->get('/api/documentation')->assertOk();
});

it('la documentación no existe en producción', function () {
    $this->app['env'] = 'production';

    $this->get('/api/documentation')->assertNotFound();
});
