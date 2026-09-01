<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * DTO primitivo de entrada del contrato de escritura de `Operaciones` (ADR
 * 0003, regla 2; HU-06, tarea 17): la forma de un registro `condiciones` en
 * el lote de `POST /api/sync`.
 *
 * Mismo criterio que `CierreSesion`: `$sesionUuidCliente` referencia la
 * sesión por el `uuid_cliente` de su apertura, nunca por id de servidor —
 * puede haber llegado en el mismo lote. `$trabajoId` NO viaja acá: la espec
 * (§4.3) lo lista en `condiciones` como columna denormalizada de
 * `sesion.trabajo_id`, así que resolverlo es responsabilidad de
 * `EscrituraSincronizacion::registrarCondiciones()` (que ya tiene que leer la
 * fila de la sesión para validar que existe), no un dato que el cliente
 * declare dos veces.
 *
 * `$momento` solo acepta `'inicio_sesion'` (alcance de esta tarea —
 * `incidencia` es HU-08). `intentarDesdeArreglo()` devuelve `null` ante un
 * dato faltante o mal tipado, igual que el resto de los DTOs de este
 * contrato — el registro se rechaza sin frenar el resto del lote.
 *
 * La decisión de "autoriza / autoriza con observación / rechaza" NO vive acá
 * (este DTO solo valida FORMA): vive en `EscrituraSincronizacionEloquent`,
 * que combina {@see self::dentroDeRango()} y
 * {@see self::tieneObservacionFirmada()} — ver runs/17.md para el porqué
 * completo de dónde vive cada pieza de esta decisión.
 */
final readonly class RegistroCondiciones
{
    /** Alcance de esta tarea (HU-06): `incidencia` es HU-08, sprint 3, tarea aparte. */
    private const array MOMENTOS = ['inicio_sesion'];

    /**
     * Rangos que autorizan sin intervención del agrónomo (espec §5, tabla de
     * transiciones: "condiciones dentro de rango").
     */
    public const float VIENTO_MAX_KMH = 17.0;

    public const float TEMPERATURA_MAX_C = 30.0;

    public const float HUMEDAD_MAX_PCT = 90.0;

    private function __construct(
        public string $uuidCliente,
        public string $sesionUuidCliente,
        public string $momento,
        public string $vientoKmh,
        public string $temperaturaC,
        public string $humedadPct,
        public ?string $observacionAgronomo,
        public ?string $firmaObservacion,
    ) {}

    /** @param  array<string, mixed>  $datos */
    public static function intentarDesdeArreglo(array $datos): ?self
    {
        if (! self::esStringNoVacio($datos['uuid_cliente'] ?? null)
            || ! self::esStringNoVacio($datos['sesion_uuid_cliente'] ?? null)
            || ! is_string($datos['momento'] ?? null)
            || ! in_array($datos['momento'], self::MOMENTOS, true)
            || ! self::esNumeroNoNegativo($datos['viento_kmh'] ?? null)
            || ! self::esNumero($datos['temperatura_c'] ?? null)
            || ! self::esNumeroNoNegativo($datos['humedad_pct'] ?? null)
            || ! self::esStringOAusente($datos['observacion_agronomo'] ?? null)
            || ! self::esStringOAusente($datos['firma_observacion'] ?? null)
        ) {
            return null;
        }

        return new self(
            uuidCliente: (string) $datos['uuid_cliente'],
            sesionUuidCliente: (string) $datos['sesion_uuid_cliente'],
            momento: (string) $datos['momento'],
            vientoKmh: (string) $datos['viento_kmh'],
            temperaturaC: (string) $datos['temperatura_c'],
            humedadPct: (string) $datos['humedad_pct'],
            observacionAgronomo: self::esStringNoVacio($datos['observacion_agronomo'] ?? null) ? (string) $datos['observacion_agronomo'] : null,
            firmaObservacion: self::esStringNoVacio($datos['firma_observacion'] ?? null) ? (string) $datos['firma_observacion'] : null,
        );
    }

    /** Espec §5: "condiciones dentro de rango" — autoriza sin observación. */
    public function dentroDeRango(): bool
    {
        return (float) $this->vientoKmh <= self::VIENTO_MAX_KMH
            && (float) $this->temperaturaC <= self::TEMPERATURA_MAX_C
            && (float) $this->humedadPct <= self::HUMEDAD_MAX_PCT;
    }

    /**
     * Espec §5: "observación firmada por el agrónomo" — ambos campos
     * presentes, no alcanza con uno solo.
     */
    public function tieneObservacionFirmada(): bool
    {
        return $this->observacionAgronomo !== null && $this->firmaObservacion !== null;
    }

    private static function esStringNoVacio(mixed $valor): bool
    {
        return is_string($valor) && $valor !== '';
    }

    private static function esStringOAusente(mixed $valor): bool
    {
        return $valor === null || is_string($valor);
    }

    /** Numérico, sin restricción de signo (la temperatura puede ser negativa). */
    private static function esNumero(mixed $valor): bool
    {
        if (is_int($valor) || is_float($valor)) {
            return true;
        }

        return is_string($valor) && $valor !== '' && is_numeric($valor);
    }

    /**
     * Forma de un `DECIMAL` no negativo (invariante 6 de CLAUDE.md), y
     * además una magnitud física que no puede ser negativa (viento, humedad).
     */
    private static function esNumeroNoNegativo(mixed $valor): bool
    {
        if (is_int($valor) || is_float($valor)) {
            return $valor >= 0;
        }

        return is_string($valor) && $valor !== '' && is_numeric($valor) && (float) $valor >= 0;
    }
}
