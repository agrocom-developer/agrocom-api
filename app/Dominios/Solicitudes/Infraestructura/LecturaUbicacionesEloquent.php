<?php

namespace App\Dominios\Solicitudes\Infraestructura;

use App\Dominios\Solicitudes\Contratos\LecturaUbicaciones;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Departamento;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Provincia;

/** Lectura del catálogo de ubicaciones (ver {@see LecturaUbicaciones}). */
final class LecturaUbicacionesEloquent implements LecturaUbicaciones
{
    /** {@inheritDoc} */
    public function catalogo(): array
    {
        return Departamento::query()
            ->with('provincias')
            ->orderBy('codigo')
            ->get()
            ->map(static fn (Departamento $departamento): array => [
                'codigo' => $departamento->codigo,
                'nombre' => $departamento->nombre,
                'provincias' => $departamento->provincias
                    ->map(static fn (Provincia $provincia): array => ['codigo' => $provincia->codigo, 'nombre' => $provincia->nombre])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    public function nombreDeDepartamento(string $codigo): ?string
    {
        return Departamento::query()->where('codigo', $codigo)->value('nombre');
    }

    public function nombreDeProvincia(string $codigo): ?string
    {
        return Provincia::query()->where('codigo', $codigo)->value('nombre');
    }

    public function provinciaEsDelDepartamento(string $departamentoCodigo, string $provinciaCodigo): bool
    {
        return Provincia::query()
            ->where('codigo', $provinciaCodigo)
            ->where('departamento_codigo', $departamentoCodigo)
            ->exists();
    }
}
