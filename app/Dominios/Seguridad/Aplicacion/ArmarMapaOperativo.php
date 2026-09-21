<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Comercial\Contratos\LecturaPanelComercial;
use App\Dominios\Comercial\Contratos\LotePanel;
use App\Dominios\Operaciones\Contratos\LecturaPanelOperaciones;
use App\Dominios\Operaciones\Contratos\ResumenLotePanel;

/**
 * Arma el `FeatureCollection` de lotes que dibuja el mapa operativo, cruzando
 * la geometría real de `com_lotes` (Comercial) con el avance de sesiones de
 * cada lote (Operaciones).
 *
 * Reemplaza a la maqueta del mapa, que devolvía cuatro polígonos
 * inventados con hectáreas que no coincidían con las de la base. Dos
 * diferencias con aquel mock, además del origen:
 *
 * - **Un lote sin geometría no se dibuja.** No se le inventa un rectángulo:
 *   la geometría se carga a mano y faltar es un estado legítimo.
 * - **No hay capa de sesiones georreferenciadas.** Ninguna tabla del esquema
 *   guarda la posición de una sesión (solo `com_lotes.geometria` es
 *   geográfica); el mock ponía puntos donde le convenía. El estado de las
 *   sesiones se ve como el COLOR del lote, que es un dato que sí existe.
 */
final class ArmarMapaOperativo
{
    private const ZOOM_INICIAL = 12;

    public function __construct(
        private readonly LecturaPanelComercial $comercial,
        private readonly LecturaPanelOperaciones $operaciones,
    ) {}

    /**
     * `null` cuando no hay ni un lote con perímetro cargado: sin geometría no
     * hay mapa que mostrar, y una sección vacía es peor que ninguna sección.
     *
     * @param  array<int, LotePanel>  $lotes
     * @return array{lotes: array<string, mixed>, centro: array{lat: float, lng: float}, zoom: int}|null
     */
    public function ejecutar(array $lotes): ?array
    {
        $centro = $this->comercial->centroOperativo();

        if ($centro === null) {
            return null;
        }

        $resumenPorLote = $this->operaciones->resumenPorLote();
        $features = [];

        foreach ($lotes as $lote) {
            if ($lote->geometria === null) {
                continue;
            }

            $features[] = $this->feature($lote, $resumenPorLote[$lote->id] ?? null);
        }

        if ($features === []) {
            return null;
        }

        return [
            'lotes' => ['type' => 'FeatureCollection', 'features' => $features],
            'centro' => $centro,
            'zoom' => self::ZOOM_INICIAL,
        ];
    }

    /**
     * Las `properties` son datos crudos, nunca HTML: `dashboard-map.js` arma
     * el popup con `textContent`, y estos valores (razón social del cliente,
     * código de lote) son editables desde el panel.
     *
     * @return array<string, mixed>
     */
    private function feature(LotePanel $lote, ?ResumenLotePanel $resumen): array
    {
        return [
            'type' => 'Feature',
            'properties' => [
                'nombre' => "{$lote->codigo} — {$lote->propiedadNombre}",
                'cliente' => $lote->clienteNombre,
                'hectareas' => $lote->hectareas,
                'hectareasAplicadas' => $resumen->hectareasAplicadas ?? '0.00',
                'sesiones' => $resumen->sesiones ?? 0,
                // Un lote sin ninguna sesión es `neutral` (programado), no
                // `success`: todavía no se voló, no es que se terminó.
                'tono' => $resumen->tono ?? 'neutral',
            ],
            'geometry' => $lote->geometria,
        ];
    }
}
