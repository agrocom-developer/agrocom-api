<?php

namespace App\Dominios\Compartido\Dominio;

/** Catálogo de documentos de la política de verificación (especificación §4.3). */
enum TipoDocumento: string
{
    case Identidad = 'identidad';
    case LicenciaPiloto = 'licencia_piloto';
    case HabilitacionDron = 'habilitacion_dron';
    case SeguroResponsabilidad = 'seguro_responsabilidad';
    case RegistroDron = 'registro_dron';
    case CertificadoFumigacion = 'certificado_fumigacion';
    case Antecedentes = 'antecedentes';
    case Otro = 'otro';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(static fn (self $tipo): string => $tipo->value, self::cases());
    }
}
