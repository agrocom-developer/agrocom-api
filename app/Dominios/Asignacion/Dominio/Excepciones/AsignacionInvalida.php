<?php

namespace App\Dominios\Asignacion\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Una operación de asignación que las reglas no permiten. `esAjeno`: el
 * registro existe pero no es de quien pregunta, y la API responde 404
 * (invariante 5 de CLAUDE.md). `esConflicto`: la oferta ya no está disponible
 * porque otro la ganó, venció o se retiró, y la API responde 409.
 */
final class AsignacionInvalida extends RuntimeException
{
    private function __construct(
        string $mensaje,
        public readonly bool $esAjeno = false,
        public readonly bool $esConflicto = false,
        public readonly ?string $campo = null,
    ) {
        parent::__construct($mensaje);
    }

    public static function sinProveedor(): self
    {
        return new self(Texto::de('asignacion.errores.sin_proveedor'), true);
    }

    public static function ofertaAjena(): self
    {
        return new self(Texto::de('asignacion.errores.oferta_ajena'), true);
    }

    public static function solicitudAjena(): self
    {
        return new self(Texto::de('asignacion.errores.solicitud_ajena'), true);
    }

    public static function ofertaNoDisponible(string $estado): self
    {
        return new self(Texto::de('asignacion.errores.oferta_no_disponible', ['estado' => Texto::de('asignacion.estado_oferta.'.$estado)]), false, true);
    }

    public static function ofertaVencida(): self
    {
        return new self(Texto::de('asignacion.errores.oferta_vencida'), false, true);
    }

    public static function solicitudYaAsignada(): self
    {
        return new self(Texto::de('asignacion.errores.solicitud_ya_asignada'), false, true);
    }

    public static function sinEquipoDisponible(): self
    {
        return new self(Texto::de('asignacion.errores.sin_equipo_disponible'), false, false, 'equipo_id');
    }

    public static function equipoNoApto(): self
    {
        return new self(Texto::de('asignacion.errores.equipo_no_apto'), false, false, 'equipo_id');
    }

    public static function motivoRequerido(): self
    {
        return new self(Texto::de('asignacion.errores.motivo_requerido'), false, false, 'motivo');
    }

    public static function proveedorNoAsignado(): self
    {
        return new self(Texto::de('asignacion.errores.proveedor_no_asignado'), true);
    }

    public static function solicitudNoCancelable(): self
    {
        return new self(Texto::de('asignacion.errores.solicitud_no_cancelable'));
    }

    public static function solicitudNoForzable(): self
    {
        return new self(Texto::de('asignacion.errores.solicitud_no_forzable'));
    }

    public static function proveedorNoElegible(): self
    {
        return new self(Texto::de('asignacion.errores.proveedor_no_elegible'), false, false, 'proveedor_id');
    }

    public static function proveedorSancionado(): self
    {
        return new self(Texto::de('asignacion.errores.proveedor_sancionado'));
    }
}
