<?php

namespace App\Dominios\Comercial\Contratos;

/**
 * Estado de cuenta de un contrato para la tab "Estado de cuentas" del
 * dashboard del dueño (ADR 0003, regla 2; tarea 135): lo contratado, lo ya
 * facturado, el adelanto pedido y el saldo que todavía falta facturar.
 *
 * `montoFacturado` es el MISMO número que `AvanceClientePanel` (delegado en
 * `Aplicacion\ObtenerAvanceComercial`, HU-32) — esta tarjeta no sabe de
 * hectáreas, solo agrega lo que ese cálculo ya resuelve con el
 * `monto_total`/`adelanto_monto` propios de `Infraestructura\Eloquent\Contrato`.
 *
 * `saldoPendiente` es `montoContratado - montoFacturado`, nunca negativo: un
 * contrato sobrefacturado (no debería pasar, pero la resta sola podría dar
 * negativo) se muestra en cero, no como saldo a favor del cliente — eso es
 * una nota contable que este panel no modela.
 *
 * Todas las magnitudes son string decimal (invariante 6 de CLAUDE.md).
 */
final readonly class EstadoCuentaContratoPanel
{
    public function __construct(
        public int $contratoId,
        public string $clienteNombre,
        public string $montoContratado,
        public string $montoFacturado,
        public string $adelantoMonto,
        public string $saldoPendiente,
    ) {}
}
