<?php

namespace App\Dominios\Calificaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Una operación de calificación que las reglas no permiten. `esAjeno`: el
 * registro existe pero no es de quien pregunta y la API responde 404 (invariante
 * 5 de CLAUDE.md). `esConflicto`: ya hay una calificación para ese sujeto y la API
 * responde 409.
 */
final class CalificacionInvalida extends RuntimeException
{
    private function __construct(
        string $mensaje,
        public readonly bool $esAjeno = false,
        public readonly bool $esConflicto = false,
        public readonly ?string $campo = null,
    ) {
        parent::__construct($mensaje);
    }

    public static function servicioAjeno(): self
    {
        return new self(Texto::de('calificaciones.errores.servicio_ajeno'), true);
    }

    public static function calificacionAjena(): self
    {
        return new self(Texto::de('calificaciones.errores.calificacion_ajena'), true);
    }

    public static function sinProveedor(): self
    {
        return new self(Texto::de('calificaciones.errores.sin_proveedor'), true);
    }

    public static function servicioNoCompletado(): self
    {
        return new self(Texto::de('calificaciones.errores.servicio_no_completado'));
    }

    public static function plazoVencido(int $dias): self
    {
        return new self(Texto::de('calificaciones.errores.plazo_vencido', ['dias' => $dias]));
    }

    public static function sujetoInvalido(string $tipo): self
    {
        return new self(Texto::de('calificaciones.errores.sujeto_invalido', ['sujeto' => $tipo]), false, false, 'sujeto_tipo');
    }

    public static function etiquetaInvalida(string $etiqueta): self
    {
        return new self(Texto::de('calificaciones.errores.etiqueta_invalida', ['etiqueta' => $etiqueta]), false, false, 'etiquetas');
    }

    public static function yaCalificado(): self
    {
        return new self(Texto::de('calificaciones.errores.ya_calificado'), false, true);
    }

    public static function uuidEnUso(): self
    {
        return new self(Texto::de('calificaciones.errores.uuid_en_uso'), false, false, 'uuid_cliente');
    }

    public static function motivoRequerido(): self
    {
        return new self(Texto::de('calificaciones.errores.motivo_requerido'), false, false, 'motivo');
    }

    public static function causaRequerida(): self
    {
        return new self(Texto::de('calificaciones.errores.causa_requerida'), false, false, 'causa');
    }

    public static function noReportable(): self
    {
        return new self(Texto::de('calificaciones.errores.no_reportable'));
    }

    public static function transicionNoPermitida(string $desde, string $hacia): self
    {
        return new self(Texto::de('calificaciones.errores.transicion_no_permitida', [
            'desde' => Texto::de('calificaciones.estado.'.$desde),
            'hacia' => Texto::de('calificaciones.estado.'.$hacia),
        ]), false, true);
    }

    public static function propiaCalificacion(): self
    {
        return new self(Texto::de('calificaciones.errores.propia_calificacion'));
    }
}
