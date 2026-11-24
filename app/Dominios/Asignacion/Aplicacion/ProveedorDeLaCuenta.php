<?php

namespace App\Dominios\Asignacion\Aplicacion;

use App\Dominios\Asignacion\Dominio\Excepciones\AsignacionInvalida;
use App\Dominios\Proveedores\Contratos\DatosProveedor;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;

/**
 * Resuelve el proveedor de la cuenta del token: el que titula o aquel del que
 * es piloto. Todo lo que la API de ofertas devuelve parte de este proveedor
 * (invariante 5 de CLAUDE.md): una oferta de otro proveedor responde 404.
 */
final class ProveedorDeLaCuenta
{
    public function __construct(private readonly LecturaProveedores $proveedores) {}

    /** @throws AsignacionInvalida si la cuenta no pertenece a ningún proveedor. */
    public function ejecutar(int $usuarioId): DatosProveedor
    {
        return $this->proveedores->proveedorDeLaCuenta($usuarioId) ?? throw AsignacionInvalida::sinProveedor();
    }
}
