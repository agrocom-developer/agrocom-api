<?php

namespace App\Dominios\Flota\Aplicacion;

use App\Dominios\Flota\Dominio\Excepciones\OperacionDeFlotaInvalida;
use App\Dominios\Flota\Dominio\Vigencia;
use App\Dominios\Flota\Infraestructura\Eloquent\Equipo;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use Illuminate\Support\Carbon;

/**
 * El titular cierra un equipo suyo poniéndole fin de vigencia (el equipo no se
 * borra: queda el historial de quién operó). El piloto y el dron de un equipo no
 * se cambian: ese es otro equipo.
 */
final class ActualizarEquipo
{
    public function __construct(private readonly LecturaProveedores $proveedores) {}

    /**
     * @param  array{vigente_hasta?: ?string}  $cambios  Solo las claves presentes se aplican.
     *
     * @throws OperacionDeFlotaInvalida
     */
    public function ejecutar(int $actorUsuarioId, int $equipoId, array $cambios): Equipo
    {
        $proveedor = $this->proveedores->proveedorDelTitular($actorUsuarioId) ?? throw OperacionDeFlotaInvalida::sinProveedor();

        $equipo = Equipo::query()->where('proveedor_id', $proveedor->id)->find($equipoId) ?? throw OperacionDeFlotaInvalida::equipoAjeno();

        if (array_key_exists('vigente_hasta', $cambios)) {
            $vigencia = new Vigencia($equipo->vigente_desde->toDateString(), $cambios['vigente_hasta']);

            if (! $vigencia->esValida()) {
                throw OperacionDeFlotaInvalida::vigenciaInvalida();
            }

            // Solo se acorta: alargar o reabrir una vigencia podría pisar a otro equipo
            // del mismo piloto o dron. Para eso se crea un equipo nuevo.
            $actual = $equipo->vigente_hasta?->toDateString();

            if ($actual !== null && ($cambios['vigente_hasta'] === null || $cambios['vigente_hasta'] > $actual)) {
                throw OperacionDeFlotaInvalida::vigenciaInvalida();
            }

            $equipo->vigente_hasta = $cambios['vigente_hasta'] === null ? null : Carbon::parse($cambios['vigente_hasta']);
        }

        $equipo->updated_by = $actorUsuarioId;
        $equipo->save();

        return $equipo->refresh();
    }
}
