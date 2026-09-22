<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Contratos\ModalidadPago;

/**
 * Lo que el formulario de tarifa entrega a `CrearTarifa` / `ActualizarTarifa`.
 * Montos como texto decimal ya validado (invariante 6).
 */
final readonly class DatosTarifa
{
    public function __construct(
        public string $nombre,
        public ModalidadPago $modalidad,
        public string $montoPiloto,
        public string $montoAuxiliar,
        public bool $predeterminada,
        public ?string $descripcion,
    ) {}

    /** @return array<string, mixed> */
    public function atributos(): array
    {
        return [
            'nombre' => $this->nombre,
            'modalidad' => $this->modalidad,
            'monto_piloto' => $this->montoPiloto,
            'monto_auxiliar' => $this->montoAuxiliar,
            'predeterminada' => $this->predeterminada,
            'descripcion' => $this->descripcion,
        ];
    }
}
