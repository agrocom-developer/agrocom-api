<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Aplicacion\MaquinaEstados\MaquinaEstadosRendicion;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Rendicion;

/**
 * Alta de una rendición de campo (HU-34, tarea 48): "como jefe de campo,
 * quiero rendir los gastos que hice en campo". Nace vacía (`estado=Abierta`,
 * `monto=0.00`), sin gastos asociados todavía — la asociación es un paso
 * posterior y explícito (`Aplicacion/AsociarGastoARendicion`).
 *
 * Delega la escritura de `estado`/`monto` a `MaquinaEstadosRendicion::generar()`
 * (invariante 7 de CLAUDE.md): este caso de uso no toca esos campos.
 */
final class CrearRendicion
{
    public function __construct(private readonly MaquinaEstadosRendicion $maquina) {}

    public function ejecutar(int $baseId, int $jefeCampoId, string $fecha, ?string $descripcion): Rendicion
    {
        return $this->maquina->generar([
            'base_id' => $baseId,
            'jefe_campo_id' => $jefeCampoId,
            'fecha' => $fecha,
            'descripcion' => $descripcion,
        ]);
    }
}
