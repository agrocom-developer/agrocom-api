<?php

namespace App\Dominios\Flota\Contratos;

use DateTimeImmutable;

/**
 * Elegibilidad de equipos para ofertar y adjudicar (especificación §5.2 y §6, ADR
 * 0030). La negociación pregunta por este contrato y nunca recorre las tablas
 * de flota ni de proveedores.
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

    /**
     * Ids de los equipos en los que la cuenta es piloto o auxiliar. Con
     * `$soloVigentes` solo los vigentes en la fecha; sin él, también los que ya
     * terminaron (la app de campo los necesita para retirar lo que ya no es suyo).
     * No juzga aptitud: eso es de {@see aptosDe()}.
     *
     * @return list<int>
     */
    public function equiposDeCuenta(int $usuarioId, bool $soloVigentes = true, ?DateTimeImmutable $hoy = null): array;

    /** Si el proveedor puede recibir ofertas hoy (tiene al menos un equipo apto). */
    public function tieneEquipoApto(int $proveedorId, ?DateTimeImmutable $hoy = null): bool;

    /**
     * Ids de los equipos vigentes hoy de un proveedor, sin juzgar su aptitud: el
     * proveedor declara disponibilidad de todos los suyos, también de los que
     * esperan aprobación.
     *
     * @return list<int>
     */
    public function vigentesDe(int $proveedorId, ?DateTimeImmutable $hoy = null): array;
}
