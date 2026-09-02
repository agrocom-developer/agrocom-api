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
 *
 * `registrarCondiciones()` (HU-06, tarea 17) crea una fila nueva, mismo
 * mecanismo de idempotencia que `abrirTrabajo()`/`abrirSesion()` — pero, a
 * diferencia de esos dos, puede terminar en `rechazado` por una razón
 * adicional a "dato inválido o referencia inexistente": condiciones fuera de
 * rango sin observación firmada del agrónomo (espec §5) nunca llegan a
 * persistirse. Sin `$operarioPersonaId`: la espec no define una noción de
 * pertenencia para este registro (piloto y jefe de campo pueden registrar
 * condiciones por igual, ver §2 tabla de acciones por rol), así que no hay
 * nada que verificar acá — mismo criterio que `abrirTrabajo()`.
 *
 * `registrarRecepcionCaldo()` (espec §7.2, HU-10 redefinida por CR-01, tarea
 * 18) crea una fila nueva, mismo mecanismo de idempotencia que
 * `abrirTrabajo()`/`registrarCondiciones()`. Sin `$operarioPersonaId`, mismo
 * motivo que `registrarCondiciones()`: la espec no define dueño para este
 * registro. `litros_consumidos`/`litros_sobrante` NO tienen un método propio
 * — viajan como campos opcionales de `CierreSesion`/`CierreTrabajo` (ver esos
 * DTOs y runs/18.md, "decisión de esquema"), así que `cerrarSesion()`/
 * `cerrarTrabajo()` ya los cubren sin ampliar esta interfaz.
 *
 * `registrarRecarga()` (HU-13, tarea 23) crea una fila nueva, mismo
 * mecanismo de idempotencia que `registrarCondiciones()`/
 * `registrarRecepcionCaldo()`. Sin `$operarioPersonaId`, mismo motivo que
 * esos dos: la espec no define dueño para este registro. A diferencia de
 * `registrarCondiciones()`, nunca rechaza por la medición en sí (temperatura
 * de batería alta): persiste una alerta calculada, no bloquea el registro.
 */
interface EscrituraSincronizacion
{
    public function abrirTrabajo(AperturaTrabajo $datos): ResultadoSincronizacion;

    public function abrirSesion(AperturaSesion $datos): ResultadoSincronizacion;

    public function cerrarTrabajo(CierreTrabajo $datos, ?int $operarioPersonaId): ResultadoSincronizacion;

    public function cerrarSesion(CierreSesion $datos, ?int $operarioPersonaId): ResultadoSincronizacion;

    public function registrarCondiciones(RegistroCondiciones $datos): ResultadoSincronizacion;

    public function registrarRecepcionCaldo(RegistroRecepcionCaldo $datos): ResultadoSincronizacion;

    public function registrarRecarga(RegistroRecarga $datos): ResultadoSincronizacion;
}
