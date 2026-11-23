<?php

namespace App\Dominios\Mantenimiento\Infraestructura;

use App\Dominios\Mantenimiento\Contratos\LecturaEquipamiento;
use App\Dominios\Mantenimiento\Contratos\RecursoCatalogo;
use App\Dominios\Mantenimiento\Dominio\EstadoBateria;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Bateria;
use Illuminate\Database\Eloquent\Builder;

/**
 * Implementación Eloquent de {@see LecturaEquipamiento}. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito: esa subcarpeta es solo para modelos.
 */
final class LecturaEquipamientoEloquent implements LecturaEquipamiento
{
    public function bateriasDisponibles(): array
    {
        return $this->aCatalogo($this->disponibles()->orderBy('identificador')->get()->all());
    }

    public function porIds(string $tipo, array $ids): array
    {
        if ($tipo !== self::TIPO_BATERIA || $ids === []) {
            return [];
        }

        $recursos = [];

        foreach ($this->aCatalogo(Bateria::query()->whereIn('id', $ids)->get()->all()) as $recurso) {
            $recursos[$recurso->id] = $recurso;
        }

        return $recursos;
    }

    public function estaDisponible(string $tipo, int $id): bool
    {
        return $tipo === self::TIPO_BATERIA && $this->disponibles()->whereKey($id)->exists();
    }

    /** @return Builder<Bateria> */
    private function disponibles(): Builder
    {
        return Bateria::query()->where('estado', EstadoBateria::Activa->value);
    }

    /**
     * @param  list<Bateria>  $baterias
     * @return list<RecursoCatalogo>
     */
    private function aCatalogo(array $baterias): array
    {
        return array_map(fn (Bateria $bateria): RecursoCatalogo => new RecursoCatalogo(
            id: (int) $bateria->id,
            identificador: (string) $bateria->identificador,
            detalle: null,
        ), $baterias);
    }
}
