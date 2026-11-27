<?php

namespace App\Dominios\Flota\Contratos;

/**
 * Lectura de drones para otros módulos (ADR 0003, regla 2): `Proveedores`
 * necesita el dueño de un dron para avisarle de un documento por vencer, y la
 * negociación (ADR 0030) pide los equipos aptos por `LecturaEquipos`.
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

    /** Si el proveedor (dueño u operador) tiene al menos un dron vivo: sin dar de baja ni borrado. */
    public function tieneDron(int $proveedorId): bool;

    /**
     * Si algún dron vivo del proveedor ya tiene subida (en revisión o aprobada, no rechazada) una foto de cada tipo
     * obligatorio (`config('flota.fotos_obligatorias')`): es lo que el registro guiado pide antes de esperar la revisión.
     */
    public function tieneDronConFotosSubidas(int $proveedorId): bool;
}
