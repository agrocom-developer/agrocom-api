<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * DTO primitivo de entrada del contrato de escritura de `Operaciones` (ADR
 * 0003, regla 2; HU-13, tarea 23): la forma de un registro `recarga` en el
 * lote de `POST /api/sync`.
 *
 * Mismo criterio que `RegistroCondiciones`/`RegistroRecepcionCaldo`:
 * `$sesionUuidCliente` referencia la sesión por el `uuid_cliente` de su
 * apertura, nunca por id de servidor — puede haber llegado en el mismo
 * lote. Sin `$operarioPersonaId` en
 * `EscrituraSincronizacion::registrarRecarga()`: la espec no define un dueño
 * individual para este registro (el auxiliar registra, pero no hay noción
 * de "pertenencia" del tipo `piloto_id` a verificar), mismo criterio que
 * `condiciones`/`recepcion_caldo`.
 *
 * `$motivoRetrasoCaldo` reencuadra el `problema_caldo` de la espec §4.3
 * (escrita antes de CR-01): la CA vigente (`plan_sprints.md`) lo pide como
 * "motivo del retraso/rechazo por caldo", nullable — solo se completa si
 * hubo retraso, no en cada recarga. Sin `mezcla_id`: la recarga (cambio de
 * batería o carga adicional de caldo durante el vuelo) es un evento
 * independiente del registro `mezcla` (espec §7, HU-78, tarea 94) que
 * transcribe qué se cargó al abrir el trabajo — no hace falta vincular ambos
 * para esta tarea.
 *
 * `$bateriaSalienteId` es texto libre, no una FK: no existe catálogo de
 * baterías en el esquema (el prompt de la tarea lo prohíbe explícitamente).
 */
final readonly class RegistroRecarga
{
    /** Reencuadre de `problema_caldo` (espec §4.3) sin el valor `ninguno` — `null` ya representa "sin motivo". */
    private const array MOTIVOS_RETRASO_CALDO = [
        'filtro_tapado',
        'grumos',
        'decantacion',
        'espuma',
        'color_olor_anormal',
    ];

    /** CA de HU-13: "alerta local si temperatura > 50 °C" — no bloquea, ver {@see self::alertaTemperatura()}. */
    public const float TEMPERATURA_MAX_C = 50.0;

    private function __construct(
        public string $uuidCliente,
        public string $sesionUuidCliente,
        public int $secuencia,
        public string $litrosCaldo,
        public string $bateriaSalienteId,
        public string $temperaturaBateriaC,
        public ?string $motivoRetrasoCaldo,
        public ?string $horaRetraso,
        public ?string $litrosCombustibleGenerador,
        public string $hora,
    ) {}

    /** @param  array<string, mixed>  $datos */
    public static function intentarDesdeArreglo(array $datos): ?self
    {
        if (! self::esStringNoVacio($datos['uuid_cliente'] ?? null)
            || ! self::esStringNoVacio($datos['sesion_uuid_cliente'] ?? null)
            || ! self::esEntero($datos['secuencia'] ?? null)
            || ! self::esNumeroNoNegativo($datos['litros_caldo'] ?? null)
            || ! self::esStringNoVacio($datos['bateria_saliente_id'] ?? null)
            || ! self::esNumero($datos['temperatura_bateria_c'] ?? null)
            || ! self::esMotivoRetrasoOAusente($datos['motivo_retraso_caldo'] ?? null)
            || ! self::esStringOAusente($datos['hora_retraso'] ?? null)
            || ! self::esNumeroNoNegativoOAusente($datos['litros_combustible_generador'] ?? null)
            || ! self::esStringNoVacio($datos['hora'] ?? null)
        ) {
            return null;
        }

        return new self(
            uuidCliente: (string) $datos['uuid_cliente'],
            sesionUuidCliente: (string) $datos['sesion_uuid_cliente'],
            secuencia: (int) $datos['secuencia'],
            litrosCaldo: (string) $datos['litros_caldo'],
            bateriaSalienteId: (string) $datos['bateria_saliente_id'],
            temperaturaBateriaC: (string) $datos['temperatura_bateria_c'],
            motivoRetrasoCaldo: self::esStringNoVacio($datos['motivo_retraso_caldo'] ?? null) ? (string) $datos['motivo_retraso_caldo'] : null,
            horaRetraso: self::esStringNoVacio($datos['hora_retraso'] ?? null) ? (string) $datos['hora_retraso'] : null,
            litrosCombustibleGenerador: isset($datos['litros_combustible_generador']) ? (string) $datos['litros_combustible_generador'] : null,
            hora: (string) $datos['hora'],
        );
    }

    /** CA de HU-13: "alerta local si temperatura > 50 °C" — persiste igual, nunca rechaza. */
    public function alertaTemperatura(): bool
    {
        return (float) $this->temperaturaBateriaC > self::TEMPERATURA_MAX_C;
    }

    private static function esStringNoVacio(mixed $valor): bool
    {
        return is_string($valor) && $valor !== '';
    }

    private static function esStringOAusente(mixed $valor): bool
    {
        return $valor === null || is_string($valor);
    }

    private static function esEntero(mixed $valor): bool
    {
        return is_int($valor) || (is_string($valor) && ctype_digit($valor));
    }

    private static function esMotivoRetrasoOAusente(mixed $valor): bool
    {
        if ($valor === null || $valor === '') {
            return true;
        }

        return is_string($valor) && in_array($valor, self::MOTIVOS_RETRASO_CALDO, true);
    }

    /** Numérico, sin restricción de signo (la temperatura puede ser negativa). */
    private static function esNumero(mixed $valor): bool
    {
        if (is_int($valor) || is_float($valor)) {
            return true;
        }

        return is_string($valor) && $valor !== '' && is_numeric($valor);
    }

    /** Forma de un `DECIMAL` no negativo (invariante 6 de CLAUDE.md). */
    private static function esNumeroNoNegativo(mixed $valor): bool
    {
        if (is_int($valor) || is_float($valor)) {
            return $valor >= 0;
        }

        return is_string($valor) && $valor !== '' && is_numeric($valor) && (float) $valor >= 0;
    }

    private static function esNumeroNoNegativoOAusente(mixed $valor): bool
    {
        return $valor === null || self::esNumeroNoNegativo($valor);
    }
}
