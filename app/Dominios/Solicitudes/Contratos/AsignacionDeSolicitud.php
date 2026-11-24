<?php

namespace App\Dominios\Solicitudes\Contratos;

/**
 * Los pasos de la solicitud que decide la asignación (ADR 0030 y 0037, punto
 * 2). Cada uno pasa por la máquina de estados de la solicitud, con el `origen`
 * (`sistema`, `proveedor` o `administrador`) y el autor en el historial.
 * Se llaman dentro de la transacción de quien decide: la máquina bloquea la
 * fila, así que dos decisiones simultáneas no se pisan.
 */
interface AsignacionDeSolicitud
{
    /**
     * `publicada` pasa a `en_asignacion`. Devuelve `false` sin hacer nada si la
     * solicitud ya no está `publicada` (el motor ya la tomó o la cancelaron):
     * tomar dos veces la misma solicitud es inofensivo.
     */
    public function iniciar(int $solicitudId): bool;

    /**
     * `en_asignacion` pasa a `asignada` y fija proveedor y equipo.
     *
     * @throws SolicitudNoAsignable si la solicitud ya no está `en_asignacion`.
     */
    public function asignar(int $solicitudId, int $proveedorId, int $equipoId, string $origen, ?int $actorUsuarioId, ?string $motivo): void;

    /**
     * `en_asignacion` pasa a `sin_proveedor`: se agotaron las rondas.
     *
     * @throws SolicitudNoAsignable
     */
    public function marcarSinProveedor(int $solicitudId): void;

    /**
     * `asignada` o `en_camino` vuelve a `en_asignacion` (el proveedor cancela o
     * un administrador reasigna). Quita el proveedor y conserva el `equipo_id`
     * para que la app del equipo retire el servicio del lote (ADR 0037, punto 6).
     *
     * @throws SolicitudNoAsignable
     */
    public function devolver(int $solicitudId, string $origen, ?int $actorUsuarioId, string $motivo): void;

    /**
     * `asignada` pasa a `en_camino`: el proveedor avisa que el equipo salió hacia el lote.
     *
     * @throws SolicitudNoAsignable si la solicitud ya no está `asignada`.
     */
    public function salirEnCamino(int $solicitudId, ?int $actorUsuarioId): void;

    /**
     * `sin_proveedor` vuelve a `en_asignacion` (pasando por `publicada`): un
     * administrador la asigna a mano.
     *
     * @throws SolicitudNoAsignable
     */
    public function reabrir(int $solicitudId, ?int $actorUsuarioId, string $motivo): void;
}
