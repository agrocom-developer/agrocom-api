<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\DatosActaConformada;
use App\Dominios\Operaciones\Contratos\LecturaActaConformada;
use App\Dominios\Operaciones\Dominio\EstadoActa;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Acta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;

/**
 * Implementación Eloquent del contrato de lectura de acta conformada. Vive
 * fuera de `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaReporteTecnicoEloquent`: esa subcarpeta está reservada a modelos que
 * extienden `ModeloDominio` (`tests/Unit/ArquitecturaModulosTest.php` lo
 * exige) — esta clase no es un modelo, es el adaptador que el
 * `ServiceProvider` del módulo liga a {@see LecturaActaConformada}.
 *
 * Resuelve `solicitud_id` por `Acta.trabajo_id → Trabajo.solicitud_id`, sin
 * JOIN cross-tabla optimizado a propósito: el volumen de actas no lo
 * justifica, y las dos tablas son propias del módulo.
 */
final class LecturaActaConformadaEloquent implements LecturaActaConformada
{
    public function obtenerPorActaId(int $actaId): ?DatosActaConformada
    {
        $acta = Acta::query()->find($actaId);

        return $acta === null ? null : $this->mapear($acta);
    }

    /**
     * La consulta nace de la solicitud, nunca de «todas las actas con un
     * `where` después» (invariante 5 de CLAUDE.md): primero se resuelven los
     * `trabajo_id` de ESA solicitud, recién ahí se filtran las actas firmadas.
     */
    public function listarFirmadasPorSolicitud(int $solicitudId): array
    {
        $trabajoIds = Trabajo::query()->where('solicitud_id', $solicitudId)->pluck('id');

        return Acta::query()
            ->whereIn('trabajo_id', $trabajoIds)
            ->where('estado', EstadoActa::Firmada)
            ->orderByDesc('fecha_firma')
            ->get()
            ->map($this->mapear(...))
            ->all();
    }

    private function mapear(Acta $acta): DatosActaConformada
    {
        $trabajo = Trabajo::query()->findOrFail($acta->trabajo_id);

        return new DatosActaConformada(
            actaId: $acta->id,
            solicitudId: (int) $trabajo->solicitud_id,
            hectareasConformadas: $acta->hectareas_conformadas,
            firmada: $acta->estado === EstadoActa::Firmada,
            pdfPath: $acta->pdf_path,
        );
    }
}
