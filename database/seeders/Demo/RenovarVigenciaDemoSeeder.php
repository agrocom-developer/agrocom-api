<?php

namespace Database\Seeders\Demo;

use App\Dominios\Negociacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Mantiene la demo viva al volver a sembrar: las ofertas vigentes recuperan sus horas de plazo y
 * las fechas deseadas ya pasadas vuelven a quedar hacia adelante. La solicitud no tiene vigencia,
 * así que no hay nada que renovar en ella. Todo es relativo a `now()`, así que correr `bin/demo seed` días
 * después deja el mismo recorrido que el primer día.
 *
 * Lo ya terminado (finalizado, calificado, cancelado, en reclamo) no se toca.
 */
class RenovarVigenciaDemoSeeder extends Seeder
{
    /** Horas de plazo que recupera cada oferta vigente. */
    public const HORAS_DE_OFERTA = 24;

    public function run(): void
    {
        $ahora = Carbon::now();

        Oferta::query()
            ->where('estado', 'vigente')
            ->where('vence_en', '<', $ahora->copy()->addHours(self::HORAS_DE_OFERTA))
            ->update(['vence_en' => $ahora->copy()->addHours(self::HORAS_DE_OFERTA)]);

        // Lo que está por ejecutarse o en curso mantiene su ventana en torno a hoy.
        $enCurso = array_map(fn (EstadoSolicitud $e): string => $e->value, [EstadoSolicitud::Abierta, EstadoSolicitud::ConOfertas, EstadoSolicitud::Adjudicada, EstadoSolicitud::EnEjecucion]);

        SolicitudServicio::query()
            ->whereIn('estado', $enCurso)
            ->where('ventana_hasta', '<', $ahora->toDateString())
            ->update([
                'ventana_desde' => $ahora->copy()->subDay()->toDateString(),
                'ventana_hasta' => $ahora->copy()->addDays(6)->toDateString(),
            ]);
    }
}
