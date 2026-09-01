<?php

namespace App\Dominios\Operaciones\Aplicacion\MaquinaEstados;

use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\Excepciones\TransicionSesionNoPermitida;
use App\Dominios\Operaciones\Dominio\MaquinaEstados\TransicionesSesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use Carbon\CarbonImmutable;

/**
 * Única clase que crea/muta el `estado` de `sesion` (invariante 7 de
 * CLAUDE.md).
 *
 * `atributos['trabajo_id']` ya viene resuelto a un id de servidor: la
 * resolución de la referencia por `uuid_cliente` del trabajo (espec §2.1
 * punto 5) es responsabilidad del contrato de escritura de `Operaciones`
 * (TE-05), no de esta clase.
 *
 * `cerrar()` (HU-05, tarea 13) es la contraparte de `abrir()`: consulta
 * {@see TransicionesSesion::permitida()} antes de escribir y lanza
 * {@see TransicionSesionNoPermitida} si la transición no está permitida.
 * `$motivoCierre` es la columna simple del catálogo de la espec §4.3
 * (completado / relevo_piloto / cambio_dron / falla_equipo / clima /
 * fin_jornada / otro) — sin la lógica de relevo de HU-07, que esta tarea no
 * implementa.
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

    /**
     * @throws TransicionSesionNoPermitida si `$sesion` no está `abierto`.
     */
    public function cerrar(Sesion $sesion, string $cierreUuidCliente, string $fin, string $motivoCierre): Sesion
    {
        $desde = $sesion->estado;
        $hasta = EstadoSesion::Cerrado;

        if (! TransicionesSesion::permitida($desde, $hasta)) {
            throw TransicionSesionNoPermitida::entre($desde, $hasta);
        }

        $sesion->estado = $hasta;
        $sesion->cierre_uuid_cliente = $cierreUuidCliente;
        $sesion->fin = CarbonImmutable::parse($fin);
        $sesion->motivo_cierre = $motivoCierre;
        $sesion->save();

        return $sesion;
    }
}
