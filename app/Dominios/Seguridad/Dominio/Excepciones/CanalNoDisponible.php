<?php

namespace App\Dominios\Seguridad\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Seguridad\Contratos\CanalDeCodigo;
use RuntimeException;

/** El canal pedido no tiene adaptador o no está configurado: la API responde `503 canal_no_disponible`. */
final class CanalNoDisponible extends RuntimeException
{
    public function __construct(public readonly CanalDeCodigo $canal)
    {
        parent::__construct(Texto::de('seguridad.codigos.canal_no_disponible'));
    }

    /** @return array{message: string, codigo: string, canal: string} */
    public function cuerpo(): array
    {
        return ['message' => $this->getMessage(), 'codigo' => 'canal_no_disponible', 'canal' => $this->canal->value];
    }
}
