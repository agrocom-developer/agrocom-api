<?php

namespace App\Dominios\Compartido\Aplicacion;

use Illuminate\Support\Facades\Storage;

/**
 * Resuelve la URL pública de una imagen guardada en disco (logo de empresa,
 * logo de cliente) y cae a un placeholder fijo del repositorio si la ruta
 * guardada no existe físicamente (23/9/2026, ADR 0026): la fila puede seguir
 * teniendo `logo_path` aunque el archivo se haya perdido (borrado a mano,
 * purga del bucket), y mostrar un `<img>` roto es peor que mostrar el
 * placeholder — a diferencia de un documento propio del sistema (acta,
 * reporte, recibo), una imagen subida por el usuario no se puede
 * reconstruir sola.
 *
 * No aplica a los endpoints de EDICIÓN del logo (`ClientesController` y
 * `OrganizacionController::logoArchivo()`): ahí `null` es el contrato
 * correcto — el `file-field` ya sabe mostrar el estado vacío de "sin logo
 * subido todavía", que es distinto de "hay un logo pero el archivo se
 * perdió". Esta clase es para pantallas que MUESTRAN el logo, no para las
 * que lo administran.
 */
final class ResolverUrlImagenConPlaceholder
{
    private const PLACEHOLDER = 'images/logo-placeholder.png';

    public function ejecutar(string $disco, ?string $ruta): string
    {
        if ($ruta !== null && Storage::disk($disco)->exists($ruta)) {
            return Storage::disk($disco)->url($ruta);
        }

        return asset(self::PLACEHOLDER);
    }
}
