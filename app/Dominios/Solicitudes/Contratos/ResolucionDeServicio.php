<?php

namespace App\Dominios\Solicitudes\Contratos;

/**
 * Los pasos de la solicitud que deciden una disputa o una calificación
 * (`negociacion-contrato.md` §1, ADR 0033 y 0039). Cada uno pasa por la máquina de
 * estados de la solicitud, con el `origen` y el autor en el historial
 * (invariante 7 de CLAUDE.md).
 */
interface ResolucionDeServicio
{
    /**
     * `finalizada` o `calificada` pasa a `disputada`. Devuelve `false` sin hacer
     * nada si la solicitud está en otro estado (la disputa del proveedor por un
     * acceso negado, por ejemplo, puede abrirse con la solicitud en curso): en ese
     * caso la disputa convive con la solicitud sin moverla.
     *
     * @param  string  $origen  `cliente` o `proveedor`.
     */
    public function abrirDisputa(int $solicitudId, string $origen, ?int $actorUsuarioId, string $motivo): bool;

    /**
     * Cierra la disputa sobre la solicitud: `disputada` vuelve a `finalizada` o
     * `calificada` (la que era al abrirla) o, si el reembolso es total, pasa a
     * `cancelada`. Si la solicitud no estaba `disputada` no la mueve. Devuelve la
     * foto resultante.
     */
    public function cerrarDisputa(int $solicitudId, bool $reembolsoTotal, ?int $actorUsuarioId, string $motivo): ?DatosServicio;

    /** `finalizada` pasa a `calificada` cuando el cliente registra su calificación. No hace nada en otro estado. */
    public function marcarCalificada(int $solicitudId, ?int $actorUsuarioId): void;
}
