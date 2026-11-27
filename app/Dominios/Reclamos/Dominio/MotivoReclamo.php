<?php

namespace App\Dominios\Reclamos\Dominio;

/**
 * Motivos de un reclamo (política de reclamos, «Motivos»). El cliente alega sobre
 * la ejecución; el proveedor, por un cobro pendiente, por acceso negado al lote o
 * por una calificación abusiva.
 */
enum MotivoReclamo: string
{
    case CoberturaIncompleta = 'cobertura_incompleta';
    case MalaAplicacion = 'mala_aplicacion';
    case DanoACultivosOTerceros = 'dano_a_cultivos_o_terceros';
    case ProductoDistinto = 'producto_distinto';
    case EquipoAusente = 'equipo_ausente';
    case EvidenciaFaltante = 'evidencia_faltante';
    case CobroIndebido = 'cobro_indebido';
    case TratoInadecuado = 'trato_inadecuado';
    case CobroPendiente = 'cobro_pendiente';
    case AccesoNegado = 'acceso_negado';
    case CalificacionAbusiva = 'calificacion_abusiva';

    /** @return list<self> */
    public static function delCliente(): array
    {
        return [
            self::CoberturaIncompleta, self::MalaAplicacion, self::DanoACultivosOTerceros, self::ProductoDistinto,
            self::EquipoAusente, self::EvidenciaFaltante, self::CobroIndebido, self::TratoInadecuado,
        ];
    }

    /** @return list<self> */
    public static function delProveedor(): array
    {
        return [self::CobroPendiente, self::AccesoNegado, self::CalificacionAbusiva];
    }

    /**
     * Estados de la solicitud en que el proveedor puede alegar el motivo: un cobro
     * se reclama tras el servicio; el acceso negado, desde que el equipo sale; una
     * calificación abusiva, con el servicio ya completado.
     *
     * @return list<string>
     */
    public function estadosDeSolicitudDelProveedor(): array
    {
        return match ($this) {
            self::CobroPendiente => ['finalizada', 'calificada', 'en_reclamo'],
            self::AccesoNegado => ['adjudicada', 'en_ejecucion', 'finalizada', 'calificada', 'en_reclamo', 'cancelada'],
            self::CalificacionAbusiva => ['finalizada', 'calificada'],
            default => [],
        };
    }
}
