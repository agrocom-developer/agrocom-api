<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\DatosActaConformada;
use App\Dominios\Operaciones\Contratos\LecturaActaConformada;
use App\Dominios\Operaciones\Dominio\EstadoActa;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Acta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;

/**
 * Implementación Eloquent del contrato de lectura de acta conformada. Vive
 * fuera de `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaSesionValidadaEloquent`: esa subcarpeta está reservada a modelos
 * que extienden `ModeloDominio` (`tests/Unit/ArquitecturaModulosTest.php` lo
 * exige) — esta clase no es un modelo, es el adaptador que el
 * `ServiceProvider` del módulo liga a {@see LecturaActaConformada}.
 *
 * Resuelve `contrato_id` subiendo la cadena `Acta.trabajo_id →
 * Trabajo.orden_id → OrdenAplicacion.contrato_id` con tres consultas por
 * acta, sin JOIN cross-tabla optimizado a propósito: el volumen de actas
 * facturables no lo justifica, y las tres tablas son propias del módulo, así
 * que no hay costo de acoplamiento por mantenerlo simple.
 */
final class LecturaActaConformadaEloquent implements LecturaActaConformada
{
    public function obtenerPorActaId(int $actaId): ?DatosActaConformada
    {
        $acta = Acta::query()->find($actaId);

        if ($acta === null) {
            return null;
        }

        return $this->mapear($acta);
    }

    public function listarFirmadas(): array
    {
        return Acta::query()
            ->where('estado', EstadoActa::Firmada)
            ->orderByDesc('fecha_firma')
            ->get()
            ->map($this->mapear(...))
            ->all();
    }

    private function mapear(Acta $acta): DatosActaConformada
    {
        $trabajo = Trabajo::query()->findOrFail($acta->trabajo_id);
        $orden = OrdenAplicacion::query()->findOrFail($trabajo->orden_id);

        return new DatosActaConformada(
            actaId: $acta->id,
            contratoId: $orden->contrato_id,
            hectareasConformadas: $acta->hectareas_conformadas,
            firmada: $acta->estado === EstadoActa::Firmada,
        );
    }
}
