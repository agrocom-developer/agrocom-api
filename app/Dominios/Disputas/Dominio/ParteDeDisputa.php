<?php

namespace App\Dominios\Disputas\Dominio;

/** Las dos partes de una disputa: la abre el cliente o el proveedor (política de disputas, «Cuándo se abre»). */
enum ParteDeDisputa: string
{
    case Cliente = 'cliente';
    case Proveedor = 'proveedor';

    public function contraparte(): self
    {
        return $this === self::Cliente ? self::Proveedor : self::Cliente;
    }

    /** @return list<MotivoDisputa> los motivos que esa parte puede alegar. */
    public function motivos(): array
    {
        return $this === self::Cliente ? MotivoDisputa::delCliente() : MotivoDisputa::delProveedor();
    }
}
