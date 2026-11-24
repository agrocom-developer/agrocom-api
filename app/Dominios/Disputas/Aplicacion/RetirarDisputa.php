<?php

namespace App\Dominios\Disputas\Aplicacion;

use App\Dominios\Disputas\Aplicacion\MaquinaEstados\MaquinaEstadosDisputa;
use App\Dominios\Disputas\Contratos\Eventos\DisputaResuelta;
use App\Dominios\Disputas\Dominio\EstadoDisputa;
use App\Dominios\Disputas\Dominio\Excepciones\DisputaInvalida;
use App\Dominios\Disputas\Dominio\ParteDeDisputa;
use App\Dominios\Disputas\Infraestructura\Eloquent\Disputa;
use App\Dominios\Solicitudes\Contratos\ResolucionDeServicio;
use Illuminate\Support\Facades\DB;

/**
 * Quien abrió la disputa la retira mientras está viva (`POST /disputas/{id}/retirar`,
 * especificación §5.5). Si la disputa había movido la solicitud a `en_disputa`, la
 * solicitud se completa como si el reclamo no se hubiera hecho. Idempotente: retirar
 * una disputa ya retirada devuelve la misma.
 */
final class RetirarDisputa
{
    public function __construct(
        private readonly ParticipanteDeDisputa $participantes,
        private readonly MaquinaEstadosDisputa $estados,
        private readonly ResolucionDeServicio $resolucion,
    ) {}

    /** @throws DisputaInvalida */
    public function ejecutar(int $usuarioId, ParteDeDisputa $parte, int $disputaId): Disputa
    {
        $disputa = $this->participantes->disputa($usuarioId, $parte, $disputaId);

        if ($disputa->abierta_por_rol !== $parte) {
            throw DisputaInvalida::soloQuienLaAbrio();
        }

        if ($disputa->estado === EstadoDisputa::Retirada) {
            return $disputa;
        }

        if (! $disputa->estado->estaViva()) {
            throw DisputaInvalida::noSeRetira();
        }

        return DB::transaction(function () use ($disputa, $usuarioId, $parte): Disputa {
            $retirada = $this->estados->transicionar($disputa, EstadoDisputa::Retirada, $usuarioId, $parte->value, __('disputas.historial.retirada'));

            if ($retirada->movio_solicitud) {
                $this->resolucion->cerrarDisputa($retirada->solicitud_id, false, $usuarioId, __('disputas.historial.retirada'));
            }

            event(new DisputaResuelta(
                $retirada->id,
                $retirada->solicitud_id,
                'retirada',
                '0.00',
                $retirada->ronda,
                [...$this->participantes->usuariosDe($retirada, ParteDeDisputa::Cliente), ...$this->participantes->usuariosDe($retirada, ParteDeDisputa::Proveedor)],
            ));

            return $retirada;
        });
    }
}
