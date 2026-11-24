<?php

namespace App\Dominios\Mantenimiento\Infraestructura;

use App\Dominios\Mantenimiento\Contratos\EstadoRecursoPanel;
use App\Dominios\Mantenimiento\Contratos\LecturaEquipamiento;
use App\Dominios\Mantenimiento\Contratos\LecturaEstadoEquipamiento;
use App\Dominios\Mantenimiento\Dominio\EstadoBateria;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Bateria;

/**
 * Implementación Eloquent de {@see LecturaEstadoEquipamiento}. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaEquipamientoEloquent`: esa subcarpeta es solo para modelos.
 *
 * Los tonos no se vuelven a definir acá: se leen de `TONO_POR_ESTADO` del
 * controlador de baterías, que es donde el listado los declara UNA vez (§6.3.4
 * de la guía de pantalla), para que el dashboard nunca pinte distinto que el
 * listado.
 */
final class LecturaEstadoEquipamientoEloquent implements LecturaEstadoEquipamiento
{
    /** Tono del badge de cada estado de batería. */
    private const array TONO_POR_ESTADO = [
        'activa' => 'success',
        'retirada' => 'neutral',
        'mantenimiento' => 'neutral',
    ];

    private const ESTADO_DRON_OPERATIVO = 'operativo';

    public function porIds(string $tipo, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return match ($tipo) {
            self::TIPO_DRON => $this->drones($ids),
            LecturaEquipamiento::TIPO_BATERIA => $this->baterias($ids),
            default => [],
        };
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, EstadoRecursoPanel>
     */
    private function baterias(array $ids): array
    {
        $estados = [];

        foreach (Bateria::query()->whereIn('id', $ids)->get() as $bateria) {
            $id = (int) $bateria->getKey();
            $estado = (string) $bateria->getAttribute('estado');

            $estados[$id] = new EstadoRecursoPanel(
                tipo: LecturaEquipamiento::TIPO_BATERIA,
                id: $id,
                estado: $estado,
                etiqueta: __("mantenimiento.estado_bateria.{$estado}"),
                tono: self::TONO_POR_ESTADO[$estado] ?? 'neutral',
                operativo: $estado === EstadoBateria::Activa->value,
            );
        }

        return $estados;
    }

    /**
     * Un dron figura siempre operativo. No se valida que el id exista en
     * `ope_drones` (es de Operaciones): quien pregunta trae ids que ya tiene
     * asignados.
     *
     * @param  list<int>  $ids
     * @return array<int, EstadoRecursoPanel>
     */
    private function drones(array $ids): array
    {
        $estados = [];

        foreach ($ids as $id) {
            $estados[$id] = new EstadoRecursoPanel(
                tipo: self::TIPO_DRON,
                id: $id,
                estado: self::ESTADO_DRON_OPERATIVO,
                etiqueta: __('mantenimiento.estado_dron.'.self::ESTADO_DRON_OPERATIVO),
                tono: 'success',
                operativo: true,
            );
        }

        return $estados;
    }
}
