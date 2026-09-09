<?php

namespace App\Dominios\Operaciones\Contratos;

use App\Dominios\Operaciones\Dominio\TipoIncidencia;

/**
 * DTO primitivo de entrada del contrato de escritura de `Operaciones` (ADR
 * 0003, regla 2; HU-08, tarea 22): la forma de un registro `incidencia` en
 * el lote de `POST /api/sync`.
 *
 * Mismo criterio que `RegistroCondiciones`/`RegistroRecepcionCaldo`:
 * `$sesionUuidCliente` referencia la sesión por el `uuid_cliente` de su
 * apertura, nunca por id de servidor — puede haber llegado en el mismo
 * lote. Sin `$operarioPersonaId` en
 * `EscrituraSincronizacion::registrarIncidencia()`: la espec no define un
 * dueño individual de este registro — piloto, auxiliar y jefe de campo
 * pueden registrar incidencias por igual (§2, misma tabla de roles que ya
 * vale para `condiciones`).
 *
 * `$evidenciaFotoUuidCliente` es OBLIGATORIO, a diferencia de
 * `CierreTrabajo::$evidenciaImagenCampoUuidCliente` que también lo es por el
 * mismo motivo: el título de la HU ("con foto") y su propósito ("respaldar
 * el reporte") son la condición de la transición, no un dato opcional.
 * Referencia por `uuid_cliente` una evidencia ya subida vía
 * `POST /api/evidencias` con `tipo: foto_incidencia` — este DTO solo valida
 * FORMA ("string no vacío"); que exista, sea del tipo correcto y no esté ya
 * usada por otra incidencia lo valida
 * `EscrituraSincronizacionEloquent::registrarIncidencia()`, que sí puede leer
 * la base.
 *
 * Decisión propia (no estaba en la espec ni en el prompt): la propiedad PHP
 * se llama `$tipo`, fiel al nombre de columna de la espec (§4.3), pero el
 * campo del JSON de entrada es `tipo_incidencia`, NO `tipo` — a diferencia de
 * `RegistroCondiciones::$momento`/`CierreSesion::$motivoCierre`, que no
 * colisionan con nada, `tipo` YA es la key que `SincronizarLote::ejecutar()`
 * usa como "sobre" para decidir el TIPO DE REGISTRO del lote (`'incidencia'`
 * en este caso). Un mismo objeto JSON no puede traer dos keys `tipo` con
 * significados distintos — el parser se quedaría con el último valor y
 * rompería el enrutamiento por tipo de registro. `tipo_incidencia` evita la
 * colisión sin tocar el mecanismo de enrutamiento existente.
 */
final readonly class RegistroIncidencia
{
    private function __construct(
        public string $uuidCliente,
        public string $sesionUuidCliente,
        public string $tipo,
        public ?string $descripcion,
        public string $hora,
        public string $evidenciaFotoUuidCliente,
    ) {}

    /** @param  array<string, mixed>  $datos */
    public static function intentarDesdeArreglo(array $datos): ?self
    {
        if (! self::esStringNoVacio($datos['uuid_cliente'] ?? null)
            || ! self::esStringNoVacio($datos['sesion_uuid_cliente'] ?? null)
            || ! is_string($datos['tipo_incidencia'] ?? null)
            || TipoIncidencia::tryFrom($datos['tipo_incidencia']) === null
            || ! self::esStringOAusente($datos['descripcion'] ?? null)
            || ! self::esStringNoVacio($datos['hora'] ?? null)
            || ! self::esStringNoVacio($datos['evidencia_foto_uuid_cliente'] ?? null)
        ) {
            return null;
        }

        return new self(
            uuidCliente: (string) $datos['uuid_cliente'],
            sesionUuidCliente: (string) $datos['sesion_uuid_cliente'],
            tipo: (string) $datos['tipo_incidencia'],
            descripcion: self::esStringNoVacio($datos['descripcion'] ?? null) ? (string) $datos['descripcion'] : null,
            hora: (string) $datos['hora'],
            evidenciaFotoUuidCliente: (string) $datos['evidencia_foto_uuid_cliente'],
        );
    }

    private static function esStringNoVacio(mixed $valor): bool
    {
        return is_string($valor) && $valor !== '';
    }

    private static function esStringOAusente(mixed $valor): bool
    {
        return $valor === null || is_string($valor);
    }
}
