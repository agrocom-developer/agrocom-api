<?php

namespace App\Dominios\Proveedores\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio: un administrador aprobó un documento. `Flota` lo escucha
 * para levantar la suspensión automática de un dron cuando vuelve a tener todos
 * sus documentos vigentes. DTO de primitivos.
 */
final readonly class DocumentoAprobado implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $documentoId,
        public string $sujetoTipo,
        public int $sujetoId,
        public string $tipoDocumento,
    ) {}
}
