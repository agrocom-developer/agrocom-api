<?php

namespace App\Dominios\Solicitudes\Contratos;

/**
 * Los pasos de la solicitud que decide una disputa (especificación §5.1, ADR
 * 0033 y 0039). Cada uno pasa por la máquina de estados de la solicitud, con el
 * `origen` y el autor en el historial (invariante 7 de CLAUDE.md).
 */
interface ResolucionDeServicio
{
    /**
     * `ejecutada` pasa a `en_disputa`. Devuelve `false` sin hacer nada si la
     * solicitud no está `ejecutada` (la disputa del proveedor por un cobro, por
     * ejemplo, puede abrirse con la solicitud ya completada): en ese caso la
     * disputa convive con la solicitud sin moverla.
     *
     * @param  string  $origen  `cliente` o `proveedor`.
     */
    public function abrirDisputa(int $solicitudId, string $origen, ?int $actorUsuarioId, string $motivo): bool;

    /**
     * Cierra la disputa sobre la solicitud: `en_disputa` pasa a `completada`
     * (con monto final y comisión congelados) o, si el reembolso es total, a
     * `cancelada`. Si la solicitud no estaba `en_disputa` no la mueve. Devuelve
     * la foto resultante.
     */
    public function cerrarDisputa(int $solicitudId, bool $reembolsoTotal, ?int $actorUsuarioId, string $motivo): ?DatosServicio;
}
