<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Contratos\FotosDePerfil;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * La foto de perfil de la persona de la cuenta. Cada cuenta escribe solo la suya (el alcance
 * sale del token); reemplazarla borra el archivo anterior del disco privado. Se entrega por una
 * URL firmada de corta vida, nunca por una pública: la firma la emite solo el servidor a
 * quien ya tiene derecho a ver a esa persona.
 */
final class GestionarFotoDePerfil implements FotosDePerfil
{
    /** Vida de la URL firmada de la foto. */
    private const int URL_MINUTOS = 15;

    public function subir(int $usuarioId, UploadedFile $archivo): string
    {
        $usuario = SecUser::query()->findOrFail($usuarioId);
        $anterior = $usuario->foto_perfil_ref;

        $ruta = (string) $archivo->store("perfiles/{$usuario->id}", 'local');

        $usuario->forceFill(['foto_perfil_ref' => $ruta])->save();

        $this->borrarArchivo($anterior);

        return self::firmar($usuario->id);
    }

    public function quitar(int $usuarioId): void
    {
        $usuario = SecUser::query()->findOrFail($usuarioId);
        $anterior = $usuario->foto_perfil_ref;

        $usuario->forceFill(['foto_perfil_ref' => null])->save();

        $this->borrarArchivo($anterior);
    }

    public function urlDe(int $usuarioId): ?string
    {
        $tiene = SecUser::query()->whereKey($usuarioId)->whereNotNull('foto_perfil_ref')->exists();

        return $tiene ? self::firmar($usuarioId) : null;
    }

    /** La ruta absoluta del archivo, o `null` si la cuenta no tiene foto o ya no está en el disco. */
    public function ruta(int $usuarioId): ?string
    {
        $ref = SecUser::query()->whereKey($usuarioId)->value('foto_perfil_ref');

        if ($ref === null || ! Storage::disk('local')->exists($ref)) {
            return null;
        }

        return Storage::disk('local')->path($ref);
    }

    private static function firmar(int $usuarioId): string
    {
        return URL::temporarySignedRoute(
            'api.personas.foto',
            Carbon::now()->addMinutes(self::URL_MINUTOS),
            ['usuario' => $usuarioId],
            absolute: false,
        );
    }

    private function borrarArchivo(?string $ref): void
    {
        if ($ref !== null && Storage::disk('local')->exists($ref)) {
            Storage::disk('local')->delete($ref);
        }
    }
}
