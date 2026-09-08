<?php

namespace App\Dominios\Campania\Aplicacion\MaquinaEstados;

use App\Dominios\Campania\Dominio\EstadoCampania;
use App\Dominios\Campania\Dominio\Excepciones\TransicionCampaniaNoPermitida;
use App\Dominios\Campania\Dominio\MaquinaEstados\TransicionesCampania;
use App\Dominios\Campania\Infraestructura\Eloquent\Campania;

/**
 * Única clase que crea/muta el `estado` de `campania` (invariante 7 de
 * CLAUDE.md), mismo criterio que `MaquinaEstadosContrato`.
 *
 * `abrir()` no lleva guarda de datos (corregido el 8/9/2026, ADR 0015 punto
 * 1): la campaña es del cliente, hay tantas abiertas como clientes en
 * campaña y sus rangos se pisan por definición (uno cosechando mientras otro
 * siembra), y hasta dentro de un mismo cliente se permiten varias abiertas a
 * la vez (soya de verano, maíz de invierno). "Solo el dueño cierra una
 * campaña" (ADR 0015, tarea 69) es autorización y se resuelve con el permiso
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
     */
    public function abrir(Campania $campania): Campania
    {
        return $this->transicionar($campania, EstadoCampania::Abierta);
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
