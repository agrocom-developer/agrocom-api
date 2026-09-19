<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Estados de la orden de aplicación (espec §5; CHECK en ope_ordenes_aplicacion).
 * Solo nombra los estados: las transiciones permitidas y sus guardas viven en
 * `Dominio/MaquinaEstados/TransicionesOrden` y en el servicio de dominio
 * `Aplicacion/MaquinaEstados/MaquinaEstadosOrden` (invariante 7).
 *
 * Reforma 19/9/2026 (ADR 0022): la orden es UNA aplicación completa del
 * contrato, correlativa, y de a una por vez. Por eso hay estados "abiertos"
 * (`emitida`, `vigente`, `pausada`: la aplicación sigue en curso) y estados
 * "cerrados" (`consumida`, `cancelada`, `vencida`). `pausada` es una detención
 * a la espera de resolver un problema (clima, logística, pago…); `cancelada`
 * guarda causa y motivo, y solo la decide el panel — la app de campo nunca
 * cancela. `vencida` sigue sin disparador de negocio: queda como estaba.
 */
enum EstadoOrdenAplicacion: string
{
    case Emitida = 'emitida';
    case Vigente = 'vigente';
    case Pausada = 'pausada';
    case Consumida = 'consumida';
    case Cancelada = 'cancelada';
    case Vencida = 'vencida';

    /**
     * ¿La aplicación sigue en curso? Un contrato solo admite UNA orden abierta
     * por vez: la siguiente se emite cuando esta se cierra o se cancela.
     */
    public function estaAbierta(): bool
    {
        return $this === self::Emitida || $this === self::Vigente || $this === self::Pausada;
    }

    /**
     * Valores de los estados abiertos, listos para un `whereIn`.
     *
     * @return list<string>
     */
    public static function valoresAbiertos(): array
    {
        return array_values(array_map(
            fn (self $estado): string => $estado->value,
            array_filter(self::cases(), fn (self $estado): bool => $estado->estaAbierta()),
        ));
    }
}
