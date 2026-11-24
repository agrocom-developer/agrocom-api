<?php

namespace App\Dominios\Solicitudes\Contratos;

/**
 * Frontera de `Solicitudes` hacia `Operaciones` (ADR 0003, regla 2): la
 * ejecución en el lote avanza el ciclo de la solicitud sin tocar su modelo ni
 * su tabla. Toda transición pasa por `MaquinaEstadosSolicitud` (invariante 7 de
 * CLAUDE.md); el motor de sync la invoca dentro de su propia transacción.
 */
interface SolicitudParaTrabajo
{
    /** La solicitud, o `null` si no existe. */
    public function datos(int $solicitudId): ?DatosSolicitudParaTrabajo;

    /**
     * El equipo abrió un trabajo: la solicitud pasa a `en_ejecucion`,
     * recorriendo si hace falta `asignada → en_camino` (el equipo trabaja sin
     * señal y no siempre alcanza a avisar que salió). Idempotente: ya en
     * `en_ejecucion` no hace nada. Origen `sistema`.
     */
    public function iniciarEjecucion(int $solicitudId): void;

    /**
     * Un trabajo se cerró: si lo declarado por los trabajos cerrados cubre al
     * menos el porcentaje mínimo de lo pedido (`solicitudes.cobertura_minima_pct`),
     * la solicitud pasa de `en_ejecucion` a `ejecutada` y falta la conformidad
     * del cliente. Si no, sigue en ejecución a la espera de más trabajo.
     * Idempotente y tolerante: una solicitud que ya no está `en_ejecucion` (por
     * ejemplo, cancelada mientras el equipo volaba sin señal) no cambia.
     *
     * @param  string  $hectareasCerradas  suma decimal exacta de `hectareas_declaradas` de los trabajos cerrados.
     */
    public function registrarCobertura(int $solicitudId, string $hectareasCerradas): void;
}
