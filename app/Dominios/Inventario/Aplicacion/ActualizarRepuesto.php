<?php

namespace App\Dominios\Inventario\Aplicacion;

use App\Dominios\Inventario\Dominio\Excepciones\RepuestoDuplicado;
use App\Dominios\Inventario\Infraestructura\Eloquent\Repuesto;
use Illuminate\Database\QueryException;

/**
 * Edición de un repuesto del catálogo (HU-36, tarea 52). `costo_unitario` SÍ
 * es editable a mano acá (a diferencia de cómo lo toca `RegistrarMovimientoStock`
 * en cada compra): permite corregir un valor cargado mal sin tener que pasar
 * por un movimiento de compra ficticio.
 */
final class ActualizarRepuesto
{
    /**
     * @throws RepuestoDuplicado si el código ya pertenece a otro repuesto
     *                           activo (índice parcial `inv_repuestos_codigo_unico`).
     */
    public function ejecutar(Repuesto $repuesto, string $codigo, string $descripcion, string $unidad, ?string $costoUnitario): Repuesto
    {
        $repuesto->codigo = $codigo;
        $repuesto->descripcion = $descripcion;
        $repuesto->unidad = $unidad;
        $repuesto->costo_unitario = $costoUnitario;

        try {
            $repuesto->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicado($excepcion, $codigo);
        }

        return $repuesto->refresh();
    }

    /**
     * @throws RepuestoDuplicado si la violación corresponde al código.
     * @throws QueryException si la violación no es la contemplada.
     */
    private function relanzarComoDuplicado(QueryException $excepcion, string $codigo): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'inv_repuestos_codigo_unico') || str_contains($mensaje, 'inv_repuestos.codigo')) {
            throw RepuestoDuplicado::porCodigo($codigo);
        }

        throw $excepcion;
    }
}
