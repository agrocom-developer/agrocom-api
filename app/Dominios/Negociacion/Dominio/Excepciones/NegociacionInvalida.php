<?php

namespace App\Dominios\Negociacion\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Una regla de la negociación no se cumple. Lleva el código de máquina (`codigo`,
 * el que las apps traducen en pantalla), el estatus HTTP que le corresponde y, si
 * aplica, el campo que falló. Los textos viven en `lang/es/negociacion.php`.
 */
final class NegociacionInvalida extends RuntimeException
{
    /** @param  array<string, string|int>  $parametros */
    private function __construct(
        public readonly string $codigo,
        public readonly int $estatus,
        public readonly ?string $campo = null,
        array $parametros = [],
    ) {
        parent::__construct(Texto::de("negociacion.errores.{$codigo}", $parametros));
    }

    /** Un recurso de otra cuenta o que no existe: 404 (invariante 5 de CLAUDE.md). */
    public static function ajeno(string $codigo): self
    {
        return new self($codigo, 404);
    }

    public static function prohibido(string $codigo): self
    {
        return new self($codigo, 403);
    }

    /**
     * Estado que ya no admite la acción (carreras, vencimientos): 409.
     *
     * @param  array<string, string|int>  $parametros
     */
    public static function conflicto(string $codigo, array $parametros = []): self
    {
        return new self($codigo, 409, null, $parametros);
    }

    /**
     * Dato inválido: 422 con el campo.
     *
     * @param  array<string, string|int>  $parametros
     */
    public static function dato(string $codigo, ?string $campo = null, array $parametros = []): self
    {
        return new self($codigo, 422, $campo, $parametros);
    }
}
