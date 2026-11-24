<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Frontera de lectura de `Operaciones` hacia `Sincronizacion` (ADR 0003, regla
 * 2): los límites de condiciones de vuelo vigentes, los mismos con los que el
 * servidor valida el registro `condiciones` del sync (espec §9.2). La app, que
 * es offline, los baja en el catálogo para mostrar lo mismo que se exige.
 */
interface LecturaLimitesDeVuelo
{
    /**
     * Límites efectivos como decimales exactos en string (invariante 6 de
     * CLAUDE.md). Viento, temperatura y humedad máxima siempre traen valor;
     * humedad mínima, altura, velocidad y ancho de pasada son `null` si el
     * sistema no fija un límite.
     *
     * @return array{humedad_min_pct: ?string, viento_max_kmh: string, temperatura_max_c: string, humedad_max_pct: string, altura_vuelo_m: ?string, velocidad_vuelo_kmh: ?string, ancho_pasada_m: ?string}
     */
    public function efectivos(): array;
}
