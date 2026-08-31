<?php

namespace App\Dominios\Seguridad\Dominio\Excepciones;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * El dispositivo pedido no existe o no pertenece al usuario del token que
 * hizo la consulta.
 *
 * **404, nunca 403**, y sin distinguir entre "no existe" y "es de otro": un
 * 403 confirmaría que ese id existe. Es la misma regla que la invariante 5 de
 * CLAUDE.md fija para el portal del cliente ("cliente A pidiendo un recurso
 * de cliente B → 404"), aplicada acá al listado de dispositivos de la app de
 * campo.
 *
 * Extiende la excepción HTTP de Symfony (no `ModelNotFoundException`) porque
 * la capa `Dominio` no puede depender de `Illuminate\Database` — lo verifica
 * `tests/Unit/ArquitecturaModulosTest.php`.
 */
final class DispositivoNoEncontrado extends NotFoundHttpException
{
    public static function paraUsuario(int $idUsuario, int $idDispositivo): self
    {
        return new self("El dispositivo #{$idDispositivo} no existe entre los del usuario #{$idUsuario}.");
    }
}
