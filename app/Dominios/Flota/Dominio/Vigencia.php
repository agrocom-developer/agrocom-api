<?php

namespace App\Dominios\Flota\Dominio;

/**
 * Vigencia de un equipo: un rango de fechas con inicio y fin opcional (`null`
 * = sin fin). Pura. Sirve para la regla «un piloto y un dron no pueden estar
 * en dos equipos vigentes a la vez» (especificación §4.4): dos vigencias se
 * solapan si comparten al menos un día.
 */
final readonly class Vigencia
{
    /**
     * @param  string  $desde  `Y-m-d`
     * @param  string|null  $hasta  `Y-m-d`; `null` = sin fin
     */
    public function __construct(public string $desde, public ?string $hasta = null) {}

    public function solapaCon(self $otra): bool
    {
        $empiezaAntesDeQueTermineLaOtra = $otra->hasta === null || $this->desde <= $otra->hasta;
        $terminaDespuesDeQueEmpieceLaOtra = $this->hasta === null || $this->hasta >= $otra->desde;

        return $empiezaAntesDeQueTermineLaOtra && $terminaDespuesDeQueEmpieceLaOtra;
    }

    public function estaVigenteEl(string $fecha): bool
    {
        return $this->desde <= $fecha && ($this->hasta === null || $this->hasta >= $fecha);
    }

    public function esValida(): bool
    {
        return $this->hasta === null || $this->hasta >= $this->desde;
    }
}
