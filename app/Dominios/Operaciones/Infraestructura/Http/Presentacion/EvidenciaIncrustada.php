<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Presentacion;

use App\Dominios\Operaciones\Aplicacion\ArmarContenidoReporteTecnico;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Convierte la ruta privada de una evidencia en el disco `r2` en un `data:`
 * URI que dompdf pueda dibujar dentro del PDF.
 *
 * Existe porque `Evidencia::archivo_url` NO es una URL: es una clave del
 * bucket (`evidencias/{tipo}/{yyyy}/{mm}/{uuid}-{id}.jpeg`, ADR 0009), sin
 * host y sin firma. En el panel eso no molesta —cada `<img>` apunta a
 * `panel.evidencias.archivo`, que hace streaming con el permiso ya
 * verificado— pero el PDF se renderiza fuera de una petición HTTP: dompdf no
 * tiene sesión, así que una ruta relativa no le dice nada y una URL firmada
 * lo obligaría a salir a la red desde el servidor. Incrustar los bytes es lo
 * único que hace que la imagen exista dentro del archivo que se descarga.
 *
 * Presentación pura, y por eso vive en `Infraestructura/Http/Presentacion/`
 * y no en `Aplicacion/`: {@see ArmarContenidoReporteTecnico}
 * sigue devolviendo la ruta del bucket —el dato— y es la vista la que decide
 * cómo dibujarla.
 *
 * Devuelve `null` en cualquier tropiezo (ruta vacía, archivo ausente del
 * bucket, credenciales de R2 mal configuradas, archivo demasiado grande) en
 * vez de lanzar: un PDF sin una foto sigue siendo un reporte útil, uno que no
 * se genera no le sirve a nadie.
 */
final class EvidenciaIncrustada
{
    /**
     * Tope de 8 MB por imagen. Un `data:` URI infla el archivo ~33 % sobre el
     * binario, y un reporte con varias sesiones lleva una captura por cada
     * una: sin tope, un puñado de fotos de cámara moderna agota la memoria de
     * dompdf antes de terminar de renderizar.
     */
    private const MAXIMO_BYTES = 8 * 1024 * 1024;

    public static function dataUri(?string $rutaEnBucket): ?string
    {
        if ($rutaEnBucket === null || trim($rutaEnBucket) === '') {
            return null;
        }

        try {
            $disco = Storage::disk('r2');

            if (! $disco->exists($rutaEnBucket)) {
                return null;
            }

            if ($disco->size($rutaEnBucket) > self::MAXIMO_BYTES) {
                return null;
            }

            $contenido = $disco->get($rutaEnBucket);

            if ($contenido === null || $contenido === '') {
                return null;
            }

            $mime = $disco->mimeType($rutaEnBucket);
        } catch (Throwable) {
            return null;
        }

        return 'data:'.($mime !== false && $mime !== '' ? $mime : 'image/jpeg').';base64,'.base64_encode($contenido);
    }
}
