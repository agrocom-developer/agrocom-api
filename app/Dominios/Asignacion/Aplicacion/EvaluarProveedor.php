<?php

namespace App\Dominios\Asignacion\Aplicacion;

use App\Dominios\Asignacion\Contratos\Eventos\ProveedorSancionado;
use App\Dominios\Asignacion\Dominio\CausaSancion;
use App\Dominios\Asignacion\Dominio\HechosDeSancion;
use App\Dominios\Asignacion\Dominio\PoliticaDeSanciones;
use App\Dominios\Asignacion\Dominio\TasasDeProveedor;
use App\Dominios\Asignacion\Dominio\TipoSancion;
use App\Dominios\Asignacion\Infraestructura\Eloquent\CancelacionDeProveedor;
use App\Dominios\Asignacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Asignacion\Infraestructura\Eloquent\Sancion;
use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Proveedores\Contratos\ReputacionDeProveedores;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula las tasas de un proveedor y le aplica la política de sanciones
 * (política de calificación y de cancelación, ADR 0038). Se llama tras cada
 * respuesta, vencimiento y cancelación, y a diario para todos los aprobados
 * (así la calificación, que escribe otro módulo, también cuenta).
 *
 * Idempotente: una sanción equivalente ya vigente no se repite, así que
 * correrlo dos veces con los mismos hechos deja las mismas filas. Ninguna
 * sanción suspende ni da de baja la cuenta (ADR 0032): la reincidencia abre una
 * revisión que decide un administrador.
 */
final class EvaluarProveedor
{
    public function __construct(
        private readonly LecturaProveedores $proveedores,
        private readonly ReputacionDeProveedores $reputacion,
        private readonly ParametrosDeAsignacion $parametros,
    ) {}

    /** @return list<Sancion> las sanciones que se aplicaron en esta evaluación. */
    public function ejecutar(int $proveedorId, ?Carbon $ahora = null): array
    {
        $ahora ??= Carbon::now();
        $proveedor = $this->proveedores->proveedor($proveedorId);

        if ($proveedor === null) {
            return [];
        }

        return DB::transaction(function () use ($proveedor, $ahora): array {
            [$tasaAceptacion, $respondidas] = $this->tasaDeAceptacion($proveedor->id, $ahora);
            [$tasaCancelacion, $cancelacionesContadas] = $this->tasaDeCancelacion($proveedor->id, $ahora);

            $this->reputacion->actualizarTasas($proveedor->id, $tasaAceptacion, $tasaCancelacion);

            if (! $proveedor->estaAprobado()) {
                return [];
            }

            $hechos = $this->hechos($proveedor->id, $proveedor->calificacionPromedio, $proveedor->calificacionesConteo, $tasaAceptacion, $respondidas, $cancelacionesContadas, $ahora);

            $aplicadas = [];

            foreach (PoliticaDeSanciones::evaluar($hechos, $this->parametros->calificacion(), $this->parametros->cancelacion(), $this->parametros->aceptacion()) as $decision) {
                $sancion = Sancion::query()->create([
                    'proveedor_id' => $proveedor->id,
                    'tipo' => $decision->tipo->value,
                    'causa' => $decision->causa->value,
                    'motivo' => Texto::de('asignacion.sancion.motivo.'.$decision->causa->value, [
                        'cancelaciones' => $cancelacionesContadas,
                        'dias' => $this->parametros->ventanaCancelacionDias(),
                        'promedio' => $proveedor->calificacionPromedio,
                        'tasa' => (string) round((float) $tasaAceptacion * 100),
                    ]),
                    'desde' => $ahora,
                    'hasta' => $decision->diasDeVigencia === null ? null : $ahora->copy()->addDays($decision->diasDeVigencia),
                ]);

                event(new ProveedorSancionado(
                    $sancion->id,
                    $proveedor->id,
                    $proveedor->razonSocial,
                    $decision->tipo->value,
                    $decision->causa->value,
                    $this->proveedores->usuariosDeAviso($proveedor->id),
                ));

                $aplicadas[] = $sancion;
            }

            return $aplicadas;
        });
    }

    /** @return array{0: string, 1: int} tasa y ofertas respondidas en la ventana. */
    private function tasaDeAceptacion(int $proveedorId, Carbon $ahora): array
    {
        $desde = $ahora->copy()->subDays($this->parametros->ventanaAceptacionDias());

        $conteo = fn (string $estado): int => Oferta::query()
            ->where('proveedor_id', $proveedorId)
            ->where('origen', 'motor')
            ->where('estado', $estado)
            ->where('ofrecida_en', '>=', $desde)
            ->count();

        $aceptadas = $conteo('aceptada');
        $rechazadas = $conteo('rechazada');
        $vencidas = $conteo('vencida');

        return [TasasDeProveedor::aceptacion($aceptadas, $rechazadas, $vencidas), $aceptadas + $rechazadas + $vencidas];
    }

    /** @return array{0: string, 1: int} tasa y cancelaciones que cuentan en la ventana. */
    private function tasaDeCancelacion(int $proveedorId, Carbon $ahora): array
    {
        $desde = $ahora->copy()->subDays($this->parametros->ventanaCancelacionDias());

        $cancelaciones = CancelacionDeProveedor::query()->where('proveedor_id', $proveedorId)->where('cancelada_en', '>=', $desde)->get();

        $aceptadas = Oferta::query()
            ->where('proveedor_id', $proveedorId)
            ->where('estado', 'aceptada')
            ->where('respondida_en', '>=', $desde)
            ->count();

        // Un servicio aceptado y luego cancelado sigue siendo uno aceptado en la ventana.
        return [
            TasasDeProveedor::cancelacion((int) $cancelaciones->sum('peso'), max($aceptadas, $cancelaciones->count())),
            $cancelaciones->where('peso', '>', 0)->count(),
        ];
    }

    private function hechos(int $proveedorId, string $calificacion, int $conteo, string $tasaAceptacion, int $respondidas, int $cancelaciones, Carbon $ahora): HechosDeSancion
    {
        $sanciones = fn () => Sancion::query()->where('proveedor_id', $proveedorId);

        $vigenteDe = function (CausaSancion $causa, array $tipos) use ($sanciones, $ahora): bool {
            return $sanciones()
                ->where('causa', $causa->value)
                ->whereIn('tipo', array_map(fn (TipoSancion $t): string => $t->value, $tipos))
                ->whereNull('levantada_en')
                ->where(fn ($consulta) => $consulta->whereNull('hasta')->orWhere('hasta', '>', $ahora))
                ->exists();
        };

        $advertenciaDesde = fn (CausaSancion $causa, int $dias): bool => $sanciones()
            ->where('causa', $causa->value)
            ->where('tipo', TipoSancion::Advertencia->value)
            ->where('desde', '>=', $ahora->copy()->subDays($dias))
            ->exists();

        $ultimaAdvertenciaCalificacion = $sanciones()
            ->where('causa', CausaSancion::Calificacion->value)
            ->where('tipo', TipoSancion::Advertencia->value)
            ->max('desde');

        $restriccionReciente = $sanciones()
            ->where('causa', CausaSancion::Cancelaciones->value)
            ->whereIn('tipo', [TipoSancion::RestriccionOfertas->value, TipoSancion::RevisionSuspension->value])
            ->where('desde', '>=', $ahora->copy()->subDays($this->parametros->reincidenciaDias()))
            ->exists();

        $restringe = [TipoSancion::RestriccionOfertas, TipoSancion::RevisionSuspension];

        return new HechosDeSancion(
            cancelacionesEnVentana: $cancelaciones,
            restriccionPorCancelacionesVigente: $vigenteDe(CausaSancion::Cancelaciones, $restringe),
            advertenciaPorCancelacionesVigente: $advertenciaDesde(CausaSancion::Cancelaciones, $this->parametros->ventanaCancelacionDias()),
            hayRestriccionPorCancelacionesReciente: $restriccionReciente,
            calificacionPromedio: $calificacion,
            calificacionesConteo: $conteo,
            restriccionPorCalificacionVigente: $vigenteDe(CausaSancion::Calificacion, $restringe),
            advertenciaPorCalificacionVigente: $advertenciaDesde(CausaSancion::Calificacion, $this->parametros->calificacion()['plazo_mejora_dias']),
            diasDesdeAdvertenciaDeCalificacion: $ultimaAdvertenciaCalificacion === null ? null : (int) Carbon::parse($ultimaAdvertenciaCalificacion)->diffInDays($ahora, true),
            tasaAceptacion: $tasaAceptacion,
            ofertasRespondidasEnVentana: $respondidas,
            advertenciaPorAceptacionVigente: $advertenciaDesde(CausaSancion::Aceptacion, $this->parametros->ventanaAceptacionDias()),
        );
    }
}
