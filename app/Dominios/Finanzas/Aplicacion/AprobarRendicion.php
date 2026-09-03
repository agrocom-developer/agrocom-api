<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Aplicacion\MaquinaEstados\MaquinaEstadosRendicion;
use App\Dominios\Finanzas\Dominio\Excepciones\JefeCampoNoPuedeAprobarSuPropiaRendicion;
use App\Dominios\Finanzas\Dominio\Excepciones\RendicionNoAprobable;
use App\Dominios\Finanzas\Dominio\PoliticaAprobacionRendicion;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Rendicion;

/**
 * Caso de uso "el encargado aprueba una rendición presentada" (HU-34, tarea
 * 48): aplica la policy de la invariante 4 ANTES de tocar la máquina de
 * estados — {@see MaquinaEstadosRendicion::aprobar()} es solo la que escribe
 * `estado` (invariante 7), no la que autoriza. Espejo exacto de
 * `Operaciones/Aplicacion/ValidarSesion` frente a `PoliticaValidacionSesion`.
 *
 * Estado de origen inválido (p. ej. todavía `abierta`) NO se chequea acá a
 * propósito: eso ya lo guarda `TransicionesRendicion` dentro de la máquina —
 * duplicarlo acá sería la misma regla escrita dos veces, con riesgo de
 * desalinearse.
 */
final class AprobarRendicion
{
    public function __construct(private readonly MaquinaEstadosRendicion $maquina) {}

    /**
     * @throws JefeCampoNoPuedeAprobarSuPropiaRendicion si `$aprobadorPersonaId` es el jefe de campo de `$rendicion`.
     * @throws RendicionNoAprobable si `$rendicion` no está `Presentada`.
     */
    public function ejecutar(Rendicion $rendicion, int $aprobadorPersonaId): Rendicion
    {
        if (! PoliticaAprobacionRendicion::puedeDecidir((int) $rendicion->jefe_campo_id, $aprobadorPersonaId)) {
            throw JefeCampoNoPuedeAprobarSuPropiaRendicion::paraRendicion($rendicion->id);
        }

        return $this->maquina->aprobar($rendicion, $aprobadorPersonaId);
    }
}
