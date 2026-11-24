<?php

namespace App\Dominios\Proveedores\Aplicacion;

use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\OrigenCambio;
use App\Dominios\Proveedores\Dominio\Excepciones\RegistroInvalido;
use App\Dominios\Proveedores\Dominio\TipoSujeto;

/**
 * El solicitante corrige lo que le rechazaron y vuelve a la cola de revisión
 * (`rechazado → pendiente`, especificación §5.2: «el solicitante puede
 * reenviar»). Solo puede reenviar lo que gestiona (ver
 * {@see AlcanceDelSolicitante}); la transición y el historial son de
 * {@see ResolverAprobacion}.
 */
final class ReenviarARevision
{
    public function __construct(
        private readonly AlcanceDelSolicitante $alcance,
        private readonly ResolverAprobacion $aprobacion,
    ) {}

    /**
     * @throws RegistroInvalido si la cuenta no gestiona al sujeto.
     */
    public function ejecutar(int $actorUsuarioId, TipoSujeto $tipo, int $sujetoId): void
    {
        if (! $this->alcance->puedeGestionar($actorUsuarioId, $tipo, $sujetoId)) {
            throw RegistroInvalido::sujetoAjeno();
        }

        $this->aprobacion->ejecutar($tipo, $sujetoId, AccionAprobacion::Reenviar, $actorUsuarioId, null, OrigenCambio::Solicitante);
    }
}
