<?php

namespace Database\Seeders\Catalogo;

use App\Dominios\Solicitudes\Infraestructura\Eloquent\Zona;
use Illuminate\Database\Seeder;

/**
 * Catálogo de zonas del marketplace (especificación §4.1): los polos agrícolas
 * de Santa Cruz, con su centroide y un radio de referencia. El proveedor declara
 * una como zona base y la asignación las usa para escalar por radio (ADR 0030).
 * Corre en todos los entornos, producción incluida. Idempotente por nombre: no
 * pisa lo que el dueño ajustó.
 */
class ZonasSeeder extends Seeder
{
    /** @var list<array{string, string, string, string}> nombre, latitud, longitud, radio en km */
    private const ZONAS = [
        ['Santa Cruz de la Sierra', '-17.7833000', '-63.1821000', '40.0'],
        ['Warnes', '-17.5141000', '-63.1668000', '25.0'],
        ['Montero', '-17.3395000', '-63.2528000', '30.0'],
        ['Yapacaní', '-17.3944000', '-63.8806000', '45.0'],
        ['Pailón', '-17.6544000', '-62.7338000', '40.0'],
        ['Cuatro Cañadas', '-17.3583000', '-62.5667000', '45.0'],
        ['San Julián', '-16.9164000', '-62.6167000', '45.0'],
        ['Cabezas', '-18.7833000', '-63.4167000', '40.0'],
        ['San José de Chiquitos', '-17.8450000', '-60.7390000', '60.0'],
    ];

    public function run(): void
    {
        foreach (self::ZONAS as [$nombre, $latitud, $longitud, $radio]) {
            Zona::query()->firstOrCreate(
                ['nombre' => $nombre],
                ['centroide_lat' => $latitud, 'centroide_lng' => $longitud, 'radio_km' => $radio],
            );
        }
    }
}
