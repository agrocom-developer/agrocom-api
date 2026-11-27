<?php

namespace App\Dominios\Reclamos\Aplicacion;

use App\Dominios\Reclamos\Aplicacion\MaquinaEstados\MaquinaEstadosReclamo;
use App\Dominios\Reclamos\Contratos\Eventos\ReclamoResuelto;
use App\Dominios\Reclamos\Dominio\EstadoReclamo;
use App\Dominios\Reclamos\Dominio\Excepciones\ReclamoInvalido;
use App\Dominios\Reclamos\Dominio\ParteDeReclamo;
use App\Dominios\Reclamos\Dominio\PlazosDeReclamo;
use App\Dominios\Reclamos\Dominio\ReglaDeResolucion;
use App\Dominios\Reclamos\Dominio\ResultadoReclamo;
use App\Dominios\Reclamos\Infraestructura\Eloquent\Reclamo;
use App\Dominios\Reclamos\Infraestructura\Eloquent\ResolucionDeReclamo;
use App\Dominios\Solicitudes\Contratos\LecturaServicios;
use App\Dominios\Solicitudes\Contratos\ResolucionDeServicio;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Un administrador resuelve el reclamo (`/admin/reclamos/{id}/resolver`,
 * especificación §10.4, política de reclamos, «Proceso» y «Reembolsos»): fija el
 * resultado (`a_favor_cliente`, `a_favor_proveedor`, `parcial` o `sin_lugar`), el
 * reembolso y el motivo. La decisión es un registro nuevo con autor y motivo y
 * nada se sobrescribe.
 *
 * - Primera ronda: la solicitud que el reclamo había movido sale de `en_reclamo`:
 *   `completada` (con monto final y comisión congelados) o `cancelada` si el
 *   reembolso es total.
 * - Apelación (segunda ronda): la nueva resolución reemplaza a la primera en lo que
 *   se reembolsa; el estado de la solicitud no se vuelve a mover.
 * - Agrocom ajusta su comisión en la misma proporción que el reembolso. El
 *   reembolso se liquida entre las partes fuera de la aplicación (ADR 0034).
 * - Un daño probado se deriva al seguro del proveedor; un reclamo sin lugar con
 *   mala fe del cliente queda registrada en su reputación.
 *
 * Idempotente por ronda: resolver un reclamo ya resuelto en la misma ronda
 * responde conflicto y no cambia nada.
 */
final class ResolverReclamo
{
    public function __construct(
        private readonly MaquinaEstadosReclamo $estados,
        private readonly LecturaServicios $servicios,
        private readonly ResolucionDeServicio $solicitudes,
        private readonly ParticipanteDeReclamo $participantes,
        private readonly ParametrosDeReclamo $parametros,
    ) {}

    /** @throws ReclamoInvalido */
    public function ejecutar(
        int $actorUsuarioId,
        int $reclamoId,
        ResultadoReclamo $resultado,
        string $motivo,
        string $montoReembolso = '0',
        bool $derivadaASeguro = false,
        bool $malaFeCliente = false,
    ): Reclamo {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw ReclamoInvalido::motivoRequerido();
        }

        try {
            return DB::transaction(function () use ($actorUsuarioId, $reclamoId, $resultado, $motivo, $montoReembolso, $derivadaASeguro, $malaFeCliente): Reclamo {
                $reclamo = Reclamo::query()->lockForUpdate()->find($reclamoId) ?? throw ReclamoInvalido::reclamoAjeno();

                if (! $reclamo->estado->estaViva()) {
                    throw ReclamoInvalido::noSeResuelve();
                }

                $servicio = $this->servicios->datos($reclamo->solicitud_id) ?? throw ReclamoInvalido::servicioAjeno();
                $referencia = $servicio->montoDeReferencia();
                $reembolso = (string) BigDecimal::of($montoReembolso === '' ? '0' : $montoReembolso)->toScale(2, RoundingMode::HalfUp);

                $error = ReglaDeResolucion::error($resultado, $referencia, $reembolso);

                if ($error !== null) {
                    throw ReclamoInvalido::reembolsoInvalido($error);
                }

                if ($reclamo->estado === EstadoReclamo::Abierta) {
                    $reclamo = $this->estados->transicionar($reclamo, EstadoReclamo::EnRevision, $actorUsuarioId, 'administrador', null, ['atendida_por' => $actorUsuarioId]);
                }

                $total = ReglaDeResolucion::esReembolsoTotal($resultado, $referencia, $reembolso);
                $despues = $servicio;

                if ($reclamo->ronda === 1 && $reclamo->movio_solicitud) {
                    $despues = $this->solicitudes->cerrarReclamo($reclamo->solicitud_id, $total, $actorUsuarioId, $motivo) ?? $servicio;
                }

                $ahora = new DateTimeImmutable;

                ResolucionDeReclamo::query()->create([
                    'reclamo_id' => $reclamo->id,
                    'ronda' => $reclamo->ronda,
                    'resultado' => $resultado->value,
                    'monto_reembolso' => $reembolso,
                    'comision_ajuste' => '0.00',
                    'derivada_a_seguro' => $derivadaASeguro,
                    'mala_fe_cliente' => $malaFeCliente && $resultado === ResultadoReclamo::SinLugar,
                    'motivo' => $motivo,
                    'resuelta_por' => $actorUsuarioId,
                    'resuelta_en' => $ahora,
                ]);

                $resuelta = $this->estados->transicionar($reclamo, EstadoReclamo::Resuelta, $actorUsuarioId, 'administrador', $motivo, [
                    'apelable_hasta' => $reclamo->ronda === 1 ? PlazosDeReclamo::apelableHasta($ahora, $this->parametros->apelacionDias()) : null,
                ]);

                event(new ReclamoResuelto(
                    $resuelta->id,
                    $resuelta->solicitud_id,
                    $resultado->value,
                    $reembolso,
                    $resuelta->ronda,
                    [...$this->participantes->usuariosDe($resuelta, ParteDeReclamo::Cliente), ...$this->participantes->usuariosDe($resuelta, ParteDeReclamo::Proveedor)],
                ));

                return $resuelta;
            });
        } catch (UniqueConstraintViolationException) {
            throw ReclamoInvalido::noSeResuelve();
        }
    }
}
