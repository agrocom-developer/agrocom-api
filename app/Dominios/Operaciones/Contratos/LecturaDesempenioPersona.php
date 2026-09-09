<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Frontera de lectura de Operaciones hacia otros módulos (ADR 0003, regla 2;
 * HU-58, tarea 81): "¿qué hizo esta persona esta campaña?" — la ficha de
 * desempeño de `Personal` no puede tocar `ope_*` directo, así que pide acá.
 *
 * Por SESIÓN, no por equipo de trabajo (ADR 0015 punto 3): la pertenencia a
 * un equipo no es exclusiva, así que no es la unidad que atribuye una
 * aplicación a una persona sin ambigüedad. La sesión, en cambio, ya trae
 * `piloto_id`/`auxiliar_id` firmes.
 *
 * Cliente y campaña de cada fila salen de `Trabajo → OrdenAplicacion →
 * Comercial\Contratos\LecturaContrato` (que a su vez resuelve la campaña vía
 * `Campania\Contratos\LecturaCampania`) — nunca deducidos por fecha ni por el
 * campo, que con varias campañas abiertas del mismo cliente es ambiguo (ADR
 * 0015 punto 1).
 */
interface LecturaDesempenioPersona
{
    /** Todo lo que `$personaId` hizo entre `$desde` y `$hasta` (fechas ISO, inclusive), ya resuelto. */
    public function ejecutar(int $personaId, string $desde, string $hasta): DatosDesempenioPersona;
}
