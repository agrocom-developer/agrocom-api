<?php

namespace App\Dominios\Mantenimiento\Infraestructura;

use App\Dominios\Mantenimiento\Contratos\EstadoRecursoPanel;
use App\Dominios\Mantenimiento\Contratos\LecturaEquipamiento;
use App\Dominios\Mantenimiento\Contratos\LecturaEstadoEquipamiento;
use App\Dominios\Mantenimiento\Dominio\EstadoBateria;
use App\Dominios\Mantenimiento\Dominio\EstadoGenerador;
use App\Dominios\Mantenimiento\Dominio\EstadoOrdenMantenimiento;
use App\Dominios\Mantenimiento\Dominio\EstadoVehiculo;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Bateria;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Generador;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\OrdenMantenimiento;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Vehiculo;
use App\Dominios\Mantenimiento\Infraestructura\Http\Controllers\Web\BateriasController;
use App\Dominios\Mantenimiento\Infraestructura\Http\Controllers\Web\GeneradoresController;
use App\Dominios\Mantenimiento\Infraestructura\Http\Controllers\Web\VehiculosController;
use Illuminate\Database\Eloquent\Builder;

/**
 * Implementación Eloquent de {@see LecturaEstadoEquipamiento}. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaEquipamientoEloquent`: esa subcarpeta es solo para modelos.
 *
 * Los tonos no se vuelven a definir acá: se leen de `TONO_POR_ESTADO` de cada
 * controlador, que es donde los listados los declaran UNA vez (§6.3.4 de la
 * guía de pantalla), para que el dashboard nunca pinte distinto que el listado.
 */
final class LecturaEstadoEquipamientoEloquent implements LecturaEstadoEquipamiento
{
    private const ESTADO_DRON_OPERATIVO = 'operativo';

    private const ESTADO_DRON_EN_MANTENIMIENTO = 'en_mantenimiento';

    public function porIds(string $tipo, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return match ($tipo) {
            self::TIPO_DRON => $this->drones($ids),
            LecturaEquipamiento::TIPO_VEHICULO => $this->deCatalogo(
                Vehiculo::query(), $tipo, $ids, EstadoVehiculo::Activo->value, VehiculosController::TONO_POR_ESTADO, 'mantenimiento.estado',
            ),
            LecturaEquipamiento::TIPO_GENERADOR => $this->deCatalogo(
                Generador::query(), $tipo, $ids, EstadoGenerador::Activo->value, GeneradoresController::TONO_POR_ESTADO, 'mantenimiento.estado',
            ),
            LecturaEquipamiento::TIPO_BATERIA => $this->deCatalogo(
                Bateria::query(), $tipo, $ids, EstadoBateria::Activa->value, BateriasController::TONO_POR_ESTADO, 'mantenimiento.estado_bateria',
            ),
            default => [],
        };
    }

    /**
     * @param  Builder<Vehiculo>|Builder<Generador>|Builder<Bateria>  $consulta
     * @param  list<int>  $ids
     * @param  array<string, string>  $tonos
     * @return array<int, EstadoRecursoPanel>
     */
    private function deCatalogo(Builder $consulta, string $tipo, array $ids, string $estadoSano, array $tonos, string $espacioTextos): array
    {
        $estados = [];

        foreach ($consulta->whereIn('id', $ids)->get() as $modelo) {
            $id = (int) $modelo->getKey();
            $estado = (string) $modelo->getAttribute('estado');

            $estados[$id] = new EstadoRecursoPanel(
                tipo: $tipo,
                id: $id,
                estado: $estado,
                etiqueta: __("{$espacioTextos}.{$estado}"),
                tono: $tonos[$estado] ?? 'neutral',
                operativo: $estado === $estadoSano,
            );
        }

        return $estados;
    }

    /**
     * Un dron con alguna orden de mantenimiento abierta está en mantenimiento;
     * sin ninguna, operativo. No se valida que el id exista en `ope_drones`
     * (es de Operaciones): quien pregunta trae ids que ya tiene asignados.
     *
     * @param  list<int>  $ids
     * @return array<int, EstadoRecursoPanel>
     */
    private function drones(array $ids): array
    {
        $enMantenimiento = OrdenMantenimiento::query()
            ->where('equipo_tipo', self::TIPO_DRON)
            ->whereIn('equipo_id', $ids)
            ->where('estado', EstadoOrdenMantenimiento::Abierta)
            ->pluck('equipo_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $estados = [];

        foreach ($ids as $id) {
            $abierto = in_array($id, $enMantenimiento, true);
            $estado = $abierto ? self::ESTADO_DRON_EN_MANTENIMIENTO : self::ESTADO_DRON_OPERATIVO;

            $estados[$id] = new EstadoRecursoPanel(
                tipo: self::TIPO_DRON,
                id: $id,
                estado: $estado,
                etiqueta: __("mantenimiento.estado_dron.{$estado}"),
                tono: $abierto ? 'neutral' : 'success',
                operativo: ! $abierto,
            );
        }

        return $estados;
    }
}
