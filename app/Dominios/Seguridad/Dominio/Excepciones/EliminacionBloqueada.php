<?php

namespace App\Dominios\Seguridad\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/** Algo impide eliminar la cuenta ahora (ADR 0047, punto 3): `409 eliminacion_bloqueada` con la lista de `motivos`. */
final class EliminacionBloqueada extends RuntimeException
{
    /** @param  list<string>  $motivos */
    public function __construct(public readonly array $motivos)
    {
        parent::__construct(Texto::de('seguridad.eliminacion.bloqueada'));
    }

    /** @return array{message: string, codigo: string, motivos: list<string>} */
    public function cuerpo(): array
    {
        return ['message' => $this->getMessage(), 'codigo' => 'eliminacion_bloqueada', 'motivos' => $this->motivos];
    }
}
