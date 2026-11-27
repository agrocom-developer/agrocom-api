<?php

namespace App\Dominios\Flota\Aplicacion;

use App\Dominios\Compartido\Aplicacion\Medios\ArchivoDeMedio;
use App\Dominios\Compartido\Dominio\VarianteDeMedio;
use App\Dominios\Compartido\Infraestructura\Http\ItemDeRevisionDeMedio;
use Illuminate\Support\Carbon;

/**
 * Las fotos de un dron como ítems de la cuadrícula de revisión del panel. Se puede aprobar lo que no está
 * aprobado y rechazar lo que está «en revisión» (misma regla que `GestionarFotosDeDron`). Las fotos no tienen
 * descarga propia: el modal las muestra y nada más.
 */
final class ItemsDeRevisionDeFotos
{
    /**
     * @param  list<object{id: int, tipo: string, estado: string, motivo: ?string, archivo: string, created_at: mixed}>  $fotos
     * @param  list<string>  $obligatorias  tipos de foto obligatorios.
     * @return list<array<string, mixed>>
     */
    public static function de(array $fotos, array $obligatorias, bool $puedeRevisar): array
    {
        return array_map(static function (object $foto) use ($obligatorias, $puedeRevisar): array {
            $medio = ArchivoDeMedio::de(
                ruta: $foto->archivo,
                disco: null,
                nombreVisible: __('flota.fotos.tipo.'.$foto->tipo),
                mime: null,
                peso: null,
                url: static fn (VarianteDeMedio $variante): string => $variante === VarianteDeMedio::Original
                    ? route('panel.drones-flota.foto', ['foto' => $foto->id], false)
                    : route('panel.drones-flota.foto', ['foto' => $foto->id, 'variante' => $variante->value], false),
            );

            return (new ItemDeRevisionDeMedio(
                clave: 'f'.$foto->id,
                titulo: __('flota.fotos.tipo.'.$foto->tipo),
                estado: $foto->estado,
                estadoEtiqueta: __('flota.fotos.estado.'.$foto->estado),
                tono: match ($foto->estado) {
                    'aprobada' => 'success',
                    'rechazada' => 'danger',
                    default => 'warning',
                },
                medio: $medio->aArray(),
                obligatoria: in_array($foto->tipo, $obligatorias, true),
                pendiente: $foto->estado === 'en_revision',
                puedeAprobar: $puedeRevisar && $foto->estado !== 'aprobada',
                puedeRechazar: $puedeRevisar && $foto->estado === 'en_revision',
                urlRevisar: route('panel.drones-flota.foto-revisar', ['foto' => $foto->id], false),
                fecha: $foto->created_at === null ? null : Carbon::parse($foto->created_at)->format('d/m/Y'),
                motivo: $foto->motivo,
            ))->aArray();
        }, $fotos);
    }
}
