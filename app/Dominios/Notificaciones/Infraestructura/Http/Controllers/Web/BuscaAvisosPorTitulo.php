<?php

namespace App\Dominios\Notificaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use Illuminate\Support\Str;

/**
 * Búsqueda por texto de los listados de avisos. El título de un aviso no se guarda: se
 * arma desde su tipo, así que se busca entre los títulos de cada tipo y se filtra por los
 * tipos que coinciden. Sin tildes ni mayúsculas.
 */
trait BuscaAvisosPorTitulo
{
    /**
     * @return list<string> valores de `TipoNotificacion` cuyo título contiene el término
     */
    private function tiposPorTitulo(string $termino): array
    {
        $buscado = Str::ascii(mb_strtolower($termino));

        return array_values(array_map(
            static fn (TipoNotificacion $tipo): string => $tipo->value,
            array_filter(
                TipoNotificacion::cases(),
                static fn (TipoNotificacion $tipo): bool => str_contains(Str::ascii(mb_strtolower(__($tipo->claveDeTitulo()))), $buscado),
            ),
        ));
    }
}
