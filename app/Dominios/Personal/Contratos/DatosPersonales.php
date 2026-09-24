<?php

namespace App\Dominios\Personal\Contratos;

/**
 * Los datos de una persona tal como ella misma los ve en su perfil: todo lo
 * que Personal guarda de ella, ya sin modelos Eloquent (ADR 0003, regla 2).
 * Lo que no tiene cargado viaja como `null`, no como cadena vacía: quien lo
 * pinta decide qué dice.
 */
final readonly class DatosPersonales
{
    public function __construct(
        public int $id,
        public string $nombre,
        public string $rol,
        public ?string $baseNombre,
        public ?string $ci,
        public ?string $celular,
        public ?string $correo,
        public ?string $direccion,
    ) {}
}
