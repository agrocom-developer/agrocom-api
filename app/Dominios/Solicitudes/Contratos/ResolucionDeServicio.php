<?php

namespace App\Dominios\Solicitudes\Contratos;

/**
 * Los pasos de la solicitud que deciden un reclamo o una calificación
 * (`negociacion-contrato.md` §1, ADR 0033 y 0039). Cada uno pasa por la máquina de
 * estados de la solicitud, con el `origen` y el autor en el historial
 * (invariante 7 de CLAUDE.md).
 */
interface ResolucionDeServicio
{
    /**
     * `finalizada` o `calificada` pasa a `en_reclamo`. Devuelve `false` sin hacer
     * nada si la solicitud está en otro estado (el reclamo del proveedor por un
     * acceso negado, por ejemplo, puede abrirse con la solicitud en curso): en ese
     * caso el reclamo convive con la solicitud sin moverla.
     *
     * @param  string  $origen  `cliente` o `proveedor`.
     */
    public function abrirReclamo(int $solicitudId, string $origen, ?int $actorUsuarioId, string $motivo): bool;

    /**
     * Cierra el reclamo sobre la solicitud: `en_reclamo` vuelve a `finalizada` o
     * `calificada` (la que era al abrirla) o, si el reembolso es total, pasa a
     * `cancelada`. Si la solicitud no estaba `en_reclamo` no la mueve. Devuelve la
     * foto resultante.
     */
    public function cerrarReclamo(int $solicitudId, bool $reembolsoTotal, ?int $actorUsuarioId, string $motivo): ?DatosServicio;

    /** `finalizada` pasa a `calificada` cuando el cliente registra su calificación. No hace nada en otro estado. */
    public function marcarCalificada(int $solicitudId, ?int $actorUsuarioId): void;
}
