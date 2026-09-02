<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use DomainException;

/**
 * `POST /api/actas/{uuid_cliente}/firmar` (HU-17, tarea 24) no puede aplicar
 * la firma pedida — la evidencia referenciada no sirve, o el acta ya está
 * firmada con una evidencia DISTINTA a la de este pedido (un reintento con
 * la MISMA evidencia es idempotente, no lanza esta excepción — ver
 * `Aplicacion/FirmarActa`).
 */
final class FirmaActaNoDisponible extends DomainException
{
    public static function porEvidenciaInexistenteOTipoInvalido(): self
    {
        return new self('La evidencia de firma no existe o no es del tipo firma_acta.');
    }

    public static function porEvidenciaYaUsada(int $evidenciaId): self
    {
        return new self("La evidencia #{$evidenciaId} ya respalda la firma de otra acta.");
    }

    public static function porActaYaFirmadaConOtraEvidencia(int $actaId): self
    {
        return new self("El acta #{$actaId} ya está firmada con otra evidencia.");
    }

    /** Dos firmas concurrentes de actas distintas con la misma evidencia (índice único parcial). */
    public static function porConflictoConcurrente(): self
    {
        return new self('La evidencia de firma ya fue tomada por otra acta en un pedido concurrente.');
    }
}
