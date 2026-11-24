<?php

namespace App\Dominios\Disputas\Aplicacion;

use App\Dominios\Disputas\Aplicacion\MaquinaEstados\MaquinaEstadosDisputa;
use App\Dominios\Disputas\Contratos\Eventos\DisputaAbierta;
use App\Dominios\Disputas\Dominio\Excepciones\DisputaInvalida;
use App\Dominios\Disputas\Dominio\MotivoDisputa;
use App\Dominios\Disputas\Dominio\ParteDeDisputa;
use App\Dominios\Disputas\Dominio\PlazosDeDisputa;
use App\Dominios\Disputas\Infraestructura\Eloquent\Disputa;
use App\Dominios\Solicitudes\Contratos\DatosServicio;
use App\Dominios\Solicitudes\Contratos\LecturaServicios;
use App\Dominios\Solicitudes\Contratos\ResolucionDeServicio;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Una de las partes abre la disputa de un servicio (`POST /solicitudes/{id}/disputas`,
 * especificación §10.1, política de disputas, «Cuándo se abre»). Una por solicitud.
 *
 * - El cliente, desde `ejecutada` hasta 72 horas después (la conformidad tácita
 *   cierra el plazo); la solicitud pasa a `en_disputa`.
 * - El proveedor, por un cobro pendiente, por acceso negado al lote o por una
 *   calificación abusiva, mientras el servicio está en un estado que lo admita y
 *   dentro de su ventana. Si el servicio está `ejecutada`, también pasa a
 *   `en_disputa`; en otro estado la disputa convive con la solicitud sin moverla.
 *
 * La contraparte tiene 48 horas para responder. Parte del servicio de la cuenta
 * (invariante 5 de CLAUDE.md): uno ajeno responde 404.
 */
final class AbrirDisputa
{
    public function __construct(
        private readonly LecturaServicios $servicios,
        private readonly ResolucionDeServicio $resolucion,
        private readonly MaquinaEstadosDisputa $estados,
        private readonly ParticipanteDeDisputa $participantes,
        private readonly ParametrosDeDisputa $parametros,
    ) {}

    /** @throws DisputaInvalida */
    public function ejecutar(int $usuarioId, ParteDeDisputa $parte, int $solicitudId, string $motivo, string $detalle, ?string $montoEnDisputa = null): Disputa
    {
        $proveedorId = $this->participantes->proveedorDe($usuarioId, $parte);
        $servicio = $this->servicios->datos($solicitudId);

        if ($servicio === null || ! $this->esDelServicio($servicio, $parte, $usuarioId, $proveedorId)) {
            throw DisputaInvalida::servicioAjeno();
        }

        $motivoDisputa = MotivoDisputa::tryFrom($motivo);

        if ($motivoDisputa === null || ! in_array($motivoDisputa, $parte->motivos(), true)) {
            throw DisputaInvalida::motivoInvalido($motivo);
        }

        $detalle = trim($detalle);

        if ($detalle === '') {
            throw DisputaInvalida::textoRequerido();
        }

        if (Disputa::query()->where('solicitud_id', $servicio->id)->exists()) {
            throw DisputaInvalida::yaExiste();
        }

        $ahora = new DateTimeImmutable;
        $this->exigirDisputable($servicio, $parte, $motivoDisputa, $ahora);

        $monto = $this->monto($servicio, $montoEnDisputa);

        try {
            return DB::transaction(function () use ($servicio, $parte, $usuarioId, $motivoDisputa, $detalle, $monto, $ahora): Disputa {
                $disputa = $this->estados->crear([
                    'solicitud_id' => $servicio->id,
                    'cliente_id' => $servicio->clienteId,
                    'cliente_usuario_id' => $servicio->clienteUsuarioId,
                    'proveedor_id' => $servicio->proveedorId,
                    'abierta_por_rol' => $parte->value,
                    'abierta_por_usuario_id' => $usuarioId,
                    'motivo' => $motivoDisputa->value,
                    'detalle' => mb_substr($detalle, 0, $this->parametros->textoMax()),
                    'monto_en_disputa' => $monto,
                    'responde_antes_de' => PlazosDeDisputa::respondeAntesDe($ahora, $this->parametros->respuestaHoras()),
                    'movio_solicitud' => false,
                ], $usuarioId, $parte->value);

                $movio = $this->resolucion->abrirDisputa($servicio->id, $parte->value, $usuarioId, $motivoDisputa->value);

                if ($movio) {
                    $disputa->forceFill(['movio_solicitud' => true])->save();
                } elseif ($parte === ParteDeDisputa::Cliente) {
                    // El cliente solo disputa lo `ejecutada`: si la solicitud ya no lo estaba (la
                    // conformidad tácita corrió entre la lectura y el paso), el plazo se cerró.
                    throw DisputaInvalida::plazoVencido();
                }

                event(new DisputaAbierta($disputa->id, $servicio->id, $parte->value, $motivoDisputa->value, $this->participantes->usuariosDe($disputa, $parte->contraparte())));

                return $disputa;
            });
        } catch (UniqueConstraintViolationException) {
            throw DisputaInvalida::yaExiste();
        }
    }

    private function esDelServicio(DatosServicio $servicio, ParteDeDisputa $parte, int $usuarioId, ?int $proveedorId): bool
    {
        return $parte === ParteDeDisputa::Cliente
            ? $servicio->clienteUsuarioId === $usuarioId
            : ($proveedorId !== null && $servicio->proveedorId === $proveedorId);
    }

    /** @throws DisputaInvalida */
    private function exigirDisputable(DatosServicio $servicio, ParteDeDisputa $parte, MotivoDisputa $motivo, DateTimeImmutable $ahora): void
    {
        if ($parte === ParteDeDisputa::Cliente) {
            if (! in_array($servicio->estado, ['finalizada', 'calificada'], true) || $servicio->finalizadaEn === null) {
                throw DisputaInvalida::servicioNoDisputable($servicio->estado);
            }

            if (! PlazosDeDisputa::clientePuedeAbrir(new DateTimeImmutable($servicio->finalizadaEn), $ahora, $this->parametros->aperturaClienteHoras())) {
                throw DisputaInvalida::plazoVencido();
            }

            return;
        }

        if (! in_array($servicio->estado, $motivo->estadosDeSolicitudDelProveedor(), true)) {
            throw DisputaInvalida::servicioNoDisputable($servicio->estado);
        }

        $desde = $servicio->finalizadaEn;

        if (! PlazosDeDisputa::proveedorPuedeAbrir($desde === null ? null : new DateTimeImmutable($desde), $ahora, $this->parametros->aperturaProveedorDias())) {
            throw DisputaInvalida::plazoVencido();
        }
    }

    /** El monto en disputa: lo pedido, que no puede pasar del monto del servicio, o todo el monto. */
    private function monto(DatosServicio $servicio, ?string $pedido): string
    {
        $referencia = BigDecimal::of($servicio->montoDeReferencia())->toScale(2, RoundingMode::HalfUp);

        if ($referencia->isLessThanOrEqualTo(0)) {
            throw DisputaInvalida::montoInvalido((string) $referencia);
        }

        if ($pedido === null || trim($pedido) === '') {
            return (string) $referencia;
        }

        $monto = BigDecimal::of($pedido)->toScale(2, RoundingMode::HalfUp);

        if ($monto->isLessThanOrEqualTo(0) || $monto->isGreaterThan($referencia)) {
            throw DisputaInvalida::montoInvalido((string) $referencia);
        }

        return (string) $monto;
    }
}
