<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\DronCatalogo;
use App\Dominios\Operaciones\Contratos\LecturaDrones;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;

/**
 * Implementación Eloquent de {@see LecturaDrones}. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaOrdenesVigentesEloquent`: esa subcarpeta está reservada a modelos
 * que extienden `ModeloDominio` (`tests/Unit/ArquitecturaModulosTest.php` lo
 * exige), y esta clase no es un modelo.
 *
 * `Dron` no tiene columna de estado/activo propia (ver su docblock: "sin
 * ciclo de vida propio"), a diferencia de `Vehiculo`/`Generador`/`Bateria`
 * (`LecturaEquipamientoEloquent`) — "disponible" acá es solo "no dado de baja"
 * (soft delete, filtrado por defecto por `ModeloDominio`).
 */
final class LecturaDronesEloquent implements LecturaDrones
{
    public function disponibles(): array
    {
        return Dron::query()
            ->orderBy('identificador')
            ->get()
            ->map(self::aCatalogo(...))
            ->all();
    }

    public function porIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $drones = [];

        foreach (Dron::query()->whereIn('id', $ids)->get() as $dron) {
            $drones[$dron->id] = self::aCatalogo($dron);
        }

        return $drones;
    }

    public function porIdentificador(string $identificador): ?DronCatalogo
    {
        $dron = Dron::query()->where('identificador', $identificador)->first();

        return $dron === null ? null : self::aCatalogo($dron);
    }

    public function estaDisponible(int $id): bool
    {
        return Dron::query()->whereKey($id)->exists();
    }

    private static function aCatalogo(Dron $dron): DronCatalogo
    {
        return new DronCatalogo(
            id: $dron->id,
            identificador: $dron->identificador,
            modelo: $dron->modelo,
        );
    }
}
