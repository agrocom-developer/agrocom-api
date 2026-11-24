<?php

namespace App\Dominios\Disputas\Aplicacion;

use App\Dominios\Disputas\Aplicacion\MaquinaEstados\MaquinaEstadosDisputa;
use App\Dominios\Disputas\Contratos\Eventos\DisputaResuelta;
use App\Dominios\Disputas\Dominio\EstadoDisputa;
use App\Dominios\Disputas\Dominio\Excepciones\DisputaInvalida;
use App\Dominios\Disputas\Dominio\ParteDeDisputa;
use App\Dominios\Disputas\Dominio\PlazosDeDisputa;
use App\Dominios\Disputas\Dominio\ReglaDeResolucion;
use App\Dominios\Disputas\Dominio\ResultadoDisputa;
use App\Dominios\Disputas\Infraestructura\Eloquent\Disputa;
use App\Dominios\Disputas\Infraestructura\Eloquent\ResolucionDeDisputa;
use App\Dominios\Solicitudes\Contratos\LecturaServicios;
use App\Dominios\Solicitudes\Contratos\ResolucionDeServicio;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Un administrador resuelve la disputa (`/admin/disputas/{id}/resolver`,
 * especificación §10.4, política de disputas, «Proceso» y «Reembolsos»): fija el
 * resultado (`a_favor_cliente`, `a_favor_proveedor`, `parcial` o `sin_lugar`), el
 * reembolso y el motivo. La decisión es un registro nuevo con autor y motivo y
 * nada se sobrescribe.
 *
 * - Primera ronda: la solicitud que la disputa había movido sale de `en_disputa`:
 *   `completada` (con monto final y comisión congelados) o `cancelada` si el
 *   reembolso es total.
 * - Apelación (segunda ronda): la nueva resolución reemplaza a la primera en lo que
 *   se reembolsa; el estado de la solicitud no se vuelve a mover.
 * - Agrocom ajusta su comisión en la misma proporción que el reembolso. El
 *   reembolso se liquida entre las partes fuera de la aplicación (ADR 0034).
 * - Un daño probado se deriva al seguro del proveedor; una disputa sin lugar con
 *   mala fe del cliente queda registrada en su reputación.
 *
 * Idempotente por ronda: resolver una disputa ya resuelta en la misma ronda
 * responde conflicto y no cambia nada.
 */
final class ResolverDisputa
{
    public function __construct(
        private readonly MaquinaEstadosDisputa $estados,
        private readonly LecturaServicios $servicios,
        private readonly ResolucionDeServicio $solicitudes,
        private readonly ParticipanteDeDisputa $participantes,
        private readonly ParametrosDeDisputa $parametros,
    ) {}

    /** @throws DisputaInvalida */
    public function ejecutar(
        int $actorUsuarioId,
        int $disputaId,
        ResultadoDisputa $resultado,
        string $motivo,
        string $montoReembolso = '0',
        bool $derivadaASeguro = false,
        bool $malaFeCliente = false,
    ): Disputa {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw DisputaInvalida::motivoRequerido();
        }

        try {
            return DB::transaction(function () use ($actorUsuarioId, $disputaId, $resultado, $motivo, $montoReembolso, $derivadaASeguro, $malaFeCliente): Disputa {
                $disputa = Disputa::query()->lockForUpdate()->find($disputaId) ?? throw DisputaInvalida::disputaAjena();

                if (! $disputa->estado->estaViva()) {
                    throw DisputaInvalida::noSeResuelve();
                }

                $servicio = $this->servicios->datos($disputa->solicitud_id) ?? throw DisputaInvalida::servicioAjeno();
                $referencia = $servicio->montoDeReferencia();
                $reembolso = (string) BigDecimal::of($montoReembolso === '' ? '0' : $montoReembolso)->toScale(2, RoundingMode::HalfUp);

                $error = ReglaDeResolucion::error($resultado, $referencia, $reembolso);

                if ($error !== null) {
                    throw DisputaInvalida::reembolsoInvalido($error);
                }

                if ($disputa->estado === EstadoDisputa::Abierta) {
                    $disputa = $this->estados->transicionar($disputa, EstadoDisputa::EnRevision, $actorUsuarioId, 'administrador', null, ['atendida_por' => $actorUsuarioId]);
                }

                $total = ReglaDeResolucion::esReembolsoTotal($resultado, $referencia, $reembolso);
                $despues = $servicio;

                if ($disputa->ronda === 1 && $disputa->movio_solicitud) {
                    $despues = $this->solicitudes->cerrarDisputa($disputa->solicitud_id, $total, $actorUsuarioId, $motivo) ?? $servicio;
                }

                $ahora = new DateTimeImmutable;

                ResolucionDeDisputa::query()->create([
                    'disputa_id' => $disputa->id,
                    'ronda' => $disputa->ronda,
                    'resultado' => $resultado->value,
                    'monto_reembolso' => $reembolso,
                    'comision_ajuste' => '0.00',
                    'derivada_a_seguro' => $derivadaASeguro,
                    'mala_fe_cliente' => $malaFeCliente && $resultado === ResultadoDisputa::SinLugar,
                    'motivo' => $motivo,
                    'resuelta_por' => $actorUsuarioId,
                    'resuelta_en' => $ahora,
                ]);

                $resuelta = $this->estados->transicionar($disputa, EstadoDisputa::Resuelta, $actorUsuarioId, 'administrador', $motivo, [
                    'apelable_hasta' => $disputa->ronda === 1 ? PlazosDeDisputa::apelableHasta($ahora, $this->parametros->apelacionDias()) : null,
                ]);

                event(new DisputaResuelta(
                    $resuelta->id,
                    $resuelta->solicitud_id,
                    $resultado->value,
                    $reembolso,
                    $resuelta->ronda,
                    [...$this->participantes->usuariosDe($resuelta, ParteDeDisputa::Cliente), ...$this->participantes->usuariosDe($resuelta, ParteDeDisputa::Proveedor)],
                ));

                return $resuelta;
            });
        } catch (UniqueConstraintViolationException) {
            throw DisputaInvalida::noSeResuelve();
        }
    }
}
