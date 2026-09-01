<?php

namespace App\Dominios\Operaciones\Aplicacion\MaquinaEstados;

use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;

/**
 * Única clase que crea/muta el `estado` de `trabajo` (invariante 7 de
 * CLAUDE.md). Hoy solo abre: es lo único que TE-05 sincroniza. El cierre real
 * con hectáreas (transición `abierto → cerrado`, ya declarada en
 * `Dominio/MaquinaEstados/TransicionesTrabajo`) llega con HU-05 y se agrega
 * acá cuando exista — no antes, no hay quién la dispare todavía.
 *
 * La idempotencia (¿este `uuid_cliente` ya existe?) no es responsabilidad de
 * esta clase: se apoya en el `UNIQUE` parcial de la migración y quien invoca
 * `abrir()` (el contrato de escritura de `Operaciones`, TE-05) es quien
 * envuelve la llamada en su propia transacción y traduce la violación de
 * unicidad en `duplicado` — la máquina de estados solo sabe crear con el
 * estado inicial correcto.
 */
final class MaquinaEstadosTrabajo
{
    /**
     * @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase.
     */
    public function abrir(array $atributos): Trabajo
    {
        return Trabajo::create([...$atributos, 'estado' => EstadoTrabajo::Abierto]);
    }
}
