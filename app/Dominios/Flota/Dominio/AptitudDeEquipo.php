<?php

namespace App\Dominios\Flota\Dominio;

/**
 * Regla de elegibilidad de un equipo (especificación §5.2, §6 y ADR 0030): un
 * proveedor aprobado solo recibe ofertas si tiene al menos un equipo vigente
 * con piloto y dron aprobados y documentos vigentes; el auxiliar, si el equipo
 * lo lleva, también debe estar aprobado. Pura: recibe los hechos ya resueltos.
 */
final class AptitudDeEquipo
{
    /**
     * @param  string|null  $estadoAuxiliar  `null` si el equipo no lleva auxiliar.
     * @return list<MotivoNoApto> vacío si el equipo es apto.
     */
    public static function motivosDeNoAptitud(
        string $estadoProveedor,
        string $estadoPiloto,
        string $estadoDron,
        ?string $estadoAuxiliar,
        bool $documentosPilotoCompletos,
        bool $documentosDronCompletos,
        Vigencia $vigencia,
        string $hoy,
    ): array {
        $motivos = [];

        if ($estadoProveedor !== 'aprobado') {
            $motivos[] = MotivoNoApto::ProveedorNoAprobado;
        }

        if ($estadoPiloto !== 'aprobado') {
            $motivos[] = MotivoNoApto::PilotoNoAprobado;
        }

        if ($estadoDron !== 'aprobado') {
            $motivos[] = MotivoNoApto::DronNoAprobado;
        }

        if ($estadoAuxiliar !== null && $estadoAuxiliar !== 'aprobado') {
            $motivos[] = MotivoNoApto::AuxiliarNoAprobado;
        }

        if (! $documentosPilotoCompletos) {
            $motivos[] = MotivoNoApto::DocumentosPilotoVencidos;
        }

        if (! $documentosDronCompletos) {
            $motivos[] = MotivoNoApto::DocumentosDronVencidos;
        }

        if (! $vigencia->estaVigenteEl($hoy)) {
            $motivos[] = MotivoNoApto::FueraDeVigencia;
        }

        return $motivos;
    }

    /** Un proveedor aprobado cuyo equipo no es apto no recibe ofertas. */
    public static function esApto(
        string $estadoProveedor,
        string $estadoPiloto,
        string $estadoDron,
        ?string $estadoAuxiliar,
        bool $documentosPilotoCompletos,
        bool $documentosDronCompletos,
        Vigencia $vigencia,
        string $hoy,
    ): bool {
        return self::motivosDeNoAptitud(
            $estadoProveedor, $estadoPiloto, $estadoDron, $estadoAuxiliar,
            $documentosPilotoCompletos, $documentosDronCompletos, $vigencia, $hoy,
        ) === [];
    }
}
