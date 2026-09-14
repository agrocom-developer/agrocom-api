<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Frontera de lectura de `Operaciones` hacia otros módulos que necesitan
 * resolver un `trabajo` por su `uuid_cliente` sin importar el modelo
 * Eloquent `Trabajo` (ADR 0003, regla 2). Primer y hoy único consumidor:
 * `Mezclas\Infraestructura\EscrituraMezclasEloquent` (espec §7, HU-78, tarea
 * 94) — mismo dato que `EscrituraSincronizacionEloquent::registrarRecepcionCaldo()`
 * ya resuelve DENTRO del propio módulo con un `Trabajo::query()->where(...)`
 * directo; acá hace falta el contrato porque el consumidor está en otro
 * módulo.
 */
interface LecturaTrabajos
{
    /** Id de servidor del trabajo con ese `uuid_cliente`, o `null` si no existe todavía. */
    public function idPorUuidCliente(string $uuidCliente): ?int;
}
