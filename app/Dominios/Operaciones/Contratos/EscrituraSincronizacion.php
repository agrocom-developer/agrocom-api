<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Contrato de escritura de `Operaciones` para el motor de sync (ADR 0003,
 * regla 2; TE-05/HU-05, tareas 09 y 13 — "qué no delegar sin revisión línea
 * por línea" de CLAUDE.md). `Sincronizacion` invoca esto para aplicar cada
 * registro del lote de `POST /api/sync`; nunca escribe
 * `ope_trabajos`/`ope_sesiones` directamente ni conoce el modelo Eloquent —
 * solo estos métodos y los DTOs primitivos que reciben.
 *
 * `abrirTrabajo()`/`abrirSesion()` crean una fila nueva: `duplicado` se
 * resuelve capturando la violación del `UNIQUE` parcial por `uuid_cliente`
 * (invariante 1 de CLAUDE.md) — nunca con un `SELECT` previo.
 *
 * `cerrarTrabajo()`/`cerrarSesion()` (HU-05, tarea 13) MUTAN una fila
 * existente — no hay `INSERT` que rechace un duplicado por sí solo, así que
 * la idempotencia y la verificación de pertenencia se resuelven dentro de la
 * implementación (ver `EscrituraSincronizacionEloquent` y runs/13.md), no
 * antes de invocarla como pasa con `abrirSesion()`/`piloto_id`. Por eso
 * ambas reciben `$operarioPersonaId` explícito, igual que
 * `SincronizarLote::ejecutar()`: nunca un modo "sin verificar" alcanzable
 * por omitir un parámetro opcional.
 */
interface EscrituraSincronizacion
{
    public function abrirTrabajo(AperturaTrabajo $datos): ResultadoSincronizacion;

    public function abrirSesion(AperturaSesion $datos): ResultadoSincronizacion;

    public function cerrarTrabajo(CierreTrabajo $datos, ?int $operarioPersonaId): ResultadoSincronizacion;

    public function cerrarSesion(CierreSesion $datos, ?int $operarioPersonaId): ResultadoSincronizacion;
}
