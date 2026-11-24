<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Compartido\Dominio\MontoDelServicio;
use App\Dominios\Solicitudes\Aplicacion\MaquinaEstados\MaquinaEstadosSolicitud;
use App\Dominios\Solicitudes\Contratos\DatosSolicitudNegociable;
use App\Dominios\Solicitudes\Contratos\PasosDeSolicitud;
use App\Dominios\Solicitudes\Contratos\SolicitudNoNegociable;
use App\Dominios\Solicitudes\Dominio\ActorSolicitud;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\Excepciones\TransicionSolicitudNoPermitida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Implementa {@see PasosDeSolicitud} sobre {@see MaquinaEstadosSolicitud}
 * (invariante 7 de CLAUDE.md): ningún `estado = ...` suelto, y cada paso deja su
 * fila de historial con origen, autor y motivo. Se llama dentro de la transacción
 * de quien decide; la máquina vuelve a bloquear la fila, así que dos decisiones
 * simultáneas no se pisan.
 */
final class PasosDeSolicitudPorMaquina implements PasosDeSolicitud
{
    public function __construct(private readonly MaquinaEstadosSolicitud $estados) {}

    public function datos(int $solicitudId, bool $bloquear = false): ?DatosSolicitudNegociable
    {
        $consulta = SolicitudServicio::query()->with(['cliente', 'terreno']);
        if ($bloquear) {
            $consulta->lockForUpdate();
        }

        $solicitud = $consulta->find($solicitudId);

        return $solicitud === null ? null : new DatosSolicitudNegociable(
            id: $solicitud->id,
            estado: $solicitud->estado->value,
            clienteId: $solicitud->cliente_id,
            clienteUsuarioId: $solicitud->cliente->usuario_id,
            proveedorId: $solicitud->proveedor_id,
            equipoId: $solicitud->equipo_id,
            ofertaAdjudicadaId: $solicitud->oferta_adjudicada_id,
            tipoAplicacion: $solicitud->tipo_aplicacion->value,
            hectareasSolicitadas: $solicitud->hectareas_solicitadas,
            precioHaPropuesto: $solicitud->precio_ha_propuesto,
            precioHaAcordado: $solicitud->precio_ha_acordado,
            montoEstimado: $solicitud->monto_estimado,
            montoFinal: $solicitud->monto_final,
            hectareasEjecutadas: $solicitud->hectareas_ejecutadas,
            porcentajeAvance: $solicitud->porcentaje_avance,
            venceEn: $solicitud->vence_en?->toIso8601String(),
            ventanaDesde: $solicitud->ventana_desde->toDateString(),
            ventanaHasta: $solicitud->ventana_hasta->toDateString(),
            zonaId: $solicitud->terreno->zona_id,
            finalizadaEn: $solicitud->finalizada_en?->toIso8601String(),
            cierreSolicitadoEn: $solicitud->cierre_solicitado_en?->toIso8601String(),
            ultimoAvanceEn: $solicitud->ultimo_avance_en?->toIso8601String(),
            iniciadaEn: $solicitud->iniciada_en?->toIso8601String(),
        );
    }

    public function marcarConOfertas(int $solicitudId): void
    {
        $solicitud = SolicitudServicio::query()->findOrFail($solicitudId);

        if ($solicitud->estado === EstadoSolicitud::Abierta) {
            $this->paso($solicitud, EstadoSolicitud::ConOfertas, ActorSolicitud::Sistema, null, null);
        }
    }

    public function reabrirSinOfertas(int $solicitudId): void
    {
        $solicitud = SolicitudServicio::query()->findOrFail($solicitudId);

        if ($solicitud->estado === EstadoSolicitud::ConOfertas) {
            $this->paso($solicitud, EstadoSolicitud::Abierta, ActorSolicitud::Sistema, null, null);
        }
    }

    public function adjudicar(int $solicitudId, int $proveedorId, int $equipoId, int $ofertaId, string $precioHaAcordado, string $origen, ?int $actorUsuarioId): void
    {
        $solicitud = SolicitudServicio::query()->findOrFail($solicitudId);

        $this->paso($solicitud, EstadoSolicitud::Adjudicada, ActorSolicitud::from($origen), $actorUsuarioId, null, [
            'proveedor_id' => $proveedorId,
            'equipo_id' => $equipoId,
            'oferta_adjudicada_id' => $ofertaId,
            'precio_ha_acordado' => $precioHaAcordado,
            'monto_estimado' => MontoDelServicio::monto($solicitud->hectareas_solicitadas, $precioHaAcordado),
            'adjudicada_en' => Carbon::now(),
        ]);
    }

    public function expirar(int $solicitudId): bool
    {
        $solicitud = SolicitudServicio::query()->find($solicitudId);

        if ($solicitud === null || ! $solicitud->estado->enMercado()) {
            return false;
        }

        try {
            $this->estados->transicionar($solicitud, EstadoSolicitud::Expirada, ActorSolicitud::Sistema, null, null, ['expirada_en' => Carbon::now()]);
        } catch (TransicionSolicitudNoPermitida) {
            return false;
        }

        return true;
    }

    public function iniciarEjecucion(int $solicitudId, ?int $actorUsuarioId, DateTimeInterface $en): void
    {
        $solicitud = SolicitudServicio::query()->findOrFail($solicitudId);

        $this->paso($solicitud, EstadoSolicitud::EnEjecucion, ActorSolicitud::Proveedor, $actorUsuarioId, null, ['iniciada_en' => Carbon::instance($en)]);
    }

    public function registrarAvance(int $solicitudId, string $hectareasEjecutadas, string $porcentaje, DateTimeInterface $en): void
    {
        SolicitudServicio::query()->whereKey($solicitudId)->where('estado', EstadoSolicitud::EnEjecucion->value)->update([
            'hectareas_ejecutadas' => $hectareasEjecutadas,
            'porcentaje_avance' => $porcentaje,
            'ultimo_avance_en' => Carbon::instance($en),
        ]);
    }

    public function marcarCierreSolicitado(int $solicitudId, string $hectareasEjecutadas, DateTimeInterface $en): void
    {
        SolicitudServicio::query()->whereKey($solicitudId)->where('estado', EstadoSolicitud::EnEjecucion->value)->update([
            'hectareas_ejecutadas' => $hectareasEjecutadas,
            'cierre_solicitado_en' => Carbon::instance($en),
        ]);
    }

    public function finalizar(int $solicitudId, string $hectareasEjecutadas, ?int $actorUsuarioId, DateTimeInterface $en): void
    {
        $solicitud = SolicitudServicio::query()->findOrFail($solicitudId);

        $this->paso($solicitud, EstadoSolicitud::Finalizada, ActorSolicitud::Proveedor, $actorUsuarioId, null, [
            'hectareas_ejecutadas' => $hectareasEjecutadas,
            'porcentaje_avance' => MontoDelServicio::porcentaje($hectareasEjecutadas, $solicitud->hectareas_solicitadas),
            'monto_final' => MontoDelServicio::monto($hectareasEjecutadas, (string) $solicitud->precio_ha_acordado),
            'finalizada_en' => Carbon::instance($en),
        ]);
    }

    public function devolverAbierta(int $solicitudId, ?int $actorUsuarioId, string $motivo): void
    {
        $solicitud = SolicitudServicio::query()->findOrFail($solicitudId);

        $this->paso($solicitud, EstadoSolicitud::Abierta, ActorSolicitud::Proveedor, $actorUsuarioId, $motivo, [
            'proveedor_id' => null,
            'equipo_id' => null,
            'oferta_adjudicada_id' => null,
            'precio_ha_acordado' => null,
            'adjudicada_en' => null,
            'vence_en' => Carbon::now()->addDays((int) config('negociacion.vigencia_solicitud_dias', 7)),
        ]);
    }

    public function equipoOcupado(int $equipoId, string $ventanaDesde, string $ventanaHasta, int $exceptoSolicitudId): bool
    {
        return SolicitudServicio::query()
            ->where('equipo_id', $equipoId)
            ->whereKeyNot($exceptoSolicitudId)
            ->whereIn('estado', [EstadoSolicitud::Adjudicada->value, EstadoSolicitud::EnEjecucion->value])
            ->whereDate('ventana_desde', '<=', $ventanaHasta)
            ->whereDate('ventana_hasta', '>=', $ventanaDesde)
            ->exists();
    }

    /** @param  array<string, mixed>  $cambios */
    private function paso(SolicitudServicio $solicitud, EstadoSolicitud $hacia, ActorSolicitud $origen, ?int $actorUsuarioId, ?string $motivo, array $cambios = []): void
    {
        try {
            $this->estados->transicionar($solicitud, $hacia, $origen, $actorUsuarioId, $motivo, $cambios);
        } catch (TransicionSolicitudNoPermitida) {
            throw SolicitudNoNegociable::enEstado($solicitud->fresh()?->estado->value ?? $solicitud->estado->value, $hacia->value);
        }
    }
}
