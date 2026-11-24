<?php

namespace App\Dominios\Disputas\Aplicacion;

use App\Dominios\Disputas\Aplicacion\MaquinaEstados\MaquinaEstadosDisputa;
use App\Dominios\Disputas\Dominio\EstadoDisputa;
use App\Dominios\Disputas\Dominio\Excepciones\DisputaInvalida;
use App\Dominios\Disputas\Infraestructura\Eloquent\Disputa;

/**
 * Soporte toma una disputa abierta (política de disputas, «Proceso», punto 2):
 * pasa a `en_revision` con quien la atiende. Idempotente: tomar una disputa ya
 * en revisión no hace nada.
 */
final class TomarDisputa
{
    public function __construct(private readonly MaquinaEstadosDisputa $estados) {}

    /** @throws DisputaInvalida */
    public function ejecutar(int $actorUsuarioId, int $disputaId): Disputa
    {
        $disputa = Disputa::query()->find($disputaId) ?? throw DisputaInvalida::disputaAjena();

        if ($disputa->estado === EstadoDisputa::EnRevision) {
            return $disputa;
        }

        return $this->estados->transicionar($disputa, EstadoDisputa::EnRevision, $actorUsuarioId, 'administrador', null, ['atendida_por' => $actorUsuarioId]);
    }
}
