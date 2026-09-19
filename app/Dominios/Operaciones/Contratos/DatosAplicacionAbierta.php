<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * La aplicación que sigue en curso de un contrato (ADR 0022): la única orden
 * abierta que le queda —`emitida`, `vigente` o `pausada`—, con lo justo para
 * decirle al usuario cuál es y llevarlo a ella. Comercial la usa para explicar
 * por qué el contrato todavía no se puede cancelar ni finalizar, sin importar
 * el modelo Eloquent de la orden (ADR 0003, regla 2).
 *
 * `estadoEtiqueta` ya viene traducido: el vocabulario de la orden es de
 * Operaciones, quien la lee no lo replica.
 */
final readonly class DatosAplicacionAbierta
{
    public function __construct(
        public int $ordenId,
        public int $contratoId,
        public int $nroAplicacion,
        public string $estado,
        public string $estadoEtiqueta,
    ) {}
}
