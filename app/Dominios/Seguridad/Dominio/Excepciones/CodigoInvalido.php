<?php

namespace App\Dominios\Seguridad\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * El código no sirve: no existe, está errado, venció, ya se usó o agotó sus intentos, o la cuenta no existe. La API
 * responde siempre el mismo `422 codigo_invalido`, para que nada revele cuál de esos casos fue.
 */
final class CodigoInvalido extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(Texto::de('seguridad.codigos.codigo_invalido'));
    }

    /** @return array{message: string, codigo: string} */
    public function cuerpo(): array
    {
        return ['message' => $this->getMessage(), 'codigo' => 'codigo_invalido'];
    }
}
