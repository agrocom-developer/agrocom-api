<?php

namespace App\Dominios\Campania\Aplicacion\MaquinaEstados;

use App\Dominios\Campania\Dominio\EstadoCampania;
use App\Dominios\Campania\Dominio\Excepciones\CampaniaSolapada;
use App\Dominios\Campania\Dominio\Excepciones\TransicionCampaniaNoPermitida;
use App\Dominios\Campania\Dominio\MaquinaEstados\TransicionesCampania;
use App\Dominios\Campania\Dominio\ValidadorSolapamientoCampanias;
use App\Dominios\Campania\Infraestructura\Eloquent\Campania;

/**
 * Única clase que crea/muta el `estado` de `campania` (invariante 7 de
 * CLAUDE.md), mismo criterio que `MaquinaEstadosContrato`.
 *
 * La guarda de `abrir()` (no solaparse con otra campaña `abierta`) es de
 * DATOS de la propia campaña, no de quién ejecuta la acción — por eso vive
 * acá, mismo criterio que `MaquinaEstadosContrato::activar()` con las
 * ventanas horarias. "Solo el dueño cierra una campaña" (ADR 0015, tarea 69)
 * es autorización y se resuelve con el permiso
 * `campania.campania.cambiar_estado` en `SeguridadSeeder`, no con una guarda
 * acá.
 */
final class MaquinaEstadosCampania
{
    /**
     * @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase.
     */
    public function crear(array $atributos): Campania
    {
        return Campania::create([...$atributos, 'estado' => EstadoCampania::Planificada]);
    }

    /**
     * `planificada → abierta` (ADR 0015 punto 1).
     *
     * @throws TransicionCampaniaNoPermitida si `$campania` no está `planificada`.
     * @throws CampaniaSolapada si el rango de fechas se solapa con otra campaña `abierta`.
     */
    public function abrir(Campania $campania): Campania
    {
        $desde = $campania->estado;
        $hasta = EstadoCampania::Abierta;

        if (! TransicionesCampania::permitida($desde, $hasta)) {
            throw TransicionCampaniaNoPermitida::entre($desde, $hasta);
        }

        $otraSolapada = Campania::query()
            ->where('estado', EstadoCampania::Abierta->value)
            ->where('id', '!=', $campania->id)
            ->get(['id', 'codigo', 'fecha_inicio', 'fecha_fin'])
            ->first(fn (Campania $otra): bool => ValidadorSolapamientoCampanias::seSolapaConAlguna(
                ['fecha_inicio' => $campania->fecha_inicio->toDateString(), 'fecha_fin' => $campania->fecha_fin->toDateString()],
                [['fecha_inicio' => $otra->fecha_inicio->toDateString(), 'fecha_fin' => $otra->fecha_fin->toDateString()]],
            ));

        if ($otraSolapada !== null) {
            throw CampaniaSolapada::con($otraSolapada->codigo);
        }

        $campania->estado = $hasta;
        $campania->save();

        return $campania;
    }

    /**
     * `abierta → cerrada` (ADR 0015 punto 1). Sin guarda adicional: el cierre
     * es una decisión del dueño, no depende de datos de la propia campaña.
     *
     * @throws TransicionCampaniaNoPermitida si `$campania` no está `abierta`.
     */
    public function cerrar(Campania $campania): Campania
    {
        return $this->transicionar($campania, EstadoCampania::Cerrada);
    }

    /**
     * Punto de entrada único para el controlador HTTP: resuelve a qué método
     * de transición corresponde `$hacia` sin que el llamador tenga que
     * conocer el nombre de cada uno. `Planificada` nunca es un destino
     * válido — ningún estado lo admite en la tabla de transiciones, así que
     * cae al camino genérico y siempre rechaza.
     *
     * @throws TransicionCampaniaNoPermitida si la transición no está en la tabla.
     * @throws CampaniaSolapada si el destino es `abierta` y el rango se solapa.
     */
    public function cambiarA(Campania $campania, EstadoCampania $hacia): Campania
    {
        return match ($hacia) {
            EstadoCampania::Abierta => $this->abrir($campania),
            EstadoCampania::Cerrada => $this->cerrar($campania),
            EstadoCampania::Planificada => $this->transicionar($campania, $hacia),
        };
    }

    /** @throws TransicionCampaniaNoPermitida si la transición no está permitida. */
    private function transicionar(Campania $campania, EstadoCampania $hasta): Campania
    {
        $desde = $campania->estado;

        if (! TransicionesCampania::permitida($desde, $hasta)) {
            throw TransicionCampaniaNoPermitida::entre($desde, $hasta);
        }

        $campania->estado = $hasta;
        $campania->save();

        return $campania;
    }
}
