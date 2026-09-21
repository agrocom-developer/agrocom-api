<?php

namespace App\Dominios\Compartido\Aplicacion;

use GdImage;
use Illuminate\Http\UploadedFile;

/**
 * Redimensiona y recomprime una imagen recién subida antes de guardarla en
 * disco (15/9/2026, logo de cliente/empresa — ADR 0019): antes se rechazaba
 * cualquier archivo de más de 2 MB; una foto de cámara moderna (12+ MP) pesa
 * eso de sobra sin ser un archivo desproporcionado para un logo, así que el
 * límite pasó de ser un rechazo a ser una conversión automática. `mimes` en
 * el Form Request sigue siendo la única guarda de FORMATO — esto solo ajusta
 * peso, nunca cambia la extensión ni el tipo de archivo que el usuario subió.
 *
 * SVG se guarda tal cual: es vector, "redimensionar" no reduce su peso y
 * reescribirlo como raster le haría perder la ventaja de serlo.
 *
 * GIF también se guarda tal cual (15/9/2026, encontrado en vivo: un GIF
 * subido dejaba de animarse en el modal): GD solo lee/escribe UN frame
 * (`imagecreatefromstring` + `imagegif` aplanan cualquier GIF animado al
 * primer cuadro) — no hay forma de redimensionar o recomprimir sin perder la
 * animación con las funciones nativas de PHP. Un GIF real para un logo es
 * chico de por sí; la excepción no compromete el objetivo de esta clase
 * (fotos de cámara pesadas), que nunca vienen en este formato.
 *
 * JPEG conserva la rotación real de la foto (EXIF `Orientation`) antes de
 * redimensionar — sin esto, una foto de celular tomada en vertical sale
 * rotada 90°: GD lee los píxeles crudos tal cual están guardados y nunca
 * aplica esa rotación por su cuenta.
 */
final class OptimizarImagenSubida
{
    private const int LADO_MAXIMO_PX = 1600;

    private const int PESO_OBJETIVO_BYTES = 2 * 1024 * 1024;

    /** @var list<int> */
    private const array CALIDADES_CON_PERDIDA = [85, 70, 55, 40];

    /** @return array{contenido: string, extension: string} */
    public function ejecutar(UploadedFile $archivo): array
    {
        $extension = strtolower($archivo->extension() ?: 'bin');
        $contenidoOriginal = (string) file_get_contents($archivo->getRealPath());

        if (in_array($extension, ['svg', 'gif'], true)) {
            return ['contenido' => $contenidoOriginal, 'extension' => $extension];
        }

        $imagen = @imagecreatefromstring($contenidoOriginal);

        // No debería pasar (`mimes` ya filtró el tipo), pero si GD no puede
        // decodificarlo se guarda el archivo tal como llegó antes que perderlo.
        if ($imagen === false) {
            return ['contenido' => $contenidoOriginal, 'extension' => $extension];
        }

        if (in_array($extension, ['jpg', 'jpeg'], true)) {
            $imagen = $this->corregirOrientacion($imagen, $archivo->getRealPath());
        }

        $imagen = $this->redimensionar($imagen);

        return $this->codificar($imagen, $extension);
    }

    private function corregirOrientacion(GdImage $imagen, string $rutaArchivo): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $imagen;
        }

        $exif = @exif_read_data($rutaArchivo);
        $orientacion = is_array($exif) ? ($exif['Orientation'] ?? 1) : 1;

        return match ($orientacion) {
            3 => imagerotate($imagen, 180, 0),
            6 => imagerotate($imagen, 270, 0),
            8 => imagerotate($imagen, 90, 0),
            default => $imagen,
        };
    }

    private function redimensionar(GdImage $imagen): GdImage
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $ladoMayor = max($ancho, $alto);

        if ($ladoMayor <= self::LADO_MAXIMO_PX) {
            return $imagen;
        }

        $factor = self::LADO_MAXIMO_PX / $ladoMayor;
        $anchoNuevo = max(1, (int) round($ancho * $factor));
        $altoNuevo = max(1, (int) round($alto * $factor));

        $redimensionada = imagecreatetruecolor($anchoNuevo, $altoNuevo);
        imagealphablending($redimensionada, false);
        imagesavealpha($redimensionada, true);
        imagecopyresampled($redimensionada, $imagen, 0, 0, 0, 0, $anchoNuevo, $altoNuevo, $ancho, $alto);

        return $redimensionada;
    }

    /** @return array{contenido: string, extension: string} */
    private function codificar(GdImage $imagen, string $extension): array
    {
        // PNG no tiene una "calidad" con pérdida real que ajustar — el resize
        // de arriba ya es todo lo que se puede hacer por su peso. GIF nunca
        // llega acá (se resuelve antes, en `ejecutar()`).
        if (! in_array($extension, ['jpg', 'jpeg', 'webp'], true)) {
            return ['contenido' => $this->capturar($imagen, $extension, null), 'extension' => $extension];
        }

        $contenido = '';

        foreach (self::CALIDADES_CON_PERDIDA as $calidad) {
            $contenido = $this->capturar($imagen, $extension, $calidad);

            if (strlen($contenido) <= self::PESO_OBJETIVO_BYTES) {
                break;
            }
        }

        return ['contenido' => $contenido, 'extension' => $extension];
    }

    private function capturar(GdImage $imagen, string $extension, ?int $calidad): string
    {
        ob_start();

        match ($extension) {
            'png' => imagepng($imagen, null, 6),
            'webp' => imagewebp($imagen, null, $calidad ?? 85),
            default => imagejpeg($imagen, null, $calidad ?? 85),
        };

        return (string) ob_get_clean();
    }
}
