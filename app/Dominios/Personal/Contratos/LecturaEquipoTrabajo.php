<?php

namespace App\Dominios\Personal\Contratos;

/**
 * Frontera de lectura de equipos de trabajo hacia otros módulos (ADR 0003,
 * regla 2; ADR 0015 punto 3) — las tareas 73 (gasto/combustible) y 74
 * (estadía) necesitan saber qué equipos existen, quién los integraba y qué
 * recursos tenían, sin importar los modelos Eloquent de `Personal`.
 *
 * `vigentesAFecha()` reemplaza a "equipos vigentes de una campaña" (la forma
 * en la que quedó redactado el criterio de aceptación original de esta
 * tarea): `per_equipos_trabajo` no lleva `campania_id` — corrección del
 * dueño del 8/9/2026, ADR 0015 punto 3, "el equipo es de Agrocom y trabaja
 * para varias campañas" — así que no existe un recorte por campaña que
 * hacer acá. Lo que sí existe, y es lo que el consumidor necesita para
 * poblar un selector al registrar un gasto o una estadía en una fecha dada,
 * es el recorte por vigencia del equipo en sí (`desde`/`hasta`).
 *
 * `integrantesAFecha()`/`recursosAFecha()` comparten la misma forma —fecha
 * puntual, vigencia que la contiene— porque ambos son la misma pregunta
 * aplicada a un sujeto distinto (persona o recurso): "quién/qué tenía este
 * equipo ese día", igual que `ValidadorSolapamientoVigencias` no distingue
 * entre los dos casos.
 */
interface LecturaEquipoTrabajo
{
    /** @return list<DatosEquipoTrabajo> equipos cuya vigencia propia contiene `$fecha`. */
    public function vigentesAFecha(string $fecha): array;

    /**
     * Los equipos pedidos, vigentes o no, indexados por id — para rotular un
     * trabajo ya asignado, que debe seguir nombrando a su equipo aunque este
     * haya terminado su vigencia o se haya dado de baja después. Un id que no
     * existe simplemente no aparece.
     *
     * @param  list<int>  $ids
     * @return array<int, DatosEquipoTrabajo>
     */
    public function porIds(array $ids): array;

    /** @return list<DatosIntegranteEquipo> integrantes del equipo cuya vigencia contiene `$fecha`. */
    public function integrantesAFecha(int $equipoTrabajoId, string $fecha): array;

    /** @return list<DatosRecursoEquipo> recursos del equipo cuya vigencia contiene `$fecha`. */
    public function recursosAFecha(int $equipoTrabajoId, string $fecha): array;
}
