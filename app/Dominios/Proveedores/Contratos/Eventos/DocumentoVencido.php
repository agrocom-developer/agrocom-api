<?php

namespace App\Dominios\Proveedores\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio: un documento aprobado venció (ADR 0032, punto 4). El
 * proceso diario ya lo marcó `vencido`. `Flota` reacciona suspendiendo al dron
 * cuando el sujeto es un dron; para proveedor, piloto y auxiliar el propio
 * módulo `Proveedores` suspende en el mismo proceso. DTO de primitivos.
 */
final readonly class DocumentoVencido implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $documentoId,
        public string $sujetoTipo,
        public int $sujetoId,
        public string $tipoDocumento,
        public string $venceEn,
        /** @var list<int> */
        public array $usuariosDestinatarios,
    ) {}
}
