<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Dominio\Excepciones\DispositivoNoEncontrado;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecTokenDispositivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;

/**
 * Un dispositivo del usuario autenticado, por id (HU-03).
 *
 * Existe como caso de uso propio en vez de resolverse con route model binding
 * justamente por el scoping: el binding hidrataría la fila desde la tabla
 * global y recién después habría que comprobar el dueño — el "where agregado
 * al final" que la invariante 5 de CLAUDE.md prohíbe. Acá la consulta arranca
 * en `$usuario->tokens()`, así que un id ajeno simplemente no aparece.
 *
 * Un dispositivo de otro usuario devuelve 404, no 403
 * ({@see DispositivoNoEncontrado}): un 403 confirmaría que ese id existe.
 */
final class BuscarDispositivoDeUsuario
{
    /**
     * @throws DispositivoNoEncontrado si el id no existe o es de otro usuario
     *                                 — indistinguibles a propósito.
     */
    public function ejecutar(SecUser $usuario, int $idDispositivo): SecTokenDispositivo
    {
        $dispositivo = $usuario->tokens()->whereKey($idDispositivo)->first();

        if ($dispositivo === null) {
            throw DispositivoNoEncontrado::paraUsuario($usuario->id, $idDispositivo);
        }

        return $dispositivo;
    }
}
