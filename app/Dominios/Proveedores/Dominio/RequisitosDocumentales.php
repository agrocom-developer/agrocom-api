<?php

namespace App\Dominios\Proveedores\Dominio;

use App\Dominios\Compartido\Dominio\TipoDocumento;
use DateTimeImmutable;

/**
 * Documentos obligatorios por sujeto (política de verificación, ADR 0032) y la
 * regla de cuáles faltan. Pura: recibe el catálogo de requisitos (que la capa
 * de aplicación lee de la configuración, porque «el catálogo de documentos se
 * ajusta por parámetro») y los documentos del sujeto como datos simples.
 */
final class RequisitosDocumentales
{
    /** @var array<string, list<string>> Requisitos de la política vigente. */
    public const array POR_DEFECTO = [
        'proveedor' => ['identidad', 'seguro_responsabilidad'],
        'piloto' => ['identidad', 'licencia_piloto', 'antecedentes'],
        'dron' => ['registro_dron', 'habilitacion_dron', 'seguro_responsabilidad'],
    ];

    /** @param  array<string, list<string>>  $porSujeto */
    public function __construct(private readonly array $porSujeto = self::POR_DEFECTO) {}

    /** @return list<TipoDocumento> */
    public function paraSujeto(TipoSujeto $sujeto): array
    {
        return array_map(
            static fn (string $valor): TipoDocumento => TipoDocumento::from($valor),
            $this->porSujeto[$sujeto->value] ?? [],
        );
    }

    /**
     * Requisitos sin un documento aprobado y vigente a `$hoy`.
     *
     * @param  list<array{tipo: string, estado: string, vence_en: ?string}>  $documentos
     * @return list<TipoDocumento>
     */
    public function faltantes(TipoSujeto $sujeto, array $documentos, DateTimeImmutable $hoy): array
    {
        $vigentes = [];

        foreach ($documentos as $documento) {
            if (self::estaVigente($documento['estado'], $documento['vence_en'], $hoy)) {
                $vigentes[$documento['tipo']] = true;
            }
        }

        return array_values(array_filter(
            $this->paraSujeto($sujeto),
            static fn (TipoDocumento $tipo): bool => ! isset($vigentes[$tipo->value]),
        ));
    }

    /** Un documento cuenta solo si está aprobado y no venció (el día de vencimiento aún vale). */
    public static function estaVigente(string $estado, ?string $venceEn, DateTimeImmutable $hoy): bool
    {
        if ($estado !== EstadoDocumento::Aprobado->value) {
            return false;
        }

        return $venceEn === null || $venceEn >= $hoy->format('Y-m-d');
    }
}
