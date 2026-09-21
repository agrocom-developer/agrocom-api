<?php

namespace App\Dominios\Comercial\Dominio;

/**
 * Estados del contrato (espec §4.1; CHECK en com_contratos). Solo nombra los
 * estados: las transiciones permitidas y sus guardas irán en el servicio de
 * dominio de la máquina de estados cuando se implemente (invariante 7) —
 * nunca en un `estado = ...` suelto.
 *
 * `conflicto` (18/9/2026, ADR 0021): un contrato `borrador` que comparte al
 * menos un lote con otro contrato que YA retiene lotes en la misma campaña.
 * Lo fija solo el sistema (`MaquinaEstadosContrato::reconciliarConflictos()`),
 * nunca un usuario, y no se puede aprobar hasta que el choque desaparezca.
 */
enum EstadoContrato: string
{
    case Borrador = 'borrador';
    case Vigente = 'vigente';
    case Finalizado = 'finalizado';
    case Cancelado = 'cancelado';
    case Pausado = 'pausado';
    case Conflicto = 'conflicto';

    /**
     * ¿Un contrato en este estado tiene sus lotes bloqueados para cualquier
     * otro contrato de la misma campaña? Sí mientras está aprobado
     * (`vigente`) o interrumpido (`pausado`: una pausa no es una salida);
     * solo cancelar o finalizar los libera (ADR 0021).
     */
    public function retieneLotes(): bool
    {
        return $this === self::Vigente || $this === self::Pausado;
    }

    /**
     * Valores de los estados que retienen lotes, listos para un `whereIn`.
     *
     * @return list<string>
     */
    public static function valoresQueRetienenLotes(): array
    {
        return array_values(array_map(
            fn (self $estado): string => $estado->value,
            array_filter(self::cases(), fn (self $estado): bool => $estado->retieneLotes()),
        ));
    }
}
