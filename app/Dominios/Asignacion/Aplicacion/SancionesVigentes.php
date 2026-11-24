<?php

namespace App\Dominios\Asignacion\Aplicacion;

use App\Dominios\Asignacion\Dominio\TipoSancion;
use App\Dominios\Asignacion\Infraestructura\Eloquent\Sancion;
use Illuminate\Support\Carbon;

/** Qué proveedores no pueden recibir ofertas hoy por una sanción vigente (ADR 0038). */
final class SancionesVigentes
{
    /** @return list<int> */
    public function proveedoresSinOfertas(?Carbon $ahora = null): array
    {
        $ahora ??= Carbon::now();

        return array_values(array_unique(array_map('intval', Sancion::query()
            ->whereIn('tipo', [TipoSancion::RestriccionOfertas->value, TipoSancion::RevisionSuspension->value])
            ->whereNull('levantada_en')
            ->where(fn ($consulta) => $consulta->whereNull('hasta')->orWhere('hasta', '>', $ahora))
            ->pluck('proveedor_id')
            ->all())));
    }

    public function impideOfertas(int $proveedorId, ?Carbon $ahora = null): bool
    {
        return in_array($proveedorId, $this->proveedoresSinOfertas($ahora), true);
    }
}
