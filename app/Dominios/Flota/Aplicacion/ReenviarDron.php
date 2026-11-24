<?php

namespace App\Dominios\Flota\Aplicacion;

use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\OrigenCambio;
use App\Dominios\Flota\Dominio\Excepciones\OperacionDeFlotaInvalida;
use App\Dominios\Flota\Infraestructura\Eloquent\Dron;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;

/** El titular reenvía a revisión un dron suyo rechazado (`rechazado → pendiente`). */
final class ReenviarDron
{
    public function __construct(
        private readonly LecturaProveedores $proveedores,
        private readonly ResolverAprobacionDron $aprobacion,
    ) {}

    /** @throws OperacionDeFlotaInvalida si el dron no es del titular. */
    public function ejecutar(int $actorUsuarioId, int $dronId): void
    {
        $proveedor = $this->proveedores->proveedorDelTitular($actorUsuarioId) ?? throw OperacionDeFlotaInvalida::sinProveedor();

        if (! Dron::query()->whereKey($dronId)->where('dueno_proveedor_id', $proveedor->id)->exists()) {
            throw OperacionDeFlotaInvalida::dronAjeno();
        }

        $this->aprobacion->ejecutar($dronId, AccionAprobacion::Reenviar, $actorUsuarioId, null, OrigenCambio::Solicitante);
    }
}
