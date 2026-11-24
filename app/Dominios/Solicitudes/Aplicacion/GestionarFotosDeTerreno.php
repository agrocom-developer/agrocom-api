<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\TerrenoFoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Fotos del estado del terreno (`POST /terrenos/{id}/fotos`, especificación §4.1): las sube el
 * cliente dueño y las ve el piloto en el detalle de la solicitud antes de ofertar. Todo parte
 * del cliente de la cuenta (invariante 5): la foto o el terreno de otro cliente responde como
 * si no existiera. El archivo vive en el disco privado y se entrega por una URL firmada de
 * corta vida, nunca por una pública.
 */
final class GestionarFotosDeTerreno
{
    public function __construct(
        private readonly GestionarTerrenos $terrenos,
        private readonly ClienteDeLaCuenta $clientes,
    ) {}

    /** @throws SolicitudInvalida */
    public function subir(int $usuarioId, int $terrenoId, UploadedFile $archivo, ?string $descripcion): TerrenoFoto
    {
        $this->clientes->activo($usuarioId);
        $terreno = $this->terrenos->obtener($usuarioId, $terrenoId);

        $maximo = (int) config('solicitudes.fotos_terreno.maximo_por_terreno', 8);

        if (TerrenoFoto::query()->where('terreno_id', $terreno->id)->count() >= $maximo) {
            throw SolicitudInvalida::fotosDeTerrenoLlenas($maximo);
        }

        $ruta = $archivo->store("terrenos/{$terreno->id}/fotos", 'local');

        return TerrenoFoto::query()->create([
            'terreno_id' => $terreno->id,
            'archivo' => (string) $ruta,
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

    /** La ruta absoluta del archivo, o `null` si ya no está en el disco. */
    public function ruta(int $fotoId): ?string
    {
        $foto = TerrenoFoto::query()->find($fotoId);

        if ($foto === null || ! Storage::disk('local')->exists($foto->archivo)) {
            return null;
        }

        return Storage::disk('local')->path($foto->archivo);
    }

    /** URL firmada de corta vida (relativa: la app la une a su base) para una foto. */
    public static function url(int $fotoId): string
    {
        return URL::temporarySignedRoute(
            'api.terrenos.fotos.archivo',
            Carbon::now()->addMinutes((int) config('solicitudes.fotos_terreno.url_minutos', 15)),
            ['foto' => $fotoId],
            absolute: false,
        );
    }
}
