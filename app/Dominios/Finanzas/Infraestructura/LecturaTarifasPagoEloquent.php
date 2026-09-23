<?php

namespace App\Dominios\Finanzas\Infraestructura;

use App\Dominios\Finanzas\Contratos\LecturaTarifasPago;
use App\Dominios\Finanzas\Contratos\TarifaPago;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Tarifa;

final class LecturaTarifasPagoEloquent implements LecturaTarifasPago
{
    public function vigentes(): array
    {
        return Tarifa::query()
            ->orderByDesc('predeterminada')
            ->orderBy('nombre')
            ->get()
            ->map(fn (Tarifa $tarifa): TarifaPago => $tarifa->comoContrato())
            ->values()
            ->all();
    }

    public function porId(int $tarifaId): ?TarifaPago
    {
        return Tarifa::query()->find($tarifaId)?->comoContrato();
    }

    public function predeterminada(): ?TarifaPago
    {
        return Tarifa::query()->where('predeterminada', true)->first()?->comoContrato();
    }
}
