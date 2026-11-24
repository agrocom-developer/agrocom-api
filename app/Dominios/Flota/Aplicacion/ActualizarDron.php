<?php

namespace App\Dominios\Flota\Aplicacion;

use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Flota\Dominio\Excepciones\OperacionDeFlotaInvalida;
use App\Dominios\Flota\Infraestructura\Eloquent\Dron;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use Illuminate\Database\QueryException;

/**
 * El titular corrige la ficha de un dron suyo mientras espera revisión
 * (`pendiente`) o tras un rechazo. Un dron aprobado no se edita: cambiar su
 * serie o su capacidad invalidaría lo que el administrador revisó, y un dron
 * distinto se registra como dron nuevo. El alcance es el dueño del dron (nunca
 * un dron ajeno, aunque se adivine el id).
 */
final class ActualizarDron
{
    public function __construct(private readonly LecturaProveedores $proveedores) {}

    /**
     * @param  array{marca: string, modelo: string, numero_serie: string, capacidad_tanque_l: string, ancho_pasada_m: string}  $datos
     *
     * @throws OperacionDeFlotaInvalida si el dron no es del titular, ya no se puede editar o la serie ya existe.
     */
    public function ejecutar(int $actorUsuarioId, int $dronId, array $datos): Dron
    {
        $proveedor = $this->proveedores->proveedorDelTitular($actorUsuarioId) ?? throw OperacionDeFlotaInvalida::sinProveedor();

        $dron = Dron::query()->where('dueno_proveedor_id', $proveedor->id)->find($dronId) ?? throw OperacionDeFlotaInvalida::dronAjeno();

        if (! in_array($dron->estado, [EstadoAprobacion::Pendiente, EstadoAprobacion::Rechazado], true)) {
            throw OperacionDeFlotaInvalida::dronNoEditable();
        }

        $dron->fill([
            'marca' => $datos['marca'],
            'modelo' => $datos['modelo'],
            'numero_serie' => $datos['numero_serie'],
            'capacidad_tanque_l' => $datos['capacidad_tanque_l'],
            'ancho_pasada_m' => $datos['ancho_pasada_m'],
        ]);
        $dron->updated_by = $actorUsuarioId;

        try {
            $dron->save();
        } catch (QueryException $excepcion) {
            if (str_contains($excepcion->getMessage(), 'flo_drones_numero_serie_unico') || str_contains($excepcion->getMessage(), 'flo_drones.numero_serie')) {
                throw OperacionDeFlotaInvalida::numeroDeSerieDuplicado($datos['numero_serie']);
            }

            throw $excepcion;
        }

        return $dron->refresh();
    }
}
