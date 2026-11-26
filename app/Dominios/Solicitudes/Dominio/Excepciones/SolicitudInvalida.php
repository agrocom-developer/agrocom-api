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

    public static function clienteYaEnEseEstado(): self
    {
        return new self(Texto::de('solicitudes.errores.cliente_ya_en_ese_estado'));
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

    /** El terreno de la solicitud no tiene departamento: no se puede publicar sin ubicación (ADR 0045). */
    public static function terrenoSinDepartamento(): self
    {
        return new self(Texto::de('solicitudes.errores.terreno_sin_departamento'), false, 'departamento_codigo');
    }

    public static function terrenoSinProvincia(): self
    {
        return new self(Texto::de('solicitudes.errores.terreno_sin_provincia'), false, 'provincia_codigo');
    }

    /** La provincia del terreno no pertenece a su departamento (o ya no está en el catálogo). */
    public static function provinciaNoEsDelDepartamento(): self
    {
        return new self(Texto::de('solicitudes.errores.provincia_no_es_del_departamento'), false, 'provincia_codigo');
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

    public static function sinTelefono(): self
    {
        return new self(Texto::de('solicitudes.errores.sin_telefono'), false, 'telefono');
    }

    public static function precioRequerido(): self
    {
        return new self(Texto::de('solicitudes.errores.precio_requerido'), false, 'precio_ha_propuesto');
    }

    public static function precioExcedeMaximo(string $maximo): self
    {
        return new self(Texto::de('solicitudes.errores.precio_excede_maximo', ['maximo' => $maximo]), false, 'precio_ha_propuesto');
    }

    public static function precioNoEditable(): self
    {
        return new self(Texto::de('solicitudes.errores.precio_no_editable'));
    }

    public static function noRenovable(): self
    {
        return new self(Texto::de('solicitudes.errores.no_renovable'));
    }

    public static function fotosDeTerrenoLlenas(int $maximo): self
    {
        return new self(Texto::de('solicitudes.errores.fotos_de_terreno_llenas', ['maximo' => $maximo]), false, 'archivo');
    }

    public static function fotoDeTerrenoAjena(): self
    {
        return new self(Texto::de('solicitudes.errores.foto_de_terreno_ajena'), true);
    }

    public static function contactoAjeno(): self
    {
        return new self(Texto::de('solicitudes.errores.contacto_ajeno'), true);
    }

    /** El `contacto_id` enviado en una solicitud no es de un contacto vigente del cliente. */
    public static function contactoNoValido(): self
    {
        return new self(Texto::de('solicitudes.errores.contacto_no_valido'), false, 'contacto_id');
    }

    public static function contactoRequerido(): self
    {
        return new self(Texto::de('solicitudes.errores.contacto_requerido'), false, 'contacto_id');
    }

    public static function ultimoContactoDeEmpresa(): self
    {
        return new self(Texto::de('solicitudes.errores.ultimo_contacto_de_empresa'));
    }
}
