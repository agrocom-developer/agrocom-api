<?php

namespace App\Dominios\Proveedores\Aplicacion;

use App\Dominios\Compartido\Aplicacion\Medios\ArchivoDeMedio;
use App\Dominios\Compartido\Dominio\VarianteDeMedio;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Documento;

/**
 * El documento de un proveedor, piloto o dron como {@see ArchivoDeMedio} para el panel: la miniatura y la vista
 * media para la cuadrícula del modal de aprobación, el original para el visor y, si quien mira puede, la descarga
 * auditada. Las URL son rutas del panel (con sesión y permiso); no se guarda ninguna.
 */
final class MedioDeDocumento
{
    public static function de(Documento $documento, bool $puedeDescargar = false): ArchivoDeMedio
    {
        return ArchivoDeMedio::de(
            ruta: $documento->archivo_ref,
            disco: (string) config('proveedores.disco_documentos', 'local'),
            nombreVisible: __('proveedores.documentos.tipo.'.$documento->tipo->value),
            mime: $documento->mime,
            peso: $documento->bytes,
            url: static fn (VarianteDeMedio $variante): string => $variante === VarianteDeMedio::Original
                ? route('panel.documentos.archivo', ['documento' => $documento->id], false)
                : route('panel.documentos.archivo', ['documento' => $documento->id, 'variante' => $variante->value], false),
            urlDescarga: $puedeDescargar ? route('panel.documentos.descargar', ['documento' => $documento->id], false) : null,
        );
    }
}
