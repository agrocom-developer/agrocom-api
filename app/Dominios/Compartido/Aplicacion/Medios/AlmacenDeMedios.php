<?php

namespace App\Dominios\Compartido\Aplicacion\Medios;

use App\Dominios\Compartido\Dominio\Excepciones\MedioNoGuardado;
use App\Dominios\Compartido\Dominio\VarianteDeMedio;
use App\Dominios\Compartido\Infraestructura\Medios\GenerarDerivadosDeMedio;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * El único lugar que guarda, mueve, sirve, comprueba y borra archivos multimedia de personas.
 *
 * - La base guarda solo la ruta RELATIVA dentro del disco (`drones/7/fotos/ab12….jpg`), nunca una URL ni una
 *   ruta del servidor: la URL se compone al responder ({@see ArchivoDeMedio}) y la raíz del disco puede cambiar.
 * - El nombre del archivo lo genera el servidor (16 bytes aleatorios): no se adivina y no lleva datos personales
 *   ni el nombre que subió la persona.
 * - Los derivados (miniatura y tamaño medio) viven junto al original con un sufijo (`ab12….miniatura.webp`): la
 *   ruta del derivado se calcula de la del original, así no hay segunda columna que mantener. Borrar o mover un
 *   original arrastra a sus derivados.
 * - Un archivo que ya no está en el disco nunca produce un error: `existe()` responde `false` y quien sirve
 *   decide cómo degradar.
 */
final class AlmacenDeMedios
{
    /** Extensiones de los derivados, en el orden en que se buscan. */
    private const array EXTENSIONES_DERIVADO = ['webp', 'jpg'];

    /** Prefijos que pudo dejar una ruta absoluta o una URL guardada por error, de la más larga a la más corta. */
    private const array PREFIJOS_CONOCIDOS = [
        'storage/app/private/', 'storage/app/public/', 'storage/app/r2/', 'app/private/', 'app/public/', 'app/r2/', 'storage/',
    ];

    public function disco(?string $disco = null): Filesystem
    {
        return Storage::disk($disco ?? (string) config('medios.disco', 'local'));
    }

    /**
     * Guarda un archivo subido bajo `$carpeta` con un nombre generado y devuelve su ruta relativa.
     * Encola la generación de los derivados si es una imagen.
     *
     * @param  bool  $derivados  `false` para lo que no se muestra reducido (la foto de perfil ya pesa poco).
     * @param  string|null  $nombre  nombre sin extensión (p. ej. el `uuid_cliente` que el dispositivo ya generó, para que
     *                               reintentar la misma subida no deje un segundo archivo).
     *
     * @throws MedioNoGuardado
     */
    public function guardar(UploadedFile $archivo, string $carpeta, ?string $nombre = null, ?string $disco = null, bool $derivados = true): string
    {
        $nombre ??= bin2hex(random_bytes(16));
        $ruta = $this->disco($disco)->putFileAs(trim($carpeta, '/'), $archivo, $nombre.'.'.$this->extensionDe($archivo));

        if (! is_string($ruta) || $ruta === '') {
            throw new MedioNoGuardado('No se pudo guardar el archivo.');
        }

        if ($derivados) {
            $this->encolarDerivados($ruta, $disco);
        }

        return $ruta;
    }

    /** Encola los derivados de una imagen ya guardada; un fallo de la cola nunca deshace la subida. */
    public function encolarDerivados(string $ruta, ?string $disco = null): void
    {
        if (! $this->esImagen($ruta)) {
            return;
        }

        try {
            GenerarDerivadosDeMedio::dispatch($ruta, $disco)->afterCommit();
        } catch (Throwable $falla) {
            report($falla);
        }
    }

    public function existe(?string $ruta, ?string $disco = null): bool
    {
        $ruta = self::normalizar($ruta);

        if ($ruta === null) {
            return false;
        }

        try {
            return $this->disco($disco)->exists($ruta);
        } catch (Throwable) {
            return false;
        }
    }

    /** La ruta del derivado si existe, o `null`. */
    public function derivado(?string $ruta, VarianteDeMedio $variante, ?string $disco = null): ?string
    {
        $ruta = self::normalizar($ruta);

        if ($ruta === null || $variante === VarianteDeMedio::Original) {
            return null;
        }

        foreach (self::EXTENSIONES_DERIVADO as $extension) {
            $candidata = self::rutaDeDerivado($ruta, $variante, $extension);

            if ($this->existe($candidata, $disco)) {
                return $candidata;
            }
        }

        return null;
    }

    /**
     * La ruta que se debe entregar para una variante: el derivado si ya se generó; si no, el original
     * (siempre sirve). `null` si el original ya no está.
     */
    public function rutaServible(?string $ruta, VarianteDeMedio $variante, ?string $disco = null): ?string
    {
        $ruta = self::normalizar($ruta);

        if ($ruta === null || ! $this->existe($ruta, $disco)) {
            return null;
        }

        return $this->derivado($ruta, $variante, $disco) ?? $ruta;
    }

    /** La ruta absoluta en el disco local de la variante servible, o `null` si falta o el disco no es local. */
    public function rutaAbsoluta(?string $ruta, VarianteDeMedio $variante = VarianteDeMedio::Original, ?string $disco = null): ?string
    {
        $servible = $this->rutaServible($ruta, $variante, $disco);

        if ($servible === null) {
            return null;
        }

        $nombre = $disco ?? (string) config('medios.disco', 'local');

        // Solo un disco local tiene una ruta en el sistema de archivos; en uno remoto se sirve por `respuesta()`.
        return config("filesystems.disks.{$nombre}.driver") === 'local' ? (string) $this->disco($disco)->path($servible) : null;
    }

    /**
     * Respuesta con el archivo, con encabezados seguros (`nosniff`, sin caché compartida). Con `$comoAdjunto` el
     * navegador lo descarga con `$nombreDescarga`; sin él lo muestra en línea.
     *
     * @return StreamedResponse|null `null` si el archivo no existe.
     */
    public function respuesta(
        ?string $ruta,
        VarianteDeMedio $variante = VarianteDeMedio::Original,
        ?string $disco = null,
        ?string $nombreDescarga = null,
        bool $comoAdjunto = false,
        ?string $mime = null,
        string $cache = 'private, no-store',
    ): ?StreamedResponse {
        $servible = $this->rutaServible($ruta, $variante, $disco);

        if ($servible === null) {
            return null;
        }

        $cabeceras = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => $cache];

        // El mime guardado solo describe al original; un derivado se declara por su propia extensión.
        if ($servible === self::normalizar($ruta) && $mime !== null && $mime !== '') {
            $cabeceras['Content-Type'] = $mime;
        }

        $sistema = $this->disco($disco);

        return $sistema->response($servible, $nombreDescarga, $cabeceras, $comoAdjunto ? 'attachment' : 'inline');
    }

    /** Borra el original y sus derivados. Idempotente: lo que no existe se ignora. */
    public function borrar(?string $ruta, ?string $disco = null): void
    {
        $ruta = self::normalizar($ruta);

        if ($ruta === null) {
            return;
        }

        $sistema = $this->disco($disco);

        foreach ([$ruta, ...$this->rutasDeDerivados($ruta)] as $candidata) {
            try {
                $sistema->delete($candidata);
            } catch (Throwable $falla) {
                report($falla);
            }
        }
    }

    /** Mueve el original y sus derivados a la ruta nueva. `false` si el original no existe o no se pudo mover. */
    public function mover(string $origen, string $destino, ?string $disco = null): bool
    {
        $origen = self::normalizar($origen);
        $destino = self::normalizar($destino);

        if ($origen === null || $destino === null || ! $this->existe($origen, $disco)) {
            return false;
        }

        $sistema = $this->disco($disco);

        if (! $sistema->move($origen, $destino)) {
            return false;
        }

        foreach (VarianteDeMedio::derivadas() as $variante) {
            foreach (self::EXTENSIONES_DERIVADO as $extension) {
                $desde = self::rutaDeDerivado($origen, $variante, $extension);

                if ($sistema->exists($desde)) {
                    $sistema->move($desde, self::rutaDeDerivado($destino, $variante, $extension));
                }
            }
        }

        return true;
    }

    public function peso(?string $ruta, ?string $disco = null): ?int
    {
        $ruta = self::normalizar($ruta);

        try {
            return $ruta !== null && $this->existe($ruta, $disco) ? (int) $this->disco($disco)->size($ruta) : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function mime(?string $ruta, ?string $disco = null): ?string
    {
        $ruta = self::normalizar($ruta);

        try {
            return $ruta !== null && $this->existe($ruta, $disco) ? ($this->disco($disco)->mimeType($ruta) ?: null) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Ruta del derivado junto al original: `a/b/x.jpg` -> `a/b/x.miniatura.webp`. */
    public static function rutaDeDerivado(string $ruta, VarianteDeMedio $variante, string $extension = 'webp'): string
    {
        $punto = strrpos($ruta, '.');
        $base = $punto === false || $punto < (int) strrpos($ruta, '/') ? $ruta : substr($ruta, 0, $punto);

        return $base.'.'.$variante->sufijo().'.'.$extension;
    }

    /** @return list<string> todas las rutas posibles de derivados de un original (existan o no). */
    public function rutasDeDerivados(string $ruta): array
    {
        $rutas = [];

        foreach (VarianteDeMedio::derivadas() as $variante) {
            foreach (self::EXTENSIONES_DERIVADO as $extension) {
                $rutas[] = self::rutaDeDerivado($ruta, $variante, $extension);
            }
        }

        return $rutas;
    }

    public static function esRutaDeDerivado(string $ruta): bool
    {
        return preg_match('/\.(miniatura|media)\.(webp|jpg)$/', $ruta) === 1;
    }

    /**
     * Deja un valor guardado como ruta relativa al disco: si traía una URL completa, una ruta absoluta del
     * servidor o un prefijo de `storage/`, se lo quita. `null` si no queda ruta o intenta salir del disco.
     */
    public static function normalizar(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim($valor);

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $valor) === 1) {
            $valor = (string) parse_url($valor, PHP_URL_PATH);
        }

        $valor = (string) strtok(str_replace('\\', '/', $valor), '?#');
        $raiz = rtrim(str_replace('\\', '/', storage_path()), '/').'/';

        if (str_starts_with($valor, $raiz)) {
            $valor = substr($valor, strlen($raiz));
        }

        $valor = ltrim($valor, '/');

        foreach (self::PREFIJOS_CONOCIDOS as $prefijo) {
            if (str_starts_with($valor, $prefijo)) {
                $valor = substr($valor, strlen($prefijo));

                break;
            }
        }

        $valor = ltrim($valor, '/');

        if ($valor === '' || in_array('..', explode('/', $valor), true)) {
            return null;
        }

        return $valor;
    }

    private function esImagen(string $ruta): bool
    {
        return in_array(strtolower(pathinfo($ruta, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
    }

    private function extensionDe(UploadedFile $archivo): string
    {
        $porMime = match ($archivo->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => null,
        };

        if ($porMime !== null) {
            return $porMime;
        }

        $extension = strtolower((string) ($archivo->guessExtension() ?: $archivo->getClientOriginalExtension()));

        return preg_match('/^[a-z0-9]{1,5}$/', $extension) === 1 ? $extension : 'bin';
    }
}
