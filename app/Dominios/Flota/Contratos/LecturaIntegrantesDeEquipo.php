<?php

namespace App\Dominios\Flota\Contratos;

/**
 * Los integrantes de un equipo, sin juzgar su aptitud ni su vigencia: la
 * calificación y la comisión miran un equipo que ya ejecutó un servicio, aunque
 * hoy ya no esté vigente. Quien lo consume no importa los modelos de `Flota`
 * (ADR 0003).
 */
interface LecturaIntegrantesDeEquipo
{
    public function integrantes(int $equipoId): ?DatosIntegrantesEquipo;
}
