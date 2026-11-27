<?php

namespace App\Dominios\Compartido\Aplicacion\Medios;

use App\Dominios\Compartido\Dominio\VarianteDeMedio;
use GdImage;
use Throwable;

/**
 * Genera la miniatura y el tamaño medio de una imagen guardada, junto al original (que no se toca).
 *
 * Usa la extensión GD que ya trae PHP (sin dependencia nueva). Escribe WebP si GD lo soporta y JPEG si no. Respeta
 * la orientación EXIF de las fotos de celular, y no agranda: una imagen más chica que el lado pedido se recomprime
 * a su tamaño. PDF, GIF y SVG no se transforman. Una imagen que no se puede leer o que pasa del límite de
 * megapíxeles se omite sin error (queda el original, que siempre sirve).
 */
final class GeneradorDeDerivados
{
    public function __construct(private readonly AlmacenDeMedios $almacen) {}

    /**
     * @return list<string> las rutas de los derivados escritos (vacía si no hay nada que hacer).
     */
    public function generar(string $ruta, ?string $disco = null, bool $forzar = false): array
    {
        $ruta = AlmacenDeMedios::normalizar($ruta);

        if ($ruta === null || AlmacenDeMedios::esRutaDeDerivado($ruta) || ! $this->esTransformable($ruta) || ! $this->almacen->existe($ruta, $disco)) {
            return [];
        }

        $pendientes = array_values(array_filter(
            VarianteDeMedio::derivadas(),
            fn (VarianteDeMedio $variante): bool => $forzar || $this->almacen->derivado($ruta, $variante, $disco) === null,
        ));

        if ($pendientes === []) {
            return [];
        }

        try {
            $contenido = (string) $this->almacen->disco($disco)->get($ruta);
            $imagen = $this->abrir($contenido, $ruta);
        } catch (Throwable $falla) {
            report($falla);

            return [];
        }

        if ($imagen === null) {
            return [];
        }

        $extension = function_exists('imagewebp') ? 'webp' : 'jpg';
        $escritos = [];

        foreach ($pendientes as $variante) {
            $config = (array) config('medios.derivados.'.$variante->value, []);
            $destino = AlmacenDeMedios::rutaDeDerivado($ruta, $variante, $extension);

            $reducida = $this->reducir($imagen, max(1, (int) ($config['lado'] ?? 320)));
            $bytes = $this->codificar($reducida, $extension, max(1, min(100, (int) ($config['calidad'] ?? 75))));

            if ($bytes !== '' && $this->almacen->disco($disco)->put($destino, $bytes)) {
                $escritos[] = $destino;
            }
        }

        return $escritos;
    }

    private function esTransformable(string $ruta): bool
    {
        return in_array(strtolower(pathinfo($ruta, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
    }

    private function abrir(string $contenido, string $ruta): ?GdImage
    {
        $medidas = @getimagesizefromstring($contenido);

        if ($medidas === false) {
            return null;
        }

        $maximo = (int) config('medios.derivados.megapixeles_maximos', 40) * 1_000_000;

        if ($maximo < $medidas[0] * $medidas[1]) {
            return null;
        }

        $imagen = @imagecreatefromstring($contenido);

        if ($imagen === false) {
            return null;
        }

        return $medidas[2] === IMAGETYPE_JPEG ? $this->corregirOrientacion($imagen, $contenido) : $imagen;
    }

    private function corregirOrientacion(GdImage $imagen, string $contenido): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $imagen;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($contenido));
        $orientacion = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $angulo = match ($orientacion) {
            3 => 180,
            6 => 270,
            8 => 90,
            default => 0,
        };

        return $angulo === 0 ? $imagen : (imagerotate($imagen, $angulo, 0) ?: $imagen);
    }

    private function reducir(GdImage $imagen, int $lado): GdImage
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $mayor = max($ancho, $alto);

        if ($mayor <= $lado) {
            return $imagen;
        }

        $factor = $lado / $mayor;
        $anchoNuevo = max(1, (int) round($ancho * $factor));
        $altoNuevo = max(1, (int) round($alto * $factor));

        $reducida = imagecreatetruecolor($anchoNuevo, $altoNuevo);
        imagealphablending($reducida, false);
        imagesavealpha($reducida, true);
        imagecopyresampled($reducida, $imagen, 0, 0, 0, 0, $anchoNuevo, $altoNuevo, $ancho, $alto);

        return $reducida;
    }

    private function codificar(GdImage $imagen, string $extension, int $calidad): string
    {
        ob_start();

        if ($extension === 'webp') {
            imagewebp($imagen, null, $calidad);
        } else {
            // JPEG no tiene transparencia: se compone sobre blanco.
            $fondo = imagecreatetruecolor(imagesx($imagen), imagesy($imagen));
            imagefill($fondo, 0, 0, (int) imagecolorallocate($fondo, 255, 255, 255));
            imagecopy($fondo, $imagen, 0, 0, 0, 0, imagesx($imagen), imagesy($imagen));
            imagejpeg($fondo, null, $calidad);
        }

        return (string) ob_get_clean();
    }
}
