<?php

namespace App\Dominios\Solicitudes\Contratos;

use DateTimeInterface;

/**
 * Los pasos de la solicitud que decide la negociación (invariante 7 de CLAUDE.md:
 * cada uno pasa por `MaquinaEstadosSolicitud`, con origen y autor en el historial).
 * La negociación bloquea primero la fila de la solicitud (`bloquear`) y después
 * las de sus ofertas, siempre en ese orden.
 */
interface PasosDeSolicitud
{
    /** Lectura de la solicitud; con `$bloquear` toma el bloqueo de la fila dentro de la transacción en curso. */
    public function datos(int $solicitudId, bool $bloquear = false): ?DatosSolicitudNegociable;

    /** `abierta` pasa a `con_ofertas`. No hace nada si ya lo está. */
    public function marcarConOfertas(int $solicitudId): void;

    /** `con_ofertas` vuelve a `abierta` (no queda ninguna oferta vigente). No hace nada en otro estado. */
    public function reabrirSinOfertas(int $solicitudId): void;

    /**
     * `abierta` o `con_ofertas` pasa a `adjudicada`: fija proveedor, equipo, oferta y precio acordado, y congela el monto estimado.
     *
     * @throws SolicitudNoNegociable
     */
    public function adjudicar(int $solicitudId, int $proveedorId, int $equipoId, int $ofertaId, string $precioHaAcordado, string $origen, ?int $actorUsuarioId): void;

    /**
     * `adjudicada` pasa a `en_ejecucion`.
     *
     * @throws SolicitudNoNegociable
     */
    public function iniciarEjecucion(int $solicitudId, ?int $actorUsuarioId, DateTimeInterface $en): void;

    /** Guarda el avance (hectáreas y porcentaje) de una solicitud `en_ejecucion`. */
    public function registrarAvance(int $solicitudId, string $hectareasEjecutadas, string $porcentaje, DateTimeInterface $en): void;

    /** Marca que el piloto pidió el cierre (queda a la espera de las evidencias mínimas). */
    public function marcarCierreSolicitado(int $solicitudId, string $hectareasEjecutadas, DateTimeInterface $en): void;

    /**
     * `en_ejecucion` pasa a `finalizada`: congela hectáreas ejecutadas y `monto_final = hectáreas × precio acordado`.
     *
     * @throws SolicitudNoNegociable
     */
    public function finalizar(int $solicitudId, string $hectareasEjecutadas, ?int $actorUsuarioId, DateTimeInterface $en): void;

    /**
     * `adjudicada` vuelve a `abierta` por decisión del proveedor: libera proveedor, equipo y precio acordado.
     *
     * @throws SolicitudNoNegociable
     */
    public function devolverAbierta(int $solicitudId, ?int $actorUsuarioId, string $motivo): void;

    /** Estados de las solicitudes que cruzan la ventana de fecha deseada del equipo (para la disponibilidad al adjudicar); sin fecha deseada no hay cruce. */
    public function equipoOcupado(int $equipoId, ?string $ventanaDesde, ?string $ventanaHasta, int $exceptoSolicitudId): bool;
}
