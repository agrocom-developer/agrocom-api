<?php

namespace App\Dominios\Compartido\Dominio\Excepciones;

use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * La cuenta existe pero su estado de aprobación no permite la acción (ADR 0046, punto 12): publicar,
 * aceptar o contraofertar (cliente) y ofertar, contraofertar o aceptar (proveedor) exigen `aprobado`.
 * La API responde `403` con `codigo: cuenta_no_aprobada`, el estado y lo que falta, y la app lo muestra
 * como la pantalla de verificación, no como un error genérico. Es distinto de `proveedor_no_habilitado`
 * (falta un equipo apto). La decide el servidor: la app no replica la regla.
 */
final class CuentaNoAprobada extends RuntimeException
{
    public const CODIGO = 'cuenta_no_aprobada';

    /** @param  list<string>  $falta  Qué impide aprobar la cuenta (`verificacion`, `documentos`…); se completa fase a fase. */
    public function __construct(public readonly EstadoAprobacion $estado, public readonly array $falta = [])
    {
        parent::__construct(Texto::de('aprobacion.errores.cuenta_no_aprobada_'.$estado->value));
    }

    /** @return array{message: string, codigo: string, estado: string, falta: list<string>} */
    public function cuerpo(): array
    {
        return [
            'message' => $this->getMessage(),
            'codigo' => self::CODIGO,
            'estado' => $this->estado->value,
            'falta' => $this->falta,
        ];
    }
}
