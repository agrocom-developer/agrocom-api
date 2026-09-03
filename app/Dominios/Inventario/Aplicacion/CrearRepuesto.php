<?php

namespace App\Dominios\Inventario\Aplicacion;

use App\Dominios\Inventario\Dominio\Excepciones\RepuestoDuplicado;
use App\Dominios\Inventario\Infraestructura\Eloquent\Repuesto;
use Illuminate\Database\QueryException;

/**
 * Alta de un repuesto del catálogo (HU-36, tarea 52): código, descripción,
 * unidad y costo unitario opcional (una alta sin compra previa todavía no
 * tiene costo conocido).
 */
final class CrearRepuesto
{
    /**
     * @throws RepuestoDuplicado si el código ya pertenece a otro repuesto
     *                           activo (índice parcial `inv_repuestos_codigo_unico`).
     */
    public function ejecutar(string $codigo, string $descripcion, string $unidad, ?string $costoUnitario): Repuesto
    {
        $repuesto = new Repuesto([
            'codigo' => $codigo,
            'descripcion' => $descripcion,
            'unidad' => $unidad,
            'costo_unitario' => $costoUnitario,
        ]);

        try {
            $repuesto->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicado($excepcion, $codigo);
        }

        return $repuesto->refresh();
    }

    /**
     * Traduce la violación del índice único parcial a una excepción de
     * dominio legible — mismo criterio que `CrearBateria::relanzarComoDuplicada`.
     * El formato del mensaje difiere por driver: Postgres nombra el índice
     * (`inv_repuestos_codigo_unico`); SQLite (motor de los tests) nombra
     * tabla.columna (`inv_repuestos.codigo`).
     *
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
