<?php

namespace App\Dominios\Reclamos\Aplicacion;

use App\Dominios\Reclamos\Aplicacion\MaquinaEstados\MaquinaEstadosReclamo;
use App\Dominios\Reclamos\Contratos\Eventos\ReclamoAbierto;
use App\Dominios\Reclamos\Dominio\Excepciones\ReclamoInvalido;
use App\Dominios\Reclamos\Dominio\MotivoReclamo;
use App\Dominios\Reclamos\Dominio\ParteDeReclamo;
use App\Dominios\Reclamos\Dominio\PlazosDeReclamo;
use App\Dominios\Reclamos\Infraestructura\Eloquent\Reclamo;
use App\Dominios\Solicitudes\Contratos\DatosServicio;
use App\Dominios\Solicitudes\Contratos\LecturaServicios;
use App\Dominios\Solicitudes\Contratos\ResolucionDeServicio;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Una de las partes abre el reclamo de un servicio (`POST /solicitudes/{id}/reclamos`,
 * especificación §10.1, política de reclamos, «Cuándo se abre»). Una por solicitud.
 *
 * - El cliente, desde `ejecutada` hasta 72 horas después (la conformidad tácita
 *   cierra el plazo); la solicitud pasa a `en_reclamo`.
 * - El proveedor, por un cobro pendiente, por acceso negado al lote o por una
 *   calificación abusiva, mientras el servicio está en un estado que lo admita y
 *   dentro de su ventana. Si el servicio está `ejecutada`, también pasa a
 *   `en_reclamo`; en otro estado el reclamo convive con la solicitud sin moverla.
 *
 * La contraparte tiene 48 horas para responder. Parte del servicio de la cuenta
 * (invariante 5 de CLAUDE.md): uno ajeno responde 404.
 */
final class AbrirReclamo
{
    public function __construct(
        private readonly LecturaServicios $servicios,
        private readonly ResolucionDeServicio $resolucion,
        private readonly MaquinaEstadosReclamo $estados,
        private readonly ParticipanteDeReclamo $participantes,
        private readonly ParametrosDeReclamo $parametros,
    ) {}

    /** @throws ReclamoInvalido */
    public function ejecutar(int $usuarioId, ParteDeReclamo $parte, int $solicitudId, string $motivo, string $detalle, ?string $montoEnReclamo = null): Reclamo
    {
        $proveedorId = $this->participantes->proveedorDe($usuarioId, $parte);
        $servicio = $this->servicios->datos($solicitudId);

        if ($servicio === null || ! $this->esDelServicio($servicio, $parte, $usuarioId, $proveedorId)) {
            throw ReclamoInvalido::servicioAjeno();
        }

        $motivoReclamo = MotivoReclamo::tryFrom($motivo);

        if ($motivoReclamo === null || ! in_array($motivoReclamo, $parte->motivos(), true)) {
            throw ReclamoInvalido::motivoInvalido($motivo);
        }

        $detalle = trim($detalle);

        if ($detalle === '') {
            throw ReclamoInvalido::textoRequerido();
        }

        if (Reclamo::query()->where('solicitud_id', $servicio->id)->exists()) {
            throw ReclamoInvalido::yaExiste();
        }

        $ahora = new DateTimeImmutable;
        $this->exigirDeclamable($servicio, $parte, $motivoReclamo, $ahora);

        $monto = $this->monto($servicio, $montoEnReclamo);

        try {
            return DB::transaction(function () use ($servicio, $parte, $usuarioId, $motivoReclamo, $detalle, $monto, $ahora): Reclamo {
                $reclamo = $this->estados->crear([
                    'solicitud_id' => $servicio->id,
                    'cliente_id' => $servicio->clienteId,
                    'cliente_usuario_id' => $servicio->clienteUsuarioId,
                    'proveedor_id' => $servicio->proveedorId,
                    'abierta_por_rol' => $parte->value,
                    'abierta_por_usuario_id' => $usuarioId,
                    'motivo' => $motivoReclamo->value,
                    'detalle' => mb_substr($detalle, 0, $this->parametros->textoMax()),
                    'monto_en_reclamo' => $monto,
                    'responde_antes_de' => PlazosDeReclamo::respondeAntesDe($ahora, $this->parametros->respuestaHoras()),
                    'movio_solicitud' => false,
                ], $usuarioId, $parte->value);

                $movio = $this->resolucion->abrirReclamo($servicio->id, $parte->value, $usuarioId, $motivoReclamo->value);

                if ($movio) {
                    $reclamo->forceFill(['movio_solicitud' => true])->save();
                } elseif ($parte === ParteDeReclamo::Cliente) {
                    // El cliente solo reclamo lo `ejecutada`: si la solicitud ya no lo estaba (la
                    // conformidad tácita corrió entre la lectura y el paso), el plazo se cerró.
                    throw ReclamoInvalido::plazoVencido();
                }

                event(new ReclamoAbierto($reclamo->id, $servicio->id, $parte->value, $motivoReclamo->value, $this->participantes->usuariosDe($reclamo, $parte->contraparte())));

                return $reclamo;
            });
        } catch (UniqueConstraintViolationException) {
            throw ReclamoInvalido::yaExiste();
        }
    }

    private function esDelServicio(DatosServicio $servicio, ParteDeReclamo $parte, int $usuarioId, ?int $proveedorId): bool
    {
        return $parte === ParteDeReclamo::Cliente
            ? $servicio->clienteUsuarioId === $usuarioId
            : ($proveedorId !== null && $servicio->proveedorId === $proveedorId);
    }

    /** @throws ReclamoInvalido */
    private function exigirDeclamable(DatosServicio $servicio, ParteDeReclamo $parte, MotivoReclamo $motivo, DateTimeImmutable $ahora): void
    {
        if ($parte === ParteDeReclamo::Cliente) {
            if (! in_array($servicio->estado, ['finalizada', 'calificada'], true) || $servicio->finalizadaEn === null) {
                throw ReclamoInvalido::servicioNoDeclamable($servicio->estado);
            }

            if (! PlazosDeReclamo::clientePuedeAbrir(new DateTimeImmutable($servicio->finalizadaEn), $ahora, $this->parametros->aperturaClienteHoras())) {
                throw ReclamoInvalido::plazoVencido();
            }

            return;
        }

        if (! in_array($servicio->estado, $motivo->estadosDeSolicitudDelProveedor(), true)) {
            throw ReclamoInvalido::servicioNoDeclamable($servicio->estado);
        }

        $desde = $servicio->finalizadaEn;

        if (! PlazosDeReclamo::proveedorPuedeAbrir($desde === null ? null : new DateTimeImmutable($desde), $ahora, $this->parametros->aperturaProveedorDias())) {
            throw ReclamoInvalido::plazoVencido();
        }
    }

    /** El monto en reclamo: lo pedido, que no puede pasar del monto del servicio, o todo el monto. */
    private function monto(DatosServicio $servicio, ?string $pedido): string
    {
        $referencia = BigDecimal::of($servicio->montoDeReferencia())->toScale(2, RoundingMode::HalfUp);

        if ($referencia->isLessThanOrEqualTo(0)) {
            throw ReclamoInvalido::montoInvalido((string) $referencia);
        }

        if ($pedido === null || trim($pedido) === '') {
            return (string) $referencia;
        }

        $monto = BigDecimal::of($pedido)->toScale(2, RoundingMode::HalfUp);

        if ($monto->isLessThanOrEqualTo(0) || $monto->isGreaterThan($referencia)) {
            throw ReclamoInvalido::montoInvalido((string) $referencia);
        }

        return (string) $monto;
    }
}
