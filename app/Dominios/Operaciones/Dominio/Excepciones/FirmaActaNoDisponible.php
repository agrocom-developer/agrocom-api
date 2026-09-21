<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
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
        return new self(Texto::de('operaciones.errores.firma_evidencia_invalida'));
    }

    public static function porEvidenciaYaUsada(int $evidenciaId): self
    {
        return new self(Texto::de('operaciones.errores.firma_evidencia_reutilizada', ['id' => $evidenciaId]));
    }

    public static function porActaYaFirmadaConOtraEvidencia(int $actaId): self
    {
        return new self(Texto::de('operaciones.errores.acta_ya_firmada_con_otra_evidencia', ['id' => $actaId]));
    }

    /** Dos firmas concurrentes de actas distintas con la misma evidencia (índice único parcial). */
    public static function porConflictoConcurrente(): self
    {
        return new self(Texto::de('operaciones.errores.firma_evidencia_conflicto_concurrente'));
    }
}
