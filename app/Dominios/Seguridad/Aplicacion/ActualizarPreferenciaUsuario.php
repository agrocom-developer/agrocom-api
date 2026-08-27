<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Dominio\Excepciones\IdiomaNoSoportado;
use App\Dominios\Seguridad\Dominio\TemaPreferencia;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserPreferencia;

/**
 * Caso de uso único para leer/escribir la preferencia de panel de un usuario
 * (tema de color e idioma, HU-02; ADR 0011, extensión 27/8/2026, punto 8).
 * Sin guarda de permisos: es autoservicio — cada usuario administra su
 * propia preferencia, nunca la de otro, así que `$usuario` es a la vez el
 * actor y el sujeto de la operación (no hay parámetro `$actor` separado como
 * en {@see AsignarRolesUsuario}, que sí gobierna cuentas ajenas).
 *
 * Sin controlador HTTP en HU-02 (backend puro, igual que HU-01 con
 * `AsignarRolesUsuario`): el panel/Livewire invoca este caso de uso directo
 * con `Auth::user()` — no hace falta un viaje HTTP dentro del propio
 * monolito (ADR 0008).
 */
final class ActualizarPreferenciaUsuario
{
    /** @var list<string> Único idioma habilitado en v1 (ADR 0013). */
    private const IDIOMAS_SOPORTADOS = ['es'];

    /**
     * @throws IdiomaNoSoportado si `$idioma` no está entre los habilitados.
     */
    public function ejecutar(SecUser $usuario, TemaPreferencia $tema, string $idioma): SecUserPreferencia
    {
        if (! in_array($idioma, self::IDIOMAS_SOPORTADOS, true)) {
            throw IdiomaNoSoportado::paraCodigo($idioma);
        }

        $preferencia = SecUserPreferencia::query()->firstOrNew(['user_id' => $usuario->id]);

        $preferencia->tema = $tema;
        $preferencia->idioma = $idioma;

        if (! $preferencia->exists) {
            $preferencia->created_by = $usuario->id;
        }
        $preferencia->updated_by = $usuario->id;

        $preferencia->save();

        return $preferencia->refresh();
    }
}
