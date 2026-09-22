<?php

namespace App\Dominios\Finanzas\Contratos;

/**
 * Condición de pago congelada para un trabajo: la modalidad y cuánto cobra
 * cada puesto de la cuadrilla. La arma Operaciones al crear la Orden de
 * Trabajo (copiando una tarifa o lo negociado) y la devuelve con la sesión
 * validada; Finanzas la usa para calcular el devengo sin releer ninguna
 * tabla ajena. Montos como texto decimal (invariante 6: nunca `float`).
 */
final readonly class CondicionPago
{
    public function __construct(
        public ModalidadPago $modalidad,
        public string $montoPiloto,
        public string $montoAuxiliar,
        public ?int $tarifaId = null,
        public bool $negociada = false,
    ) {}
}
