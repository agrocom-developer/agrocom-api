<?php

namespace App\Dominios\Proveedores\Aplicacion;

use App\Dominios\Compartido\Dominio\TipoDocumento;
use App\Dominios\Proveedores\Contratos\VerificacionDocumental;
use App\Dominios\Proveedores\Dominio\RequisitosDocumentales;
use App\Dominios\Proveedores\Dominio\TipoSujeto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Documento;
use DateTimeImmutable;

/**
 * Qué documentos obligatorios le faltan hoy a un sujeto (política de
 * verificación). Lee los documentos y delega la regla en
 * {@see RequisitosDocumentales}; el catálogo de requisitos sale de
 * `config/proveedores.php`.
 */
final class EstadoDocumentalDeSujeto implements VerificacionDocumental
{
    /** @return list<TipoDocumento> */
    public function faltantesDe(TipoSujeto $sujeto, int $sujetoId, ?DateTimeImmutable $hoy = null): array
    {
        $hoy ??= new DateTimeImmutable('today');

        $documentos = Documento::query()
            ->where('sujeto_tipo', $sujeto->value)
            ->where('sujeto_id', $sujetoId)
            ->get(['tipo', 'estado', 'vence_en'])
            ->map(static fn (Documento $documento): array => [
                'tipo' => $documento->tipo->value,
                'estado' => $documento->estado->value,
                'vence_en' => $documento->vence_en?->format('Y-m-d'),
            ])
            ->all();

        return $this->requisitos()->faltantes($sujeto, $documentos, $hoy);
    }

    public function faltantes(string $sujetoTipo, int $sujetoId): array
    {
        return array_map(
            static fn (TipoDocumento $tipo): string => $tipo->value,
            $this->faltantesDe(TipoSujeto::from($sujetoTipo), $sujetoId),
        );
    }

    public function requisitos(): RequisitosDocumentales
    {
        /** @var array<string, list<string>> $catalogo */
        $catalogo = config('proveedores.documentos_requeridos', RequisitosDocumentales::POR_DEFECTO);

        return new RequisitosDocumentales($catalogo);
    }
}
