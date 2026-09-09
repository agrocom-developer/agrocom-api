<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Dominio\Excepciones\ContrasenaActualIncorrecta;
use App\Dominios\Seguridad\Dominio\Excepciones\UsuarioDuplicado;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;

/**
 * Caso de uso único de autoservicio (tarea 66): un usuario administra su
 * propio correo y contraseña — nunca los de otro, así que, igual que
 * {@see ActualizarPreferenciaUsuario}, `$usuario` es a la vez actor y
 * sujeto y no hace falta ninguna guarda de permiso.
 *
 * `$name` es OPCIONAL y `null` significa "no lo toques". Desde el 9/9/2026 el
 * panel siempre pasa `null`: el nombre de una cuenta interna dejó de ser
 * autoservicio y lo cambia un administrador desde Seguridad › Usuarios
 * ({@see ActualizarUsuario}). El motivo es que `plt_bitacoras` guarda solo
 * `user_id` y resuelve el nombre del actor por join contra el valor VIGENTE
 * ({@see ListarBitacora}), así que renombrarse reescribía la autoría de toda
 * la historia: una entrada de hace seis meses pasaba a decir quién es hoy esa
 * persona, no quién era cuando hizo la mutación. El portal del cliente sigue
 * mandándolo — ahí el nombre es el del contacto de la cuenta, no aparece como
 * actor en la bitácora del panel.
 *
 * Cambiar la contraseña exige la ACTUAL (verificada con `Hash::check`, nunca
 * confiando en la sesión sola) y cierra las demás sesiones del usuario —
 * {@see CerrarOtrasSesiones} — para que un dispositivo robado con sesión
 * abierta deje de servir apenas el dueño cambia la contraseña desde otro.
 */
final class ActualizarPerfilPropio
{
    public function __construct(private readonly CerrarOtrasSesiones $cerrarOtrasSesiones) {}

    /**
     * @throws ContrasenaActualIncorrecta si se pide `passwordNueva` sin la
     *                                    `passwordActual` correcta.
     * @throws UsuarioDuplicado si el `email` ya pertenece a otra cuenta viva.
     */
    public function ejecutar(
        SecUser $usuario,
        string $guard,
        string $idSesionActual,
        ?string $name,
        ?string $email,
        ?string $passwordActual,
        ?string $passwordNueva,
    ): SecUser {
        if ($passwordNueva !== null
            && ($passwordActual === null || ! Hash::check($passwordActual, $usuario->password))
        ) {
            throw ContrasenaActualIncorrecta::porIntento();
        }

        if ($name !== null) {
            $usuario->name = $name;
        }

        $usuario->email = $email;

        if ($passwordNueva !== null) {
            $usuario->password = $passwordNueva;
        }

        $usuario->updated_by = $usuario->id;

        try {
            $usuario->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicado($excepcion, $email);
        }

        $this->cerrarOtrasSesiones->ejecutar($usuario, $guard, $idSesionActual, $passwordNueva);

        return $usuario->refresh();
    }

    /**
     * Mismo criterio que `AsignarRolesUsuario::relanzarComoDuplicado()`: acá
     * el único índice único que puede violar una edición de perfil es el de
     * `email` (name/username no se editan por acá).
     *
     * @throws UsuarioDuplicado si la violación corresponde a `email`.
     * @throws QueryException si la violación no es esa.
     */
    private function relanzarComoDuplicado(QueryException $excepcion, ?string $email): never
    {
        $mensaje = $excepcion->getMessage();

        if ($email !== null
            && (str_contains($mensaje, 'sec_user_email_unico') || str_contains($mensaje, 'sec_user.email'))
        ) {
            throw UsuarioDuplicado::porEmail($email);
        }

        throw $excepcion;
    }
}
