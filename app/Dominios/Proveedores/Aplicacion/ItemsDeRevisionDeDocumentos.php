<?php

namespace App\Dominios\Proveedores\Aplicacion;

use App\Dominios\Compartido\Infraestructura\Http\ItemDeRevisionDeMedio;
use App\Dominios\Proveedores\Contratos\DatosDocumento;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Documento;

/**
 * Los documentos de proveedor, piloto o dron como ítems de la cuadrícula de revisión del panel. Solo se aprueba
 * o rechaza lo que está «en revisión»; la descarga del original solo viaja si quien mira puede descargar.
 */
final class ItemsDeRevisionDeDocumentos
{
    private const TONO = ['en_revision' => 'warning', 'aprobado' => 'success', 'rechazado' => 'danger', 'vencido' => 'danger'];

    /**
     * @param  list<DatosDocumento>  $datos  los de una ficha.
     * @return list<array<string, mixed>>
     */
    public static function deFicha(array $datos, bool $puedeRevisar, bool $puedeDescargar): array
    {
        $ids = array_map(static fn (DatosDocumento $dato): int => $dato->id, $datos);
        $modelos = Documento::query()->whereIn('id', $ids)->get()->keyBy('id');

        $items = [];
        foreach ($modelos->sortBy(fn (Documento $documento): int => (int) array_search($documento->id, $ids, true)) as $documento) {
            $items[] = self::de($documento, $puedeRevisar, $puedeDescargar);
        }

        return $items;
    }

    /**
     * @param  iterable<Documento>  $documentos
     * @param  array<int, string>  $sujetos  nombre del sujeto por id de documento (la cola los mezcla).
     * @return list<array<string, mixed>>
     */
    public static function deModelos(iterable $documentos, bool $puedeRevisar, bool $puedeDescargar, array $sujetos = []): array
    {
        $items = [];
        foreach ($documentos as $documento) {
            $items[] = self::de($documento, $puedeRevisar, $puedeDescargar, $sujetos[$documento->id] ?? null);
        }

        return $items;
    }

    /** @return array<string, mixed> */
    public static function de(Documento $documento, bool $puedeRevisar, bool $puedeDescargar, ?string $sujeto = null): array
    {
        $estado = $documento->estado->value;
        $revisable = $puedeRevisar && $estado === 'en_revision';

        return (new ItemDeRevisionDeMedio(
            clave: 'd'.$documento->id,
            titulo: __('proveedores.documentos.tipo.'.$documento->tipo->value),
            estado: $estado,
            estadoEtiqueta: __('proveedores.documentos.estado.'.$estado),
            tono: self::TONO[$estado],
            medio: MedioDeDocumento::de($documento, $puedeDescargar)->aArray(),
            obligatoria: false,
            pendiente: $estado === 'en_revision',
            puedeAprobar: $revisable,
            puedeRechazar: $revisable,
            urlRevisar: $revisable ? route('panel.documentos.revisar', ['documento' => $documento->id], false) : null,
            subtitulo: $sujeto,
            fecha: $documento->created_at->format('d/m/Y'),
            motivo: $documento->motivo_rechazo,
        ))->aArray();
    }
}
