<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\LecturaTrabajos;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;

/**
 * Implementación Eloquent de {@see LecturaTrabajos} (ADR 0003, regla 2).
 * Vive fuera de `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaOrdenesVigentesEloquent`/`EscrituraSincronizacionEloquent`: esa
 * subcarpeta está reservada a modelos que extienden `ModeloDominio`.
 */
final class LecturaTrabajosEloquent implements LecturaTrabajos
{
    public function idPorUuidCliente(string $uuidCliente): ?int
    {
        return Trabajo::query()->where('uuid_cliente', $uuidCliente)->value('id');
    }
}
