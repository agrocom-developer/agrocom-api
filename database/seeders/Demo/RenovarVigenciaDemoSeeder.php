<?php

namespace Database\Seeders\Demo;

use App\Dominios\Negociacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Mantiene la demo viva al volver a sembrar: las solicitudes que siguen abiertas recuperan su
 * vigencia, las ofertas vigentes recuperan sus horas de plazo y las ventanas de fechas caducadas
 * vuelven a quedar hacia adelante. Todo es relativo a `now()`, así que correr `bin/demo seed` días
 * después deja el mismo recorrido que el primer día.
 *
 * Lo ya terminado (finalizado, calificado, cancelado, expirado, disputado) no se toca.
 */
class RenovarVigenciaDemoSeeder extends Seeder
{
    /** Horas de plazo que recupera cada oferta vigente. */
    public const HORAS_DE_OFERTA = 24;

    /** Días de vigencia que recupera cada solicitud abierta. */
    public const DIAS_DE_SOLICITUD = 5;

    public function run(): void
    {
        $ahora = Carbon::now();

        $abiertas = array_map(fn (EstadoSolicitud $e): string => $e->value, [EstadoSolicitud::Abierta, EstadoSolicitud::ConOfertas]);

        SolicitudServicio::query()
            ->whereIn('estado', $abiertas)
            ->where('vence_en', '<', $ahora->copy()->addDays(2))
            ->update(['vence_en' => $ahora->copy()->addDays(self::DIAS_DE_SOLICITUD)]);

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
