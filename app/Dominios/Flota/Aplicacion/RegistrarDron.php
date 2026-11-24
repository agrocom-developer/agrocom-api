<?php

namespace App\Dominios\Flota\Aplicacion;

use App\Dominios\Flota\Aplicacion\MaquinaEstados\MaquinaEstadosDron;
use App\Dominios\Flota\Dominio\Excepciones\OperacionDeFlotaInvalida;
use App\Dominios\Flota\Dominio\OrigenDron;
use App\Dominios\Flota\Infraestructura\Eloquent\Dron;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use Illuminate\Database\QueryException;

/**
 * El titular de un proveedor registra un dron suyo: queda `pendiente` hasta que
 * un administrador lo aprueba con sus documentos (ADR 0029, punto 2). El dueño
 * y el operador son el proveedor de quien registra; cambiar el operador (poner
 * el dron a trabajar con otro proveedor) es una decisión que hoy toma un
 * administrador. El origen sale del proveedor: `flota_agrocom` para Agrocom,
 * `externo` para el resto — `vendido_por_agrocom` no se pide por la API.
 */
final class RegistrarDron
{
    public function __construct(
        private readonly LecturaProveedores $proveedores,
        private readonly MaquinaEstadosDron $estados,
    ) {}

    /**
     * @param  array{marca: string, modelo: string, numero_serie: string, capacidad_tanque_l: string, ancho_pasada_m: string, anio?: int|string|null}  $datos
     *
     * @throws OperacionDeFlotaInvalida si la cuenta no es titular de un proveedor, el proveedor no admite altas o la serie ya existe.
     */
    public function ejecutar(int $actorUsuarioId, array $datos): Dron
    {
        $proveedor = $this->proveedores->proveedorDelTitular($actorUsuarioId) ?? throw OperacionDeFlotaInvalida::sinProveedor();

        if (! $proveedor->admiteAltas()) {
            throw OperacionDeFlotaInvalida::proveedorNoAdmiteAltas();
        }

        try {
            return $this->estados->registrar([
                'dueno_proveedor_id' => $proveedor->id,
                'operador_proveedor_id' => $proveedor->id,
                'marca' => $datos['marca'],
                'modelo' => $datos['modelo'],
                'numero_serie' => $datos['numero_serie'],
                'capacidad_tanque_l' => $datos['capacidad_tanque_l'],
                'ancho_pasada_m' => $datos['ancho_pasada_m'],
                'anio' => isset($datos['anio']) ? (int) $datos['anio'] : null,
                'ciclos_bateria' => 0,
                'origen' => $proveedor->tipo === 'propio' ? OrigenDron::FlotaAgrocom : OrigenDron::Externo,
                'created_by' => $actorUsuarioId,
                'updated_by' => $actorUsuarioId,
            ]);
        } catch (QueryException $excepcion) {
            if (str_contains($excepcion->getMessage(), 'flo_drones_numero_serie_unico') || str_contains($excepcion->getMessage(), 'flo_drones.numero_serie')) {
                throw OperacionDeFlotaInvalida::numeroDeSerieDuplicado($datos['numero_serie']);
            }

            throw $excepcion;
        }
    }
}
