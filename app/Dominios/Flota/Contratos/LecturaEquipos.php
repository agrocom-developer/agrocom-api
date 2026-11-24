<?php

namespace App\Dominios\Flota\Contratos;

use DateTimeImmutable;

/**
 * Elegibilidad de equipos para la asignación (especificación §5.2 y §6, ADR
 * 0030). El motor de asignación pregunta por este contrato y nunca recorre las
 * tablas de flota ni de proveedores.
 */
interface LecturaEquipos
{
    /**
     * Equipos de un proveedor que hoy pueden recibir ofertas: proveedor, piloto,
     * dron y auxiliar (si lo lleva) aprobados, documentos vigentes y vigencia
     * abarcando la fecha. Vacío si el proveedor no está aprobado.
     *
     * @return list<DatosEquipoApto>
     */
    public function aptosDe(int $proveedorId, ?DateTimeImmutable $hoy = null): array;

    /** Si el proveedor puede recibir ofertas hoy (tiene al menos un equipo apto). */
    public function tieneEquipoApto(int $proveedorId, ?DateTimeImmutable $hoy = null): bool;
}
