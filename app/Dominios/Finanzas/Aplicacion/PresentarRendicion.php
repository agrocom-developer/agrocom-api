<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Aplicacion\MaquinaEstados\MaquinaEstadosRendicion;
use App\Dominios\Finanzas\Dominio\Excepciones\RendicionNoPresentable;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Rendicion;

/**
 * `POST /panel/rendiciones/{rendicion}/presentar` (HU-34, tarea 48):
 * transiciona `Abierta → Presentada` y congela el `monto` como la suma de
 * los gastos asociados. Sin guarda de persona (a diferencia de
 * `AprobarRendicion`): cualquiera con el permiso `finanzas.rendicion.presentar`
 * puede presentar la rendición que armó.
 *
 * Las guardas de negocio ("¿está `Abierta`?", "¿tiene gastos asociados?") las
 * aplica `MaquinaEstadosRendicion::presentar()` contra `TransicionesRendicion`,
 * lanzando {@see RendicionNoPresentable} si no corresponde — este caso de uso
 * no las duplica.
 */
final class PresentarRendicion
{
    public function __construct(private readonly MaquinaEstadosRendicion $maquina) {}

    /**
     * @throws RendicionNoPresentable si `$rendicion` no está `Abierta` o no tiene gastos asociados.
     */
    public function ejecutar(Rendicion $rendicion): Rendicion
    {
        return $this->maquina->presentar($rendicion);
    }
}
