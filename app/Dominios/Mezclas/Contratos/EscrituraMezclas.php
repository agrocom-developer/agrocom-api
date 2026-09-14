<?php

namespace App\Dominios\Mezclas\Contratos;

use App\Dominios\Operaciones\Contratos\ResultadoSincronizacion;

/**
 * Contrato de escritura de `Mezclas` para el motor de sync (ADR 0003, regla
 * 2; espec §7, HU-78, tarea 94 — "qué no delegar sin revisión línea por
 * línea" de CLAUDE.md). `Sincronizacion` invoca esto para aplicar el
 * registro `mezcla` del lote de `POST /api/sync`; nunca escribe
 * `mez_mezclas`/`mez_mezcla_detalles` directamente ni conoce el modelo
 * Eloquent.
 *
 * Reutiliza `Operaciones\Contratos\ResultadoSincronizacion` en vez de
 * duplicarlo: es el value object primitivo de PLATAFORMA del motor de sync
 * (los tres únicos estados `aplicado`/`duplicado`/`rechazado`), no una regla
 * de negocio de `Operaciones` — consumir el `Contratos/` de otro módulo es
 * exactamente el cruce sancionado por ADR 0003 regla 2, el mismo que ya usa
 * `Sincronizacion\Aplicacion\SincronizarLote`.
 *
 * `registrarMezcla()` crea una fila de cabecera nueva (y sus detalles, en la
 * misma transacción): `duplicado` se resuelve capturando la violación del
 * `UNIQUE` parcial de `uuid_cliente` de `mez_mezclas` (invariante 1 de
 * CLAUDE.md) — nunca con un `SELECT` previo.
 */
interface EscrituraMezclas
{
    public function registrarMezcla(RegistroMezcla $datos): ResultadoSincronizacion;
}
