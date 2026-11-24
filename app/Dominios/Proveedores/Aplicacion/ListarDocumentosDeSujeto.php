<?php

namespace App\Dominios\Proveedores\Aplicacion;

use App\Dominios\Proveedores\Contratos\DatosDocumento;
use App\Dominios\Proveedores\Contratos\LecturaDocumentos;
use App\Dominios\Proveedores\Dominio\VencimientoDocumento;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Documento;
use DateTimeImmutable;

/** Documentos de un sujeto, con los días que faltan para vencer ya calculados: la vista no calcula nada. */
final class ListarDocumentosDeSujeto implements LecturaDocumentos
{
    public function de(string $sujetoTipo, int $sujetoId): array
    {
        $hoy = new DateTimeImmutable('today');

        return Documento::query()
            ->where('sujeto_tipo', $sujetoTipo)
            ->where('sujeto_id', $sujetoId)
            ->orderByDesc('id')
            ->get()
            ->map(static function (Documento $documento) use ($hoy): DatosDocumento {
                $vence = $documento->vence_en?->format('Y-m-d');

                return new DatosDocumento(
                    $documento->id,
                    $documento->tipo->value,
                    $documento->estado->value,
                    $documento->vence_en?->format('d/m/Y'),
                    $vence === null ? null : VencimientoDocumento::diasHasta($vence, $hoy),
                    $documento->motivo_rechazo,
                    $documento->nombre_original,
                    $documento->bytes,
                    $documento->created_at->format('d/m/Y'),
                );
            })
            ->all();
    }
}
