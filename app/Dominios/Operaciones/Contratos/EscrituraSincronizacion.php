<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Contrato de escritura de `Operaciones` para el motor de sync (ADR 0003,
 * regla 2; TE-05, tarea 09 — "qué no delegar sin revisión línea por línea" de
 * CLAUDE.md). `Sincronizacion` invoca esto para aplicar cada registro del
 * lote de `POST /api/sync`; nunca escribe `ope_trabajos`/`ope_sesiones`
 * directamente ni conoce el modelo Eloquent — solo estos dos métodos y los
 * DTOs primitivos que reciben.
 *
 * `duplicado` se resuelve capturando la violación del `UNIQUE` parcial por
 * `uuid_cliente` (invariante 1 de CLAUDE.md) — nunca con un `SELECT` previo.
 */
interface EscrituraSincronizacion
{
    public function abrirTrabajo(AperturaTrabajo $datos): ResultadoSincronizacion;

    public function abrirSesion(AperturaSesion $datos): ResultadoSincronizacion;
}
