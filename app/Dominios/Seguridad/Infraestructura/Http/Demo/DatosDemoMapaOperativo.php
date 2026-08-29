<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Demo;

/**
 * ============================== MOCK ==============================
 * Datos de DEMO de los tabs "Mapa" y "Resumen por lote" del dashboard —
 * separado de DatosDemoPanel por el volumen del payload geoespacial y
 * porque conceptualmente es "todo lo geoespacial/por-lote", candidato
 * natural a reemplazarse en bloque cuando exista el endpoint real de
 * Comercial (`com_lotes.geometria`, ya en BD — ver
 * database/seeders/Demo/NucleoComercialSeeder.php) y de Operaciones
 * (sesiones reales con posición).
 *
 * El shape de {@see lotes()} calca el GeoJSON real de `com_lotes.geometria`
 * (`{type: 'Polygon', coordinates: [[[lng, lat], ...]]}`) y las coordenadas
 * están centradas en la misma zona que usa el seeder (Warnes, Santa Cruz,
 * ~lng -62.85 / lat -17.34) para que el día que se conecte a datos reales
 * el mapa no "salte" de continente.
 *
 * Las propiedades de cada feature son datos crudos (nombre, cliente, hora,
 * piloto...) — nunca HTML: dashboard-map.js arma el contenido de cada popup
 * con `textContent`/`createElement`, no `innerHTML`, para no establecer un
 * patrón de HTML-desde-el-servidor en un componente que algún día mostrará
 * datos reales (nombre de cliente, piloto) potencialmente editables.
 * ==================================================================
 */
final class DatosDemoMapaOperativo
{
    /**
     * Polígonos de lotes coloreados por estado agregado de sus sesiones del
     * día. `tono` es el mismo vocabulario que el resto del dashboard
     * (success|warning|info|neutral).
     *
     * @return array{type: string, features: list<array{type: string, properties: array{nombre: string, cliente: string, hectareas: string, tono: string}, geometry: array{type: string, coordinates: list<list<list<float>>>}}>}
     */
    public function lotes(): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => [
                $this->lote('Lote 12 — San Marcos', 'Agropecuaria San Marcos S.R.L.', '86 ha', 'warning', [
                    [-62.8600, -17.3300], [-62.8450, -17.3300], [-62.8450, -17.3420], [-62.8600, -17.3420], [-62.8600, -17.3300],
                ]),
                $this->lote('Lote 3 — El Carmen', 'El Carmen Agroindustrial S.A.', '112 ha', 'info', [
                    [-62.8430, -17.3300], [-62.8280, -17.3300], [-62.8280, -17.3420], [-62.8430, -17.3420], [-62.8430, -17.3300],
                ]),
                $this->lote('Lote 8 — El Carmen', 'El Carmen Agroindustrial S.A.', '48 ha', 'neutral', [
                    [-62.8430, -17.3440], [-62.8280, -17.3440], [-62.8280, -17.3540], [-62.8430, -17.3540], [-62.8430, -17.3440],
                ]),
                $this->lote('Lote 1 — Santa Rosa', 'Grupo Santa Rosa', '130 ha', 'neutral', [
                    [-62.8600, -17.3440], [-62.8450, -17.3440], [-62.8450, -17.3560], [-62.8600, -17.3560], [-62.8600, -17.3440],
                ]),
            ],
        ];
    }

    /**
     * Sesiones de fumigación del día, georreferenciadas — mismas 5 sesiones
     * de {@see DatosDemoPanel::sesiones()}, otra vista del mismo dato.
     *
     * @return array{type: string, features: list<array{type: string, properties: array{hora: string, piloto: string, dron: string, ha: string, tono: string}, geometry: array{type: string, coordinates: list<float>}}>}
     */
    public function sesionesGeo(): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => [
                $this->sesion('05:45', 'R. Vaca', 'T50 · AG-04', '86 ha', 'success', -62.8535, -17.3355),
                $this->sesion('07:10', 'R. Vaca', 'T50 · AG-04', '74 ha', 'warning', -62.8515, -17.3375),
                $this->sesion('08:30', 'M. Ordóñez', 'T70 · AG-07', '112 ha', 'info', -62.8355, -17.3360),
                $this->sesion('09:50', 'J. Peña', 'T30 · AG-02', '48 ha', 'neutral', -62.8355, -17.3490),
                $this->sesion('11:15', 'M. Ordóñez', 'T100 · AG-09', '130 ha', 'neutral', -62.8525, -17.3500),
            ],
        ];
    }

    /**
     * Resumen operativo por lote (Fase 7 — tab "Resumen por lote"): mismos
     * 4 lotes de {@see lotes()}, con las métricas que ya se ven en la
     * pantalla del control remoto del dron durante el vuelo (área
     * completada/pendiente, litros de pesticida, L/ha, tiempo de vuelo).
     * Cifras ancladas a los datos REALES transcritos en
     * `docs/especificacion/analisis_capturas_rc.md` §8 (L/ha entre 9,15 y
     * 12,15 en 7 misiones reales, mediana ~10,05; tiempos de vuelo entre
     * 2:54 y 10:40) para que no se sientan arbitrarias.
     *
     * @return list<array{lote: string, cliente: string, hectareasTotales: float, hectareasCompletadas: float, hectareasPendientes: float, litrosPesticida: float, litrosPorHectarea: float, tiempoVuelo: string, pctCompletado: float, tono: string}>
     */
    public function resumenPorLote(): array
    {
        return [
            ['lote' => 'Lote 12 — San Marcos', 'cliente' => 'Agropecuaria San Marcos S.R.L.', 'hectareasTotales' => 86.0, 'hectareasCompletadas' => 74.2, 'hectareasPendientes' => 11.8, 'litrosPesticida' => 745.6, 'litrosPorHectarea' => 10.05, 'tiempoVuelo' => '4:56', 'pctCompletado' => 86.3, 'tono' => 'warning'],
            ['lote' => 'Lote 3 — El Carmen', 'cliente' => 'El Carmen Agroindustrial S.A.', 'hectareasTotales' => 112.0, 'hectareasCompletadas' => 72.8, 'hectareasPendientes' => 39.2, 'litrosPesticida' => 730.4, 'litrosPorHectarea' => 10.03, 'tiempoVuelo' => '1:42', 'pctCompletado' => 65.0, 'tono' => 'info'],
            ['lote' => 'Lote 8 — El Carmen', 'cliente' => 'El Carmen Agroindustrial S.A.', 'hectareasTotales' => 48.0, 'hectareasCompletadas' => 0.0, 'hectareasPendientes' => 48.0, 'litrosPesticida' => 0.0, 'litrosPorHectarea' => 0.0, 'tiempoVuelo' => '0:00', 'pctCompletado' => 0.0, 'tono' => 'neutral'],
            ['lote' => 'Lote 1 — Santa Rosa', 'cliente' => 'Grupo Santa Rosa', 'hectareasTotales' => 130.0, 'hectareasCompletadas' => 0.0, 'hectareasPendientes' => 130.0, 'litrosPesticida' => 0.0, 'litrosPorHectarea' => 0.0, 'tiempoVuelo' => '0:00', 'pctCompletado' => 0.0, 'tono' => 'neutral'],
        ];
    }

    /**
     * Cuadros informativos del tab Mapa — agregados simples sobre el mismo
     * dato geoespacial de arriba.
     *
     * @return array{lotesEnMapa: int, hectareasEnMapa: string, sesionesGeorreferenciadas: int}
     */
    public function resumenMapa(): array
    {
        return [
            'lotesEnMapa' => 4,
            'hectareasEnMapa' => '376 ha',
            'sesionesGeorreferenciadas' => 5,
        ];
    }

    /** @param list<list<float>> $anillo */
    private function lote(string $nombre, string $cliente, string $hectareas, string $tono, array $anillo): array
    {
        return [
            'type' => 'Feature',
            'properties' => [
                'nombre' => $nombre,
                'cliente' => $cliente,
                'hectareas' => $hectareas,
                'tono' => $tono,
            ],
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [$anillo],
            ],
        ];
    }

    private function sesion(string $hora, string $piloto, string $dron, string $ha, string $tono, float $lng, float $lat): array
    {
        return [
            'type' => 'Feature',
            'properties' => [
                'hora' => $hora,
                'piloto' => $piloto,
                'dron' => $dron,
                'ha' => $ha,
                'tono' => $tono,
            ],
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$lng, $lat],
            ],
        ];
    }
}
