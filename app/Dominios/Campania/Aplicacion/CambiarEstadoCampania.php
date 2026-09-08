<?php

namespace App\Dominios\Campania\Aplicacion;

use App\Dominios\Campania\Aplicacion\MaquinaEstados\MaquinaEstadosCampania;
use App\Dominios\Campania\Dominio\EstadoCampania;
use App\Dominios\Campania\Dominio\Excepciones\CampaniaSolapada;
use App\Dominios\Campania\Dominio\Excepciones\TransicionCampaniaNoPermitida;
use App\Dominios\Campania\Infraestructura\Eloquent\Campania;

/**
 * Cambio de estado de una campaña desde el panel (ADR 0015 punto 1, tarea
 * 69): adaptador delgado sobre {@see MaquinaEstadosCampania::cambiarA()} —
 * ninguna regla de negocio vive acá, solo delega (invariante 7). "Solo el
 * dueño cierra una campaña" lo resuelve el permiso
 * `campania.campania.cambiar_estado` en `SeguridadSeeder` (exclusivo del rol
 * `dueno`), no una guarda acá.
 */
final class CambiarEstadoCampania
{
    public function __construct(private readonly MaquinaEstadosCampania $maquinaEstados) {}

    /**
     * @throws TransicionCampaniaNoPermitida si la transición no está permitida.
     * @throws CampaniaSolapada si `$hacia` es `abierta` y el rango se solapa con otra.
     */
    public function ejecutar(Campania $campania, EstadoCampania $hacia): Campania
    {
        return $this->maquinaEstados->cambiarA($campania, $hacia);
    }
}
