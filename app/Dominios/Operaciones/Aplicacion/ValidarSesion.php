<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosSesion;
use App\Dominios\Operaciones\Dominio\Excepciones\PilotoNoPuedeDecidirSuPropiaSesion;
use App\Dominios\Operaciones\Dominio\Excepciones\SesionNoDisponibleParaDecision;
use App\Dominios\Operaciones\Dominio\Excepciones\TransicionSesionNoPermitida;
use App\Dominios\Operaciones\Dominio\PoliticaValidacionSesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;

/**
 * Caso de uso "el jefe de campo aprueba una sesión cerrada" (HU-14, tarea
 * 14): aplica la policy de la invariante 4 ANTES de tocar la máquina de
 * estados — {@see MaquinaEstadosSesion::validar()} es solo la que escribe
 * `estado` (invariante 7), no la que autoriza.
 *
 * Estado de origen inválido (p. ej. todavía `abierto`) NO se chequea acá a
 * propósito: eso ya lo guarda {@see
 * \App\Dominios\Operaciones\Dominio\MaquinaEstados\TransicionesSesion} dentro
 * de la máquina — duplicarlo acá sería la misma regla escrita dos veces, con
 * riesgo de desalinearse. `anulada_en`, en cambio, SÍ se chequea acá: no es
 * parte de esa tabla de transiciones (una sesión rechazada se queda
 * `cerrado` para siempre, invariante 2), así que sin este chequeo la máquina
 * dejaría "validar" una sesión ya anulada por un rechazo — se filtra en la
 * capa de autorización, mismo lugar que la policy.
 */
final class ValidarSesion
{
    public function __construct(private readonly MaquinaEstadosSesion $maquina) {}

    /**
     * @throws SesionNoDisponibleParaDecision si `$sesion` ya fue anulada por un rechazo.
     * @throws PilotoNoPuedeDecidirSuPropiaSesion si `$validadorPersonaId` es el piloto de `$sesion`.
     * @throws TransicionSesionNoPermitida si `$sesion` no está `cerrado` ni `validado`.
     */
    public function ejecutar(Sesion $sesion, int $validadorPersonaId): Sesion
    {
        if ($sesion->anulada_en !== null) {
            throw SesionNoDisponibleParaDecision::porYaAnulada($sesion->id);
        }

        if (! PoliticaValidacionSesion::puedeDecidir((int) $sesion->piloto_id, $validadorPersonaId)) {
            throw PilotoNoPuedeDecidirSuPropiaSesion::paraSesion($sesion->id);
        }

        return $this->maquina->validar($sesion, $validadorPersonaId);
    }
}
