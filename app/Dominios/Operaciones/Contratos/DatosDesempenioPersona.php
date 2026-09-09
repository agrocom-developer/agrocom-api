<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Resultado completo de {@see LecturaDesempenioPersona::ejecutar()} (tarea
 * 81): las tres listas separadas que pide HU-58 — nunca un puntaje ni un
 * promedio, solo hechos con su fuente (ver `prompts/81-ficha-desempeno.md`,
 * "Qué NO hacer").
 *
 * `sesiones` son únicamente las VIGENTES (`anulada_en` null): las rechazadas
 * viven aparte en `rechazos` y a propósito no suman hectáreas (invariante 2
 * de CLAUDE.md — un rechazo no pisa la sesión original, y acá tampoco se
 * mezcla con lo que sí se validó/aplicó).
 */
final readonly class DatosDesempenioPersona
{
    /**
     * @param  list<DatosSesionDesempenio>  $sesiones
     * @param  list<DatosRechazoDesempenio>  $rechazos
     * @param  list<DatosIncidenciaDesempenio>  $incidencias
     */
    public function __construct(
        public array $sesiones,
        public array $rechazos,
        public array $incidencias,
    ) {}
}
