<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Operaciones\Contratos\DatosIncidenciaDesempenio;
use App\Dominios\Operaciones\Contratos\DatosSesionDesempenio;

/**
 * Resultado de {@see ObtenerDesempenioPersona} (HU-58, tarea 81): las listas
 * ya filtradas por cliente/campaña, sus totales, y las opciones de los
 * selects de filtro — todo derivado de la MISMA consulta al contrato de
 * lectura, sin pedirle nada más a `Comercial` (los valores únicos de
 * cliente/campaña ya viajan en cada fila).
 *
 * Ningún puntaje ni ranking acá (ver "Qué NO hacer" del prompt de la tarea):
 * los totales son sumas/conteos de hechos, no una nota inventada.
 */
final readonly class ResultadoDesempenioPersona
{
    /**
     * @param  list<DatosSesionDesempenio>  $sesiones  vigentes, ya filtradas por cliente/campaña.
     * @param  list<FilaRechazoDesempenio>  $rechazos  ya filtrados por cliente/campaña.
     * @param  list<DatosIncidenciaDesempenio>  $incidencias  de las sesiones/rechazos visibles tras el filtro.
     * @param  array<int, string>  $clientesDisponibles  clienteId => nombre, ordenado por nombre.
     * @param  array<int, string>  $campaniasDisponibles  campaniaId => código, ordenado por código.
     * @param  array<int, int>  $clientePorCampania  campaniaId => clienteId, para filtrar el select de campaña en JS.
     */
    public function __construct(
        public array $sesiones,
        public array $rechazos,
        public array $incidencias,
        public string $hectareasAplicadas,
        public int $totalSesionesValidadas,
        public int $totalSesionesRechazadas,
        public int $totalIncidencias,
        public array $clientesDisponibles,
        public array $campaniasDisponibles,
        public array $clientePorCampania,
    ) {}
}
