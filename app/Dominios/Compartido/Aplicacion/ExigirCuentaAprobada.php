<?php

namespace App\Dominios\Compartido\Aplicacion;

use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Dominio\Excepciones\CuentaNoAprobada;

/**
 * La compuerta de la cuenta de app (ADR 0046, punto 12): una cuenta que todavía no está `aprobada`
 * puede entrar y ver sus pantallas, pero no operar en el mercado. Una sola regla para las dos
 * apps: el cliente publica, acepta y contraoferta; el proveedor oferta, contraoferta y acepta.
 * Quien llama pasa el estado de aprobación de la ficha de la cuenta (cliente, o proveedor) y lo que
 * falta para aprobarla; acá solo se decide.
 */
final class ExigirCuentaAprobada
{
    /**
     * @param  list<string>  $falta  Qué impide aprobar la cuenta, para guiar a la app.
     *
     * @throws CuentaNoAprobada si el estado no es `aprobado`.
     */
    public function exigir(EstadoAprobacion $estado, array $falta = []): void
    {
        if ($estado !== EstadoAprobacion::Aprobado) {
            throw new CuentaNoAprobada($estado, $falta);
        }
    }
}
