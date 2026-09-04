<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Dominio\CausaPausa;
use App\Dominios\Operaciones\Dominio\Excepciones\PausaFinAnteriorAInicio;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Pausa;
use Illuminate\Support\Carbon;

/**
 * Alta de una pausa con causa atribuible (HU-44, tarea 58). Alta manual desde
 * el panel (ver docblock de la migración `ope_pausas`): sin idempotencia por
 * `uuid_cliente` que proteger, mismo criterio que `Finanzas/Aplicacion/CrearGasto`
 * — un alta humana del panel no se reintenta sola.
 *
 * `->utc()`: mismo mecanismo (y mismo fix) que `MaquinaEstadosSesion` aplica a
 * `ope_sesiones.inicio`/`fin` (columnas `dateTime` sin tz, tarea 29) — sin él,
 * `format()` escribiría la hora LOCAL literal del offset original.
 *
 * `duracion_minutos` se calcula acá, una sola vez, y se guarda (ver docblock
 * de la migración): el agregado por causa lo suma directo en SQL.
 */
final class RegistrarPausa
{
    public function ejecutar(int $sesionId, CausaPausa $causa, string $inicio, string $fin): Pausa
    {
        $inicioUtc = Carbon::parse($inicio)->utc();
        $finUtc = Carbon::parse($fin)->utc();

        if (! $finUtc->greaterThan($inicioUtc)) {
            throw new PausaFinAnteriorAInicio;
        }

        return Pausa::query()->create([
            'sesion_id' => $sesionId,
            'causa' => $causa,
            'inicio' => $inicioUtc,
            'fin' => $finUtc,
            'duracion_minutos' => $inicioUtc->diffInMinutes($finUtc),
        ]);
    }
}
