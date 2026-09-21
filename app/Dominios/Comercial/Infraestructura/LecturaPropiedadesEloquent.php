<?php

namespace App\Dominios\Comercial\Infraestructura;

use App\Dominios\Comercial\Contratos\LecturaPropiedades;
use App\Dominios\Comercial\Contratos\PropiedadCatalogo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use Illuminate\Database\Eloquent\Builder;

/**
 * Implementación Eloquent de {@see LecturaPropiedades}. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaLotesEloquent`: esa subcarpeta es solo para modelos.
 *
 * `cliente()` es una relación Eloquent NORMAL (`Cliente` vive en el mismo
 * módulo, ADR 0003 regla 1) — se precarga siempre para no N+1 al armar
 * `clienteNombre`.
 */
final class LecturaPropiedadesEloquent implements LecturaPropiedades
{
    public function disponibles(): array
    {
        return Propiedad::query()
            ->with('cliente')
            ->orderBy('nombre')
            ->get()
            ->map(self::aCatalogo(...))
            ->all();
    }

    public function porIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $propiedades = [];

        foreach (Propiedad::query()->with('cliente')->whereIn('id', $ids)->get() as $propiedad) {
            $propiedades[$propiedad->id] = self::aCatalogo($propiedad);
        }

        return $propiedades;
    }

    public function idsQueCoinciden(string $texto): array
    {
        if (trim($texto) === '') {
            return [];
        }

        $patron = '%'.mb_strtolower($texto).'%';

        return Propiedad::query()
            ->where(function (Builder $consulta) use ($patron): void {
                $consulta
                    ->whereRaw('LOWER(nombre) LIKE ?', [$patron])
                    ->orWhereHas('cliente', function (Builder $consulta) use ($patron): void {
                        $consulta->whereRaw('LOWER(razon_social) LIKE ?', [$patron]);
                    });
            })
            ->pluck('id')
            ->map(fn (int|string $id): int => (int) $id)
            ->all();
    }

    private static function aCatalogo(Propiedad $propiedad): PropiedadCatalogo
    {
        return new PropiedadCatalogo(
            id: $propiedad->id,
            nombre: $propiedad->nombre,
            clienteId: $propiedad->cliente_id,
            clienteNombre: $propiedad->cliente->razon_social ?? '',
        );
    }
}
