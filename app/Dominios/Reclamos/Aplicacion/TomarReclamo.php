<?php

namespace App\Dominios\Reclamos\Aplicacion;

use App\Dominios\Reclamos\Aplicacion\MaquinaEstados\MaquinaEstadosReclamo;
use App\Dominios\Reclamos\Dominio\EstadoReclamo;
use App\Dominios\Reclamos\Dominio\Excepciones\ReclamoInvalido;
use App\Dominios\Reclamos\Infraestructura\Eloquent\Reclamo;

/**
 * Soporte toma un reclamo abierto (política de reclamos, «Proceso», punto 2):
 * pasa a `en_revision` con quien lo atiende. Idempotente: tomar un reclamo ya
 * en revisión no hace nada.
 */
final class TomarReclamo
{
    public function __construct(private readonly MaquinaEstadosReclamo $estados) {}

    /** @throws ReclamoInvalido */
    public function ejecutar(int $actorUsuarioId, int $reclamoId): Reclamo
    {
        $reclamo = Reclamo::query()->find($reclamoId) ?? throw ReclamoInvalido::reclamoAjeno();

        if ($reclamo->estado === EstadoReclamo::EnRevision) {
            return $reclamo;
        }

        return $this->estados->transicionar($reclamo, EstadoReclamo::EnRevision, $actorUsuarioId, 'administrador', null, ['atendida_por' => $actorUsuarioId]);
    }
}
