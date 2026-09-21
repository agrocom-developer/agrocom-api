<?php

namespace App\Dominios\Mantenimiento\Infraestructura;

use App\Dominios\Mantenimiento\Contratos\LecturaEquipamiento;
use App\Dominios\Mantenimiento\Contratos\RecursoCatalogo;
use App\Dominios\Mantenimiento\Dominio\EstadoBateria;
use App\Dominios\Mantenimiento\Dominio\EstadoGenerador;
use App\Dominios\Mantenimiento\Dominio\EstadoVehiculo;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Bateria;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Generador;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Vehiculo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Implementación Eloquent de {@see LecturaEquipamiento}. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaCiclosBateriaEloquent`: esa subcarpeta es solo para modelos.
 */
final class LecturaEquipamientoEloquent implements LecturaEquipamiento
{
    public function vehiculosDisponibles(): array
    {
        return $this->aCatalogo(self::TIPO_VEHICULO, $this->disponibles(self::TIPO_VEHICULO)->orderBy('identificador')->get()->all());
    }

    public function generadoresDisponibles(): array
    {
        return $this->aCatalogo(self::TIPO_GENERADOR, $this->disponibles(self::TIPO_GENERADOR)->orderBy('identificador')->get()->all());
    }

    public function bateriasDisponibles(): array
    {
        return $this->aCatalogo(self::TIPO_BATERIA, $this->disponibles(self::TIPO_BATERIA)->orderBy('identificador')->get()->all());
    }

    public function porIds(string $tipo, array $ids): array
    {
        $consulta = $this->consulta($tipo);

        if ($consulta === null || $ids === []) {
            return [];
        }

        $recursos = [];

        foreach ($this->aCatalogo($tipo, $consulta->whereIn('id', $ids)->get()->all()) as $recurso) {
            $recursos[$recurso->id] = $recurso;
        }

        return $recursos;
    }

    public function estaDisponible(string $tipo, int $id): bool
    {
        return $this->consulta($tipo) !== null
            && $this->disponibles($tipo)->whereKey($id)->exists();
    }

    /** @return Builder<Vehiculo>|Builder<Generador>|Builder<Bateria>|null */
    private function consulta(string $tipo): ?Builder
    {
        return match ($tipo) {
            self::TIPO_VEHICULO => Vehiculo::query(),
            self::TIPO_GENERADOR => Generador::query(),
            self::TIPO_BATERIA => Bateria::query(),
            default => null,
        };
    }

    /** @return Builder<Vehiculo>|Builder<Generador>|Builder<Bateria> */
    private function disponibles(string $tipo): Builder
    {
        return match ($tipo) {
            self::TIPO_VEHICULO => Vehiculo::query()->where('estado', EstadoVehiculo::Activo->value),
            self::TIPO_GENERADOR => Generador::query()->where('estado', EstadoGenerador::Activo->value),
            default => Bateria::query()->where('estado', EstadoBateria::Activa->value),
        };
    }

    /**
     * @param  list<Vehiculo|Generador|Bateria>  $modelos
     * @return list<RecursoCatalogo>
     */
    private function aCatalogo(string $tipo, array $modelos): array
    {
        return array_map(fn (Vehiculo|Generador|Bateria $modelo): RecursoCatalogo => new RecursoCatalogo(
            id: (int) $modelo->id,
            identificador: (string) $modelo->identificador,
            detalle: $this->detalle($tipo, $modelo),
        ), $modelos);
    }

    private function detalle(string $tipo, Vehiculo|Generador|Bateria $modelo): ?string
    {
        $partes = match ($tipo) {
            self::TIPO_VEHICULO => [$modelo->getAttribute('marca'), $modelo->getAttribute('modelo')],
            self::TIPO_GENERADOR => [$modelo->getAttribute('modelo')],
            default => [],
        };

        $texto = trim(implode(' ', array_filter($partes, fn ($parte) => $parte !== null && $parte !== '')));

        return $texto === '' ? null : $texto;
    }
}
