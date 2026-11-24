<?php

namespace App\Dominios\Flota\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Una operación sobre drones o equipos que las reglas de la flota no admiten.
 * `esAjeno`: el registro existe pero no es de quien pregunta; la API responde
 * 404, nunca 403 (invariante 5 de CLAUDE.md).
 */
final class OperacionDeFlotaInvalida extends RuntimeException
{
    private function __construct(string $mensaje, public readonly ?string $campo = null, public readonly bool $esAjeno = false)
    {
        parent::__construct($mensaje);
    }

    public static function sinProveedor(): self
    {
        return new self(Texto::de('flota.errores.sin_proveedor'));
    }

    public static function sinEquipos(): self
    {
        return new self(Texto::de('flota.errores.sin_equipos'));
    }

    public static function proveedorNoAdmiteAltas(): self
    {
        return new self(Texto::de('flota.errores.proveedor_no_admite_altas'));
    }

    public static function motivoRequerido(): self
    {
        return new self(Texto::de('flota.errores.motivo_requerido'), 'motivo');
    }

    public static function dronAjeno(): self
    {
        return new self(Texto::de('flota.errores.dron_ajeno'), null, true);
    }

    public static function equipoAjeno(): self
    {
        return new self(Texto::de('flota.errores.equipo_ajeno'), null, true);
    }

    public static function dronNoEditable(): self
    {
        return new self(Texto::de('flota.errores.dron_no_editable'));
    }

    public static function numeroDeSerieDuplicado(string $serie): self
    {
        return new self(Texto::de('flota.errores.numero_serie_duplicado', ['serie' => $serie]), 'numero_serie');
    }

    public static function pilotoAjeno(): self
    {
        return new self(Texto::de('flota.errores.piloto_ajeno'), 'piloto_id');
    }

    public static function auxiliarAjeno(): self
    {
        return new self(Texto::de('flota.errores.auxiliar_ajeno'), 'auxiliar_id');
    }

    public static function integranteNoDisponible(string $rol): self
    {
        return new self(Texto::de('flota.errores.integrante_no_disponible', ['rol' => Texto::de('flota.equipos.rol.'.$rol)]), $rol.'_id');
    }

    public static function dronNoOperable(): self
    {
        return new self(Texto::de('flota.errores.dron_no_operable'), 'dron_id');
    }

    public static function vigenciaInvalida(): self
    {
        return new self(Texto::de('flota.errores.vigencia_invalida'), 'vigente_hasta');
    }

    public static function integranteEnOtroEquipo(string $rol): self
    {
        return new self(Texto::de('flota.errores.integrante_en_otro_equipo', ['rol' => Texto::de('flota.equipos.rol.'.$rol)]), $rol.'_id');
    }
}
