<?php

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;

/*
 * Tests de arquitectura (ADR 0003, regla 5): las fronteras modulares las
 * defiende la suite, no la disciplina. Los módulos se descubren recorriendo
 * app/Dominios/ — un módulo nuevo queda protegido sin editar este archivo.
 * Compartido es la plataforma (define ModeloDominio y lo transversal), por
 * eso queda fuera de las reglas de módulo.
 */

$rutaDominios = dirname(__DIR__, 2).'/app/Dominios';

/** @var list<string> $modulos Módulos de dominio descubiertos, sin Compartido. */
$modulos = collect(glob($rutaDominios.'/*', GLOB_ONLYDIR) ?: [])
    ->map(fn (string $ruta): string => basename($ruta))
    ->reject(fn (string $modulo): bool => $modulo === 'Compartido')
    ->values()
    ->all();

// Red de seguridad del descubrimiento: si esto falla, las reglas de abajo
// no protegen nada (p. ej. porque se movió app/Dominios).
test('el descubrimiento encuentra módulos de dominio', function () use ($modulos) {
    expect($modulos)->not->toBeEmpty();
});

foreach ($modulos as $modulo) {
    // Reglas por módulo solo si la carpeta correspondiente tiene clases:
    // un namespace vacío haría fallar la expectativa por no ser testeable.

    if (glob("{$rutaDominios}/{$modulo}/Infraestructura/Eloquent/*.php")) {
        arch("los modelos de {$modulo} extienden el modelo base de plataforma (ADR 0007)")
            ->expect("App\\Dominios\\{$modulo}\\Infraestructura\\Eloquent")
            ->toExtend(ModeloDominio::class);
    }

    if (glob("{$rutaDominios}/{$modulo}/Dominio/*.php")) {
        arch("la capa Dominio de {$modulo} no depende de Eloquent")
            ->expect("App\\Dominios\\{$modulo}\\Dominio")
            ->not->toUse('Illuminate\Database');
    }

    $modelosAjenos = array_map(
        fn (string $otro): string => "App\\Dominios\\{$otro}\\Infraestructura\\Eloquent",
        array_values(array_filter($modulos, fn (string $otro): bool => $otro !== $modulo)),
    );

    if ($modelosAjenos !== []) {
        arch("{$modulo} no importa modelos Eloquent de otros módulos (ADR 0003, regla 2)")
            ->expect("App\\Dominios\\{$modulo}")
            ->not->toUse($modelosAjenos);
    }

    $aplicacionAjena = array_map(
        fn (string $otro): string => "App\\Dominios\\{$otro}\\Aplicacion",
        array_values(array_filter($modulos, fn (string $otro): bool => $otro !== $modulo)),
    );

    if ($aplicacionAjena !== []) {
        // Excepción puntual (tarea 68, revisión del PR #106): Operaciones\Contratos\ResumenLotePanel
        // referencia Comercial\Aplicacion\ObtenerAvanceComercial SOLO en un
        // `{@see}` de docblock (comparación de fórmula, no dependencia de
        // código — la clase no se instancia ni se tipa en ningún lado del
        // archivo). Preexistente y fuera del alcance de la tarea 68
        // ("Puede tocar" no incluye Operaciones/Contratos); documentado en
        // runs/68.md.
        $ignorar = $modulo === 'Operaciones'
            ? ['App\Dominios\Operaciones\Contratos\ResumenLotePanel']
            : [];

        arch("{$modulo} no importa Aplicacion de otros módulos, solo su Contratos/ o eventos de dominio (ADR 0003, regla 2)")
            ->expect("App\\Dominios\\{$modulo}")
            ->not->toUse($aplicacionAjena)
            ->ignoring($ignorar);
    }
}

// Dirección de dependencia entre módulos concretos (revisión 3/9/2026, ADR
// 0003): Comercial no conoce la lógica interna de Operaciones (Aplicacion,
// Dominio, Infraestructura — sus modelos Eloquent ya están cubiertos arriba
// por la regla genérica), pero sí puede leer su Contratos/, el mismo cruce
// síncrono sancionado por ADR 0003 regla 2 que ya usan Sincronizacion y
// Finanzas para leer Operaciones (HU-31, tarea 45: `Comercial\Aplicacion\EmitirFactura`
// consume `Operaciones\Contratos\LecturaActaConformada`).
arch('Comercial no usa la lógica interna de Operaciones, solo su Contratos/ (ADR 0003, regla 2)')
    ->expect('App\Dominios\Comercial')
    ->not->toUse([
        'App\Dominios\Operaciones\Aplicacion',
        'App\Dominios\Operaciones\Dominio',
        'App\Dominios\Operaciones\Infraestructura',
    ]);
