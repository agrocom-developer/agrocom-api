<?php

namespace App\Dominios\Flota\Contratos;

/**
 * Quiénes integran un equipo y de dónde viene su dron (ver
 * {@see LecturaIntegrantesDeEquipo}). Primitivos puros.
 */
final readonly class DatosIntegrantesEquipo
{
    public function __construct(
        public int $equipoId,
        public int $proveedorId,
        public int $pilotoId,
        public int $dronId,
        public string $dronOrigen,
    ) {}

    /** Si el dron lo vendió Agrocom: la comisión usa la tasa preferencial del grupo (política de comisión). */
    public function dronVendidoPorAgrocom(): bool
    {
        return $this->dronOrigen === 'vendido_por_agrocom';
    }
}
