<?php

namespace App\Dominios\Seguridad\Contratos;

/**
 * La foto de perfil de una cuenta para los demás módulos: solo la URL firmada y de corta
 * vida, nunca la ruta del archivo. Quien la use debe entregarla únicamente a una cuenta
 * que ya tiene derecho a ver a esa persona (por ejemplo, el cliente al comparar ofertas).
 */
interface FotosDePerfil
{
    /** URL firmada (relativa a la base de la API) o `null` si la cuenta no tiene foto. */
    public function urlDe(int $usuarioId): ?string;
}
