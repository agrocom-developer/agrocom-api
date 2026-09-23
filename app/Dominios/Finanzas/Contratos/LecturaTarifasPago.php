<?php

namespace App\Dominios\Finanzas\Contratos;

/**
 * Lectura del catálogo de tarifas para otros módulos (ADR 0023). Operaciones
 * la usa en el alta de la Orden de Trabajo: lista las vigentes para el select
 * de cada equipo y resuelve una por id al guardar.
 */
interface LecturaTarifasPago
{
    /** @return list<TarifaPago> Vivas, la predeterminada primero y luego por nombre. */
    public function vigentes(): array;

    public function porId(int $tarifaId): ?TarifaPago;

    public function predeterminada(): ?TarifaPago;
}
