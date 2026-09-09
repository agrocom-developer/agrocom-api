<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Dominio\EstadoRendicion;
use App\Dominios\Finanzas\Dominio\Excepciones\GastoYaAsociadoARendicion;
use App\Dominios\Finanzas\Dominio\Excepciones\RendicionNoAceptaGastos;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Gasto;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Rendicion;

/**
 * Asocia un gasto suelto a una rendición `Abierta` (HU-34, tarea 48). No
 * toca `estado` de nada (ni del gasto, que no tiene, ni de la rendición), así
 * que NO vive en `Aplicacion/MaquinaEstados/` — solo escribe `rendicion_id`
 * sobre el gasto, mismo criterio que cualquier caso de uso que no muta una
 * máquina de estados.
 *
 * El `monto` de la rendición NO se actualiza acá: se recalcula recién al
 * presentar/aprobar (`MaquinaEstadosRendicion`), así que asociar y desasociar
 * gastos mientras la rendición sigue `Abierta` no requiere mantener ningún
 * acumulador sincronizado.
 */
final class AsociarGastoARendicion
{
    /**
     * @throws GastoYaAsociadoARendicion si `$gasto` ya pertenece a otra rendición.
     * @throws RendicionNoAceptaGastos si `$rendicion` no está `Abierta`.
     */
    public function ejecutar(Gasto $gasto, Rendicion $rendicion): Gasto
    {
        if ($gasto->rendicion_id !== null) {
            throw GastoYaAsociadoARendicion::paraGasto($gasto->id);
        }

        if ($rendicion->estado !== EstadoRendicion::Abierta) {
            throw RendicionNoAceptaGastos::paraRendicion($rendicion->id);
        }

        $gasto->rendicion_id = $rendicion->id;
        $gasto->save();

        return $gasto->refresh();
    }
}
