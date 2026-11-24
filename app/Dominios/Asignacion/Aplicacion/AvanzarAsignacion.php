<?php

namespace App\Dominios\Asignacion\Aplicacion;

use App\Dominios\Asignacion\Aplicacion\MaquinaEstados\MaquinaEstadosOferta;
use App\Dominios\Asignacion\Contratos\Eventos\OfertaCreada;
use App\Dominios\Asignacion\Contratos\Eventos\SolicitudSinProveedor;
use App\Dominios\Asignacion\Dominio\GrupoOferta;
use App\Dominios\Asignacion\Dominio\OrigenOferta;
use App\Dominios\Asignacion\Dominio\RondaPlanificada;
use App\Dominios\Asignacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Asignacion\Infraestructura\Eloquent\Ronda;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Solicitudes\Contratos\AsignacionDeSolicitud;
use App\Dominios\Solicitudes\Contratos\DatosSolicitudParaAsignar;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * El corazón del motor (ADR 0030): abre la ronda que sigue y, si una ronda no
 * tiene a quién ofrecer, pasa a la siguiente en el acto. Cuando el plan se
 * agota sin candidatos, la solicitud pasa a `sin_proveedor` y se avisa al
 * cliente. Cada ronda queda en `asg_rondas`, incluso las vacías, para las
 * métricas del mercado.
 *
 * Idempotente: una ronda que ya existe para `(solicitud, ciclo, ronda)` no se
 * vuelve a abrir, así que reintentar tras un fallo no duplica ofertas. Quien la
 * llama ya tiene bloqueada la fila de la solicitud.
 */
final class AvanzarAsignacion
{
    public function __construct(
        private readonly SeleccionDeCandidatos $seleccion,
        private readonly MaquinaEstadosOferta $ofertas,
        private readonly ParametrosDeAsignacion $parametros,
        private readonly LecturaProveedores $proveedores,
        private readonly AsignacionDeSolicitud $asignacion,
    ) {}

    public function ejecutar(DatosSolicitudParaAsignar $solicitud, int $ciclo, int $desdeRonda): void
    {
        DB::transaction(function () use ($solicitud, $ciclo, $desdeRonda): void {
            $plan = $this->parametros->plan();

            for ($numero = max(1, $desdeRonda); ; $numero++) {
                $ronda = $plan->ronda($numero);

                if ($ronda === null) {
                    $this->cerrarSinProveedor($solicitud, $ciclo);

                    return;
                }

                $existente = Ronda::query()->where('solicitud_id', $solicitud->id)->where('ciclo', $ciclo)->where('ronda', $numero)->first();

                if ($existente !== null) {
                    if (Oferta::query()->where('solicitud_id', $solicitud->id)->where('ciclo', $ciclo)->where('ronda', $numero)->where('estado', 'ofrecida')->exists()) {
                        return;
                    }

                    continue;
                }

                $candidatos = $this->seleccion->ejecutar($solicitud, $ronda, $ciclo);

                $ahora = Carbon::now();

                Ronda::query()->create([
                    'solicitud_id' => $solicitud->id,
                    'ciclo' => $ciclo,
                    'ronda' => $numero,
                    'grupo' => $ronda->grupo->value,
                    'radio_km' => $ronda->radioKm,
                    'proveedores_ofrecidos' => count($candidatos),
                    'iniciada_en' => $ahora,
                    'cerrada_en' => $candidatos === [] ? $ahora : null,
                ]);

                if ($candidatos === []) {
                    continue;
                }

                $this->ofrecer($solicitud, $ciclo, $ronda, $candidatos, $ahora);

                return;
            }
        });
    }

    /** @param  list<Candidato>  $candidatos */
    private function ofrecer(DatosSolicitudParaAsignar $solicitud, int $ciclo, RondaPlanificada $ronda, array $candidatos, Carbon $ahora): void
    {
        $vence = $ahora->copy()->addMinutes($this->parametros->ventanaOfertaMinutos($solicitud->urgente));

        foreach ($candidatos as $candidato) {
            $oferta = $this->ofertas->ofrecer([
                'solicitud_id' => $solicitud->id,
                'proveedor_id' => $candidato->proveedorId,
                'ciclo' => $ciclo,
                'ronda' => $ronda->ronda,
                'grupo' => GrupoOferta::deTipoDeProveedor($candidato->tipo)->value,
                'origen' => OrigenOferta::Motor->value,
                'ofrecida_en' => $ahora,
                'vence_en' => $vence,
                'pago_estimado_proveedor' => $this->pagoEstimado($solicitud->montoEstimado, $candidato->tipo),
                'distancia_km' => $candidato->distanciaKm,
                'puntuacion' => $candidato->puntuacion,
            ]);

            event(new OfertaCreada(
                $oferta->id,
                $solicitud->id,
                $candidato->proveedorId,
                $this->proveedores->usuariosDeAviso($candidato->proveedorId),
                $solicitud->hectareas,
                $vence->toIso8601String(),
                $solicitud->urgente,
                $ronda->ronda,
                $ronda->grupo->value,
            ));
        }
    }

    /**
     * Lo que el proveedor cobra por el servicio: el monto estimado menos la
     * comisión, que es cero para un proveedor propio (política de comisión).
     */
    public function pagoEstimado(?string $montoEstimado, string $tipoDeProveedor): ?string
    {
        if ($montoEstimado === null) {
            return null;
        }

        $monto = BigDecimal::of($montoEstimado);

        if ($tipoDeProveedor === 'propio') {
            return (string) $monto->toScale(2, RoundingMode::HalfUp);
        }

        $comision = $monto->multipliedBy($this->parametros->comisionExternoPorcentaje())->dividedBy(100, 2, RoundingMode::HalfUp);

        return (string) $monto->minus($comision)->toScale(2, RoundingMode::HalfUp);
    }

    private function cerrarSinProveedor(DatosSolicitudParaAsignar $solicitud, int $ciclo): void
    {
        $this->asignacion->marcarSinProveedor($solicitud->id);

        event(new SolicitudSinProveedor($solicitud->id, $ciclo, $solicitud->clienteUsuarioId));
    }
}
