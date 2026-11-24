<?php

namespace Database\Seeders\Demo;

use App\Dominios\Asignacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Mantiene la demo viva al volver a sembrar: las ofertas pendientes recuperan
 * una cuenta regresiva vigente y las solicitudes que siguen abiertas recuperan
 * una ventana de fechas futura. Todo es relativo a `now()`, así que correr
 * `bin/demo seed` días después deja el mismo recorrido que el primer día.
 *
 * Lo ya terminado (completado, cancelado, en disputa) no se toca.
 */
class RenovarVigenciaDemoSeeder extends Seeder
{
    /** Horas de cuenta regresiva que recupera cada oferta pendiente. */
    public const HORAS_DE_OFERTA = 3;

    public function run(): void
    {
        $ahora = Carbon::now();

        Oferta::query()
            ->where('estado', 'ofrecida')
            ->update([
                'ofrecida_en' => $ahora->copy()->subMinutes(5),
                'vence_en' => $ahora->copy()->addHours(self::HORAS_DE_OFERTA),
            ]);

        // Lo que está ocurriendo hoy mantiene su ventana en torno a hoy; lo que aún no
        // empieza recupera una ventana futura. Solo se corrigen las ventanas caducadas.
        $enCurso = [EstadoSolicitud::EnCamino, EstadoSolicitud::EnEjecucion, EstadoSolicitud::SuspendidaPorClima];
        $pendientes = [EstadoSolicitud::Publicada, EstadoSolicitud::EnAsignacion, EstadoSolicitud::SinProveedor, EstadoSolicitud::Asignada];
        $valores = fn (array $estados): array => array_map(fn (EstadoSolicitud $e): string => $e->value, $estados);

        SolicitudServicio::query()
            ->whereIn('estado', $valores($enCurso))
            ->where('ventana_hasta', '<', $ahora->toDateString())
            ->update([
                'ventana_desde' => $ahora->copy()->subDay()->toDateString(),
                'ventana_hasta' => $ahora->copy()->addDays(2)->toDateString(),
            ]);

        SolicitudServicio::query()
            ->whereIn('estado', $valores($pendientes))
            ->where('ventana_hasta', '<', $ahora->toDateString())
            ->update([
                'ventana_desde' => $ahora->copy()->addDays(3)->toDateString(),
                'ventana_hasta' => $ahora->copy()->addDays(10)->toDateString(),
            ]);
    }
}
