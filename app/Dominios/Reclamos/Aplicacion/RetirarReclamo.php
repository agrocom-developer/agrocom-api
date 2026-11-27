<?php

namespace App\Dominios\Reclamos\Aplicacion;

use App\Dominios\Reclamos\Aplicacion\MaquinaEstados\MaquinaEstadosReclamo;
use App\Dominios\Reclamos\Contratos\Eventos\ReclamoResuelto;
use App\Dominios\Reclamos\Dominio\EstadoReclamo;
use App\Dominios\Reclamos\Dominio\Excepciones\ReclamoInvalido;
use App\Dominios\Reclamos\Dominio\ParteDeReclamo;
use App\Dominios\Reclamos\Infraestructura\Eloquent\Reclamo;
use App\Dominios\Solicitudes\Contratos\ResolucionDeServicio;
use Illuminate\Support\Facades\DB;

/**
 * Quien abrió el reclamo lo retira mientras está vivo (`POST /reclamos/{id}/retirar`,
 * especificación §5.5). Si el reclamo había movido la solicitud a `en_reclamo`, la
 * solicitud se completa como si el reclamo no se hubiera hecho. Idempotente: retirar
 * un reclamo ya retirado devuelve el mismo.
 */
final class RetirarReclamo
{
    public function __construct(
        private readonly ParticipanteDeReclamo $participantes,
        private readonly MaquinaEstadosReclamo $estados,
        private readonly ResolucionDeServicio $resolucion,
    ) {}

    /** @throws ReclamoInvalido */
    public function ejecutar(int $usuarioId, ParteDeReclamo $parte, int $reclamoId): Reclamo
    {
        $reclamo = $this->participantes->reclamo($usuarioId, $parte, $reclamoId);

        if ($reclamo->abierta_por_rol !== $parte) {
            throw ReclamoInvalido::soloQuienLaAbrio();
        }

        if ($reclamo->estado === EstadoReclamo::Retirada) {
            return $reclamo;
        }

        if (! $reclamo->estado->estaViva()) {
            throw ReclamoInvalido::noSeRetira();
        }

        return DB::transaction(function () use ($reclamo, $usuarioId, $parte): Reclamo {
            $retirada = $this->estados->transicionar($reclamo, EstadoReclamo::Retirada, $usuarioId, $parte->value, __('reclamos.historial.retirada'));

            if ($retirada->movio_solicitud) {
                $this->resolucion->cerrarReclamo($retirada->solicitud_id, false, $usuarioId, __('reclamos.historial.retirada'));
            }

            event(new ReclamoResuelto(
                $retirada->id,
                $retirada->solicitud_id,
                'retirada',
                '0.00',
                $retirada->ronda,
                [...$this->participantes->usuariosDe($retirada, ParteDeReclamo::Cliente), ...$this->participantes->usuariosDe($retirada, ParteDeReclamo::Proveedor)],
            ));

            return $retirada;
        });
    }
}
