<?php

namespace App\Dominios\Comercial\Aplicacion\Lote;

/** Lo que hizo una edición en bloque: cuántos lotes cambiaron, cuántos nacieron y cuántos se dieron de baja. */
final readonly class ResultadoEdicionEnBloque
{
    public function __construct(
        public int $actualizados,
        public int $creados,
        public int $eliminados,
    ) {}

    public function huboCambios(): bool
    {
        return $this->actualizados + $this->creados + $this->eliminados > 0;
    }
}
