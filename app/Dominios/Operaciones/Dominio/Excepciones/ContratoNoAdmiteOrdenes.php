<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use DomainException;

/**
 * Solo un contrato `vigente` ("En Ejecución") admite órdenes de aplicación
 * (pedido del dueño, 18/9/2026, ADR 0022): la orden es la aplicación de un
 * contrato ya aprobado, cuyos lotes ya quedaron reservados para él (ADR 0021).
 * Antes se permitía emitirlas sobre cualquier contrato, incluso un borrador.
 */
final class ContratoNoAdmiteOrdenes extends DomainException
{
    public static function porEstado(int $contratoId): self
    {
        return new self(Texto::de('operaciones.errores.contrato_no_admite_ordenes', ['id' => $contratoId]));
    }

    public static function noExiste(int $contratoId): self
    {
        return new self(Texto::de('operaciones.errores.contrato_inexistente', ['id' => $contratoId]));
    }
}
