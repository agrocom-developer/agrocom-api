<?php

namespace App\Dominios\Finanzas\Contratos;

/**
 * Una tarifa del catálogo de Finanzas (`fin_tarifas`), tal como la ve otro
 * módulo: Operaciones la ofrece en el alta de la Orden de Trabajo y copia sus
 * montos a la condición de pago del equipo.
 */
final readonly class TarifaPago
{
    public function __construct(
        public int $id,
        public string $nombre,
        public ModalidadPago $modalidad,
        public string $montoPiloto,
        public string $montoAuxiliar,
        public bool $predeterminada,
    ) {}

    public function comoCondicion(): CondicionPago
    {
        return new CondicionPago(
            modalidad: $this->modalidad,
            montoPiloto: $this->montoPiloto,
            montoAuxiliar: $this->montoAuxiliar,
            tarifaId: $this->id,
        );
    }
}
