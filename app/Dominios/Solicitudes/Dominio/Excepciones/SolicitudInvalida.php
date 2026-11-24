<?php

namespace App\Dominios\Solicitudes\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/** Una operación sobre una solicitud o un terreno que las reglas del cliente no permiten. */
final class SolicitudInvalida extends RuntimeException
{
    /** `esAjeno`: el registro existe pero no es del cliente que pregunta; la API responde 404 (invariante 5). */
    private function __construct(string $mensaje, public readonly bool $esAjeno = false, public readonly ?string $campo = null)
    {
        parent::__construct($mensaje);
    }

    public static function sinCliente(): self
    {
        return new self(Texto::de('solicitudes.errores.sin_cliente'));
    }

    public static function clienteSuspendido(): self
    {
        return new self(Texto::de('solicitudes.errores.cliente_suspendido'));
    }

    public static function publicacionLimitada(): self
    {
        return new self(Texto::de('solicitudes.errores.publicacion_limitada'));
    }

    public static function terrenoAjeno(): self
    {
        return new self(Texto::de('solicitudes.errores.terreno_ajeno'), true);
    }

    public static function solicitudAjena(): self
    {
        return new self(Texto::de('solicitudes.errores.solicitud_ajena'), true);
    }

    public static function uuidEnUso(): self
    {
        return new self(Texto::de('solicitudes.errores.uuid_en_uso'), false, 'uuid_cliente');
    }

    public static function hectareasExcedenTerreno(string $solicitadas, string $disponibles): self
    {
        return new self(Texto::de('solicitudes.errores.hectareas_exceden_terreno', [
            'solicitadas' => $solicitadas,
            'disponibles' => $disponibles,
        ]), false, 'hectareas_solicitadas');
    }

    public static function ventanaVencida(): self
    {
        return new self(Texto::de('solicitudes.errores.ventana_vencida'), false, 'ventana_hasta');
    }

    public static function motivoRequerido(): self
    {
        return new self(Texto::de('solicitudes.errores.motivo_requerido'), false, 'motivo');
    }

    public static function noSePuedeEditar(): self
    {
        return new self(Texto::de('solicitudes.errores.no_se_puede_editar'));
    }

    public static function terrenoConSolicitudesVivas(): self
    {
        return new self(Texto::de('solicitudes.errores.terreno_con_solicitudes_vivas'));
    }
}
