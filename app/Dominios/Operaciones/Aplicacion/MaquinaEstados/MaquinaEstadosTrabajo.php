<?php

namespace App\Dominios\Operaciones\Aplicacion\MaquinaEstados;

use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\Excepciones\TransicionTrabajoNoPermitida;
use App\Dominios\Operaciones\Dominio\MaquinaEstados\TransicionesTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use Carbon\CarbonImmutable;

/**
 * Única clase que crea/muta el `estado` de `trabajo` (invariante 7 de
 * CLAUDE.md).
 *
 * La idempotencia (¿este `uuid_cliente` ya existe?) no es responsabilidad de
 * esta clase: se apoya en el `UNIQUE` parcial de la migración y quien invoca
 * `abrir()` (el contrato de escritura de `Operaciones`, TE-05) es quien
 * envuelve la llamada en su propia transacción y traduce la violación de
 * unicidad en `duplicado` — la máquina de estados solo sabe crear con el
 * estado inicial correcto.
 *
 * `cerrar()` (HU-05, tarea 13) es la contraparte: consulta
 * {@see TransicionesTrabajo::permitida()} antes de escribir y lanza
 * {@see TransicionTrabajoNoPermitida} si la transición no está permitida
 * (p. ej. cerrar algo ya cerrado) — nunca deja pasar un `estado = ...`
 * inválido. La decisión de SI corresponde cerrar (idempotencia del evento de
 * cierre, pertenencia) es de quien invoca, no de esta clase: acá solo se
 * aplica la transición o se rechaza.
 */
final class MaquinaEstadosTrabajo
{
    /**
     * @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase.
     */
    public function abrir(array $atributos): Trabajo
    {
        $atributos['inicio'] = self::normalizarUtc($atributos['inicio'] ?? null);
        $atributos['fin'] = self::normalizarUtc($atributos['fin'] ?? null);

        return Trabajo::create([...$atributos, 'estado' => EstadoTrabajo::Abierto]);
    }

    /**
     * @throws TransicionTrabajoNoPermitida si `$trabajo` no está `abierto`.
     */
    public function cerrar(Trabajo $trabajo, string $cierreUuidCliente, string $fin): Trabajo
    {
        $desde = $trabajo->estado;
        $hasta = EstadoTrabajo::Cerrado;

        if (! TransicionesTrabajo::permitida($desde, $hasta)) {
            throw TransicionTrabajoNoPermitida::entre($desde, $hasta);
        }

        $trabajo->estado = $hasta;
        $trabajo->cierre_uuid_cliente = $cierreUuidCliente;
        $trabajo->fin = self::normalizarUtc($fin);
        $trabajo->save();

        return $trabajo;
    }

    /**
     * `CarbonImmutable::parse()` conserva el offset original del string
     * entrante (p. ej. `-04:00`) como huso horario del objeto, no lo
     * normaliza — y `ope_trabajos.inicio`/`fin` son `dateTime` sin tz. Sin
     * este `->utc()`, `format()` escribiría la hora LOCAL literal
     * ("16:30:00") y una relectura posterior la interpretaría como UTC,
     * corriendo el instante real por el valor del offset. Mismo mecanismo
     * (y mismo fix) que `MaquinaEstadosActa::firmar()` ya aplica a
     * `fecha_firma` (runs/24.md).
     */
    private static function normalizarUtc(string|\DateTimeInterface|null $valor): ?CarbonImmutable
    {
        return $valor === null ? null : CarbonImmutable::parse($valor)->utc();
    }
}
