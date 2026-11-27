<?php

namespace App\Dominios\Compartido\Aplicacion\Medios;

use App\Dominios\Compartido\Dominio\VarianteDeMedio;
use Closure;

/**
 * Contrato interno estable de un archivo multimedia para cualquier pantalla (panel o API): un recurso por
 * archivo con todo lo que una cuadrícula, un modal o un visor necesitan, sin que la vista toque el disco.
 *
 * ```
 * url_miniatura      string|null  derivado chico (cuadrículas y listas); si aún no se generó, sirve el original reducido por el servidor o el original
 * url_media          string|null  derivado medio (vista previa)
 * url_original       string|null  el archivo tal como se subió (visor y descarga); null si ya no existe
 * existe             bool         el archivo está en el disco; si es false, las URL de imagen apuntan al placeholder
 * tipo               imagen|pdf|otro
 * tiene_vista_previa bool         true solo para imágenes (un PDF no se transforma: se muestra un ícono)
 * mime               string|null
 * peso               int|null     bytes del original
 * nombre_visible     string       texto para mostrar; nunca el nombre que subió la persona
 * url_descarga       string|null  extra: descarga auditada del original, solo si quien mira tiene el permiso
 * ```
 *
 * Las URL no se guardan en ningún lado: cada módulo las compone con su ruta (firmada, con token o del panel)
 * al construir este objeto.
 */
final readonly class ArchivoDeMedio
{
    public function __construct(
        public ?string $urlMiniatura,
        public ?string $urlMedia,
        public ?string $urlOriginal,
        public bool $existe,
        public string $tipo,
        public bool $tieneVistaPrevia,
        public ?string $mime,
        public ?int $peso,
        public string $nombreVisible,
        public ?string $urlDescarga = null,
    ) {}

    /**
     * @param  Closure(VarianteDeMedio): string  $url  compone la URL de una variante con la ruta del módulo.
     * @param  int|null  $peso  los bytes ya conocidos (si no, se leen del disco).
     * @param  string|null  $urlDescarga  la URL de descarga del original, si quien mira puede descargar.
     */
    public static function de(
        ?string $ruta,
        ?string $disco,
        string $nombreVisible,
        ?string $mime,
        ?int $peso,
        Closure $url,
        ?string $urlDescarga = null,
    ): self {
        $almacen = app(AlmacenDeMedios::class);
        $existe = $almacen->existe($ruta, $disco);
        $mime = $mime !== null && $mime !== '' ? $mime : ($existe ? $almacen->mime($ruta, $disco) : null);
        $tipo = self::tipoDe($mime, $ruta);

        $placeholder = asset((string) config('medios.placeholder', 'images/medio-no-disponible.svg'));

        return new self(
            urlMiniatura: $tipo !== 'imagen' ? null : ($existe ? $url(VarianteDeMedio::Miniatura) : $placeholder),
            urlMedia: $tipo !== 'imagen' ? null : ($existe ? $url(VarianteDeMedio::Media) : $placeholder),
            urlOriginal: $existe ? $url(VarianteDeMedio::Original) : null,
            existe: $existe,
            tipo: $tipo,
            tieneVistaPrevia: $tipo === 'imagen',
            mime: $mime,
            peso: $peso ?? ($existe ? $almacen->peso($ruta, $disco) : null),
            nombreVisible: $nombreVisible,
            urlDescarga: $existe ? $urlDescarga : null,
        );
    }

    /** @return array{url_miniatura: ?string, url_media: ?string, url_original: ?string, existe: bool, tipo: string, tiene_vista_previa: bool, mime: ?string, peso: ?int, nombre_visible: string, url_descarga: ?string} */
    public function aArray(): array
    {
        return [
            'url_miniatura' => $this->urlMiniatura,
            'url_media' => $this->urlMedia,
            'url_original' => $this->urlOriginal,
            'existe' => $this->existe,
            'tipo' => $this->tipo,
            'tiene_vista_previa' => $this->tieneVistaPrevia,
            'mime' => $this->mime,
            'peso' => $this->peso,
            'nombre_visible' => $this->nombreVisible,
            'url_descarga' => $this->urlDescarga,
        ];
    }

    private static function tipoDe(?string $mime, ?string $ruta): string
    {
        $extension = strtolower(pathinfo((string) $ruta, PATHINFO_EXTENSION));

        if (str_starts_with((string) $mime, 'image/') || in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return 'imagen';
        }

        if ($mime === 'application/pdf' || $extension === 'pdf') {
            return 'pdf';
        }

        return 'otro';
    }
}
