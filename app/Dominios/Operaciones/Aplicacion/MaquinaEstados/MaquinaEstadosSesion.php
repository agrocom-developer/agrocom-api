<?php

namespace App\Dominios\Operaciones\Aplicacion\MaquinaEstados;

use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;

/**
 * Única clase que crea/muta el `estado` de `sesion` (invariante 7 de
 * CLAUDE.md). Mismo criterio que `MaquinaEstadosTrabajo`: hoy solo abre — el
 * cierre real con hectáreas y `motivo_cierre` (HU-05) llega después.
 *
 * `atributos['trabajo_id']` ya viene resuelto a un id de servidor: la
 * resolución de la referencia por `uuid_cliente` del trabajo (espec §2.1
 * punto 5) es responsabilidad del contrato de escritura de `Operaciones`
 * (TE-05), no de esta clase.
 */
final class MaquinaEstadosSesion
{
    /**
     * @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase.
     */
    public function abrir(array $atributos): Sesion
    {
        return Sesion::create([...$atributos, 'estado' => EstadoSesion::Abierto]);
    }
}
