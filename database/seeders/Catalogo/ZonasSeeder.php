<?php

namespace Database\Seeders\Catalogo;

use App\Dominios\Solicitudes\Infraestructura\Eloquent\Zona;
use Illuminate\Database\Seeder;

/**
 * Catálogo de zonas del marketplace (especificación §4.1): los polos agrícolas
 * de Santa Cruz, con su centroide y un radio de referencia. El proveedor declara
 * una como zona base y el mapa de solicitudes las agrupa por macrorregión (ADR 0030, 0041).
 * Corre en todos los entornos, producción incluida. Idempotente por nombre: no
 * pisa lo que el dueño ajustó.
 */
class ZonasSeeder extends Seeder
{
    /** @var list<array{string, string, string, string, string}> nombre, latitud, longitud, radio en km, macrorregión */
    private const ZONAS = [
        ['Santa Cruz de la Sierra', '-17.7833000', '-63.1821000', '40.0', 'cercado'],
        ['Warnes', '-17.5141000', '-63.1668000', '25.0', 'norte_integrado'],
        ['Montero', '-17.3395000', '-63.2528000', '30.0', 'norte_integrado'],
        ['Yapacaní', '-17.3944000', '-63.8806000', '45.0', 'norte_integrado'],
        ['Pailón', '-17.6544000', '-62.7338000', '40.0', 'chiquitania'],
        ['Cuatro Cañadas', '-17.3583000', '-62.5667000', '45.0', 'norte_integrado'],
        ['San Julián', '-16.9164000', '-62.6167000', '45.0', 'norte_integrado'],
        ['Cabezas', '-18.7833000', '-63.4167000', '40.0', 'chaco'],
        ['San José de Chiquitos', '-17.8450000', '-60.7390000', '60.0', 'chiquitania'],
        ['San Ignacio de Velasco', '-16.3733000', '-60.9633000', '60.0', 'chiquitania'],
        ['Vallegrande', '-18.4890000', '-64.1090000', '40.0', 'valles'],
        ['Samaipata', '-18.1780000', '-63.8750000', '30.0', 'valles'],
    ];

    public function run(): void
    {
        foreach (self::ZONAS as [$nombre, $latitud, $longitud, $radio, $macrorregion]) {
            $zona = Zona::query()->firstOrCreate(
                ['nombre' => $nombre],
                ['centroide_lat' => $latitud, 'centroide_lng' => $longitud, 'radio_km' => $radio, 'macrorregion' => $macrorregion],
            );

            // La región alimenta el mapa de los pilotos: se completa si una zona ya cargada no la trae.
            if ($zona->macrorregion === null) {
                $zona->forceFill(['macrorregion' => $macrorregion])->save();
            }
        }
    }
}
