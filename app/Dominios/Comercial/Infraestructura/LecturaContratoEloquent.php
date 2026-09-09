<?php

namespace App\Dominios\Comercial\Infraestructura;

use App\Dominios\Campania\Contratos\LecturaCampania;
use App\Dominios\Comercial\Contratos\DatosResumenContrato;
use App\Dominios\Comercial\Contratos\LecturaContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;

/**
 * Implementación Eloquent del contrato de lectura de resumen de contrato.
 * Vive fuera de `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaLotesEloquent`: esa subcarpeta está reservada a modelos que
 * extienden `ModeloDominio` (`tests/Unit/ArquitecturaModulosTest.php` lo
 * exige) — esta clase no es un modelo, es el adaptador que el
 * `ServiceProvider` del módulo liga a {@see LecturaContrato}.
 *
 * `Contrato` y `Cliente` viven las dos en este módulo, así que sí usa la
 * relación Eloquent `cliente()` directo (ADR 0003, regla 3 solo restringe
 * relaciones CRUZADAS entre módulos). La campaña, en cambio, vive en el
 * módulo `Campania` — se resuelve por su contrato de lectura
 * {@see LecturaCampania}, mismo patrón que `Comercial\Aplicacion\CrearContrato`
 * usa para validar la campaña elegida (ADR 0015).
 */
final class LecturaContratoEloquent implements LecturaContrato
{
    public function __construct(private readonly LecturaCampania $lecturaCampania) {}

    public function obtenerResumen(int $contratoId): ?DatosResumenContrato
    {
        $contrato = Contrato::query()->with('cliente')->find($contratoId);

        if ($contrato === null) {
            return null;
        }

        $campania = $contrato->campania_id !== null
            ? $this->lecturaCampania->obtener($contrato->campania_id)
            : null;

        return new DatosResumenContrato(
            contratoId: $contrato->id,
            clienteId: $contrato->cliente_id,
            clienteNombre: $contrato->cliente->razon_social,
            campaniaId: $contrato->campania_id,
            campaniaCodigo: $campania?->codigo,
        );
    }
}
