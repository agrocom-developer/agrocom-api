<?php

namespace App\Dominios\Negociacion\Aplicacion;

use App\Dominios\Negociacion\Contratos\Eventos\AvisoDeNegociacion;

/**
 * Emite un {@see AvisoDeNegociacion} a las cuentas que lo quieren: filtra por la
 * categoría que cada una dejó activa en sus preferencias. Una categoría `null` es
 * obligatoria (adjudicación, cierre, expiración) y llega siempre.
 */
final class Avisador
{
    public function __construct(private readonly PreferenciasDeAviso $preferencias) {}

    /**
     * @param  array<string, string|int>  $parametros
     * @param  list<int>  $usuarios
     */
    public function avisar(string $tipo, string $claveEvento, array $parametros, int $solicitudId, array $usuarios, ?string $categoria = null): void
    {
        $destinatarios = array_values(array_filter(
            array_unique($usuarios),
            fn (int $usuarioId): bool => $categoria === null || $this->preferencias->permite($usuarioId, $categoria),
        ));

        if ($destinatarios === []) {
            return;
        }

        event(new AvisoDeNegociacion($tipo, $claveEvento, $parametros, $solicitudId, $destinatarios));
    }
}
