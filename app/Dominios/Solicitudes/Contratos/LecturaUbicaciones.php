<?php

namespace App\Dominios\Solicitudes\Contratos;

/**
 * El catálogo oficial de departamentos y provincias de Bolivia (ADR 0045, punto 3),
 * de solo lectura. Lo leen la API pública del catálogo, la validación al publicar
 * una solicitud y los demás módulos que necesiten el nombre de una ubicación, sin
 * importar los modelos.
 */
interface LecturaUbicaciones
{
    /**
     * Todos los departamentos con sus provincias, por código.
     *
     * @return list<array{codigo: string, nombre: string, provincias: list<array{codigo: string, nombre: string}>}>
     */
    public function catalogo(): array;

    /** Nombre del departamento, o `null` si el código no existe. */
    public function nombreDeDepartamento(string $codigo): ?string;

    /** Nombre de la provincia, o `null` si el código no existe. */
    public function nombreDeProvincia(string $codigo): ?string;

    /** Si la provincia existe y pertenece a ese departamento. */
    public function provinciaEsDelDepartamento(string $departamentoCodigo, string $provinciaCodigo): bool;
}
