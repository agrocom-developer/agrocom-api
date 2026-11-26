<?php

namespace App\Dominios\Seguridad\Contratos;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * El teléfono o el documento ya tienen una cuenta DE ESTE TIPO (ADR 0046, puntos 9 y 26). La app
 * responde `409 cuenta_ya_registrada` con el tipo de cuenta y nada más: ni qué dato chocó, ni
 * datos de nadie, ni si la cuenta está completa. La persona inicia sesión o recupera su acceso.
 */
final class CuentaYaRegistrada extends RuntimeException
{
    public function __construct(public readonly TipoCuenta $tipoCuenta)
    {
        parent::__construct(Texto::de('seguridad.errores.cuenta_ya_registrada', [
            'tipo' => Texto::de('seguridad.tipos_de_cuenta.'.$tipoCuenta->value),
        ]));
    }

    /** El cuerpo de la respuesta `409`, igual para el teléfono y para el documento. */
    public function cuerpo(): array
    {
        return [
            'message' => $this->getMessage(),
            'codigo' => 'cuenta_ya_registrada',
            'tipo_cuenta' => $this->tipoCuenta->value,
        ];
    }
}
