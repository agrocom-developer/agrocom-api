<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Personal\Dominio\RolOperativoPersona;

/**
 * Lo que se carga de una persona de campo en el alta y en la edición
 * (21/9/2026): datos personales, datos de referencia y su trabajo en campo.
 * Mismo objeto para `CrearPersona` y `ActualizarPersona`, que ya no reciben
 * una docena de parámetros sueltos.
 */
final readonly class DatosPersona
{
    public function __construct(
        public string $nombres,
        public string $apellidoPaterno,
        public ?string $apellidoMaterno,
        public string $ci,
        public string $celular,
        public ?string $correo,
        public ?string $direccion,
        public RolOperativoPersona $rol,
        public ?int $baseId,
        public ?string $tarifaHa,
        public bool $activo,
    ) {}

    /**
     * Nombre completo, como lo muestra el resto del sistema
     * (`per_personas.nombre`): nombres, apellido paterno y, si tiene, materno.
     */
    public function nombreCompleto(): string
    {
        return implode(' ', array_filter(
            [$this->nombres, $this->apellidoPaterno, $this->apellidoMaterno],
            fn (?string $parte): bool => $parte !== null && $parte !== '',
        ));
    }

    /** @return array<string, mixed> */
    public function atributos(): array
    {
        return [
            'nombre' => $this->nombreCompleto(),
            'nombres' => $this->nombres,
            'apellido_paterno' => $this->apellidoPaterno,
            'apellido_materno' => $this->apellidoMaterno,
            'ci' => $this->ci,
            'celular' => $this->celular,
            'correo' => $this->correo,
            'direccion' => $this->direccion,
            'rol' => $this->rol,
            'base_id' => $this->baseId,
            'tarifa_ha' => $this->tarifaHa,
            'activo' => $this->activo,
        ];
    }
}
