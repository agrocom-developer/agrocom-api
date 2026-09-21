<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;

/**
 * Caso de uso: desde dónde arranca el mapa de una propiedad que todavía no
 * tiene marcador ni perímetro cargado (adenda 16/9/2026 a ADR 0018 punto 1)
 * — pedido directo del dueño: usar el departamento ya cargado en el
 * formulario principal como punto de partida, y Santa Cruz de la Sierra como
 * último respaldo cuando tampoco hay departamento.
 *
 * Solo a nivel departamento: `com_provincias`/`com_municipios` no guardan
 * centroide propio (cientos de filas sin ese dato) — agregarlo excede el
 * alcance de "arreglar el editor de mapa". Nueve valores a nivel
 * departamento ya evitan que el mapa arranque siempre en el mismo punto del
 * país; el usuario ajusta con el buscador de coordenadas o el marcador.
 *
 * Si la propiedad tiene municipio, el editor afina este punto (19/9/2026):
 * ubica el municipio con un geocodificador a partir de sus nombres
 * ({@see ResolverMunicipioPropiedad}) y este centroide queda de respaldo por
 * si no lo encuentra.
 *
 * IDs fijos de `GeografiaBoliviaSeeder` (database/seeders/Catalogo/) — "mismos
 * IDs que el dump de origen del dueño", documentado en la migración que crea
 * `com_departamentos`.
 */
final class ResolverCentroReferenciaPropiedad
{
    /** Centroide aproximado (capital o punto medio del territorio) — valor
     *  de UX para encuadrar el mapa, nunca un dato geodésico de precisión.
     *
     * @var array<int, array{lat: float, lng: float}>
     */
    private const CENTROIDES_POR_DEPARTAMENTO_ID = [
        1 => ['lat' => -17.783, 'lng' => -63.182], // Santa Cruz
        2 => ['lat' => -16.500, 'lng' => -68.150], // La Paz
        3 => ['lat' => -17.390, 'lng' => -66.160], // Cochabamba
        4 => ['lat' => -19.590, 'lng' => -65.750], // Potosí
        5 => ['lat' => -19.033, 'lng' => -65.260], // Chuquisaca
        6 => ['lat' => -17.980, 'lng' => -67.110], // Oruro
        7 => ['lat' => -21.535, 'lng' => -64.730], // Tarija
        8 => ['lat' => -14.833, 'lng' => -64.900], // Beni
        9 => ['lat' => -11.030, 'lng' => -68.770], // Pando
    ];

    /** Santa Cruz de la Sierra, Bolivia — pedido directo del dueño como
     *  último respaldo. */
    private const CENTRO_POR_DEFECTO = ['lat' => -17.783327, 'lng' => -63.182140];

    /** @return array{lat: float, lng: float} */
    public function ejecutar(Propiedad $propiedad): array
    {
        return self::CENTROIDES_POR_DEPARTAMENTO_ID[$propiedad->departamento_id] ?? self::CENTRO_POR_DEFECTO;
    }
}
