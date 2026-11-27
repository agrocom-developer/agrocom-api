<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Compartido\Aplicacion\Medios\AlmacenDeMedios;
use App\Dominios\Compartido\Aplicacion\Medios\LimiteDeMedios;
use App\Dominios\Compartido\Dominio\VarianteDeMedio;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\TerrenoFoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Fotos del estado del terreno (`POST /terrenos/{id}/fotos`, especificación §4.1): las sube el
 * cliente dueño y las ve el piloto en el detalle de la solicitud antes de ofertar. Todo parte
 * del cliente de la cuenta (invariante 5): la foto o el terreno de otro cliente responde como
 * si no existiera. El archivo vive en el disco privado y se entrega por una URL firmada de
 * corta vida, nunca por una pública. Un terreno admite como máximo `medios.limites.por_entidad.terreno_fotos` fotos.
 */
final class GestionarFotosDeTerreno
{
    public function __construct(
        private readonly GestionarTerrenos $terrenos,
        private readonly ClienteDeLaCuenta $clientes,
        private readonly AlmacenDeMedios $almacen,
        private readonly LimiteDeMedios $limites,
    ) {}

    /** @throws SolicitudInvalida */
    public function subir(int $usuarioId, int $terrenoId, UploadedFile $archivo, ?string $descripcion): TerrenoFoto
    {
        $this->clientes->activo($usuarioId);
        $terreno = $this->terrenos->obtener($usuarioId, $terrenoId);

        if ($this->limites->excede('terreno_fotos', TerrenoFoto::query()->where('terreno_id', $terreno->id)->count())) {
            throw SolicitudInvalida::fotosDeTerrenoLlenas($this->limites->porEntidad('terreno_fotos'));
        }

        $ruta = $this->almacen->guardar($archivo, "terrenos/{$terreno->id}/fotos");

        return TerrenoFoto::query()->create([
            'terreno_id' => $terreno->id,
            'archivo' => $ruta,
            'hash' => (string) hash_file('sha256', $archivo->getRealPath()),
            'bytes' => (int) $archivo->getSize(),
            'descripcion' => $descripcion === null || trim($descripcion) === '' ? null : trim($descripcion),
        ])->refresh();
    }

    /**
     * @return list<TerrenoFoto>
     *
     * @throws SolicitudInvalida
     */
    public function listar(int $usuarioId, int $terrenoId): array
    {
        $terreno = $this->terrenos->obtener($usuarioId, $terrenoId);

        return TerrenoFoto::query()->where('terreno_id', $terreno->id)->orderBy('id')->get()->all();
    }

    /** @throws SolicitudInvalida */
    public function borrar(int $usuarioId, int $terrenoId, int $fotoId): void
    {
        $terreno = $this->terrenos->obtener($usuarioId, $terrenoId);

        $foto = TerrenoFoto::query()->where('terreno_id', $terreno->id)->find($fotoId) ?? throw SolicitudInvalida::fotoDeTerrenoAjena();

        $foto->delete();
    }

    /** La ruta absoluta del archivo (el derivado pedido si ya existe), o `null` si ya no está en el disco. */
    public function ruta(int $fotoId, VarianteDeMedio $variante = VarianteDeMedio::Original): ?string
    {
        $foto = TerrenoFoto::query()->find($fotoId);

        return $foto === null ? null : $this->almacen->rutaAbsoluta($foto->archivo, $variante);
    }

    /** URL firmada de corta vida (relativa: la app la une a su base) para una foto; sin variante, el original. */
    public static function url(int $fotoId, VarianteDeMedio $variante = VarianteDeMedio::Original): string
    {
        return URL::temporarySignedRoute(
            'api.terrenos.fotos.archivo',
            Carbon::now()->addMinutes((int) config('solicitudes.fotos_terreno.url_minutos', 15)),
            $variante === VarianteDeMedio::Original ? ['foto' => $fotoId] : ['foto' => $fotoId, 'variante' => $variante->value],
            absolute: false,
        );
    }

    /**
     * Las tres URL firmadas de una foto: `url` (original, como siempre) y los derivados para listas y vistas previas.
     *
     * @return array{url: string, url_miniatura: string, url_media: string}
     */
    public static function urls(int $fotoId): array
    {
        return [
            'url' => self::url($fotoId),
            'url_miniatura' => self::url($fotoId, VarianteDeMedio::Miniatura),
            'url_media' => self::url($fotoId, VarianteDeMedio::Media),
        ];
    }
}
