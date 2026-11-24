<?php

namespace App\Dominios\Flota\Contratos;

/**
 * Lectura de drones para otros módulos (ADR 0003, regla 2): `Proveedores`
 * necesita el dueño de un dron para avisarle de un documento por vencer, y la
 * asignación (ADR 0030) pedirá los equipos aptos por este mismo contrato.
 */
interface LecturaDrones
{
    public function dron(int $id): ?DatosDron;

    /**
     * Fotos del dron que el administrador aprobó: lo que el cliente ve al comparar ofertas.
     *
     * @return list<array{id: int, tipo: string}>
     */
    public function fotosAprobadas(int $dronId): array;
}
