<?php

namespace App\Dominios\Proveedores\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio: un documento aprobado vence dentro de `$diasRestantes`
 * días (aviso previo de 30 y de 7 días, ADR 0032, punto 4). Avisa a la cuenta
 * responsable del sujeto (`$usuariosDestinatarios`). DTO de primitivos.
 */
final readonly class DocumentoPorVencer implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $documentoId,
        public string $sujetoTipo,
        public int $sujetoId,
        public string $tipoDocumento,
        public string $venceEn,
        public int $diasRestantes,
        /** @var list<int> */
        public array $usuariosDestinatarios,
    ) {}
}
