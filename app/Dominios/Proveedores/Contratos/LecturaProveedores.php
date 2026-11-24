<?php

namespace App\Dominios\Proveedores\Contratos;

/**
 * Lo que `Flota` (y, más adelante, `Asignacion`) necesita saber de proveedores,
 * pilotos y auxiliares sin importar sus modelos (ADR 0003, regla 2).
 */
interface LecturaProveedores
{
    public function proveedor(int $id): ?DatosProveedor;

    /** El proveedor del que la cuenta es titular (una cuenta es titular de uno como máximo). */
    public function proveedorDelTitular(int $usuarioId): ?DatosProveedor;

    /** El proveedor Agrocom (único, tipo `propio`), si ya existe. */
    public function proveedorPropio(): ?DatosProveedor;

    public function piloto(int $id): ?DatosPiloto;

    public function auxiliar(int $id): ?DatosAuxiliar;

    /**
     * Ids de los registros de piloto y de auxiliar de una cuenta (una cuenta
     * puede ser ambos). El equipo de flota los referencia por esos ids; la app
     * de campo conoce a la persona por su cuenta.
     *
     * @return array{pilotos: list<int>, auxiliares: list<int>}
     */
    public function registrosDeCuenta(int $usuarioId): array;

    /**
     * Pilotos vivos de un proveedor.
     *
     * @return list<DatosPiloto>
     */
    public function pilotosDe(int $proveedorId): array;
}
