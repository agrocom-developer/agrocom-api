<?php

namespace App\Dominios\Asignacion\Dominio;

/**
 * El plan de rondas de la asignación (ADR 0030, punto 1 y 2): la ronda 1 es de
 * propios, sin radio; de la ronda 2 en adelante son terceros e independientes,
 * con el radio de la lista que corresponda (50, 100 y 150 km por defecto). Al
 * agotarse la lista no hay más rondas y la solicitud pasa a `sin_proveedor`.
 * Pura: solo aritmética sobre la lista de radios.
 */
final class PlanDeRondas
{
    /** @param  list<string>  $radiosKm  de menor a mayor. */
    public function __construct(private readonly array $radiosKm) {}

    /** La ronda `$numero` (desde 1), o `null` si el plan ya se agotó. */
    public function ronda(int $numero): ?RondaPlanificada
    {
        if ($numero < 1) {
            return null;
        }

        $total = $this->total();

        if ($numero === 1) {
            return new RondaPlanificada(1, GrupoOferta::Propio, null, $numero === $total);
        }

        $radio = $this->radiosKm[$numero - 2] ?? null;

        return $radio === null ? null : new RondaPlanificada($numero, GrupoOferta::Tercero, $radio, $numero === $total);
    }

    /** Cantidad de rondas del plan: la de propios más una por radio. */
    public function total(): int
    {
        return 1 + count($this->radiosKm);
    }
}
