<?php

namespace App\Dominios\Reclamos\Dominio;

/** Las dos partes de un reclamo: lo abre el cliente o el proveedor (política de reclamos, «Cuándo se abre»). */
enum ParteDeReclamo: string
{
    case Cliente = 'cliente';
    case Proveedor = 'proveedor';

    public function contraparte(): self
    {
        return $this === self::Cliente ? self::Proveedor : self::Cliente;
    }

    /** @return list<MotivoReclamo> los motivos que esa parte puede alegar. */
    public function motivos(): array
    {
        return $this === self::Cliente ? MotivoReclamo::delCliente() : MotivoReclamo::delProveedor();
    }
}
