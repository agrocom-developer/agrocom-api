<?php

namespace App\Dominios\Proveedores\Contratos;

use Illuminate\Http\UploadedFile;

/**
 * Carga de documentos de un sujeto, también para los de otro módulo (el dron
 * es de `Flota`, pero sus documentos viven en `prv_documentos`). Quien llama
 * ya verificó que el solicitante puede gestionar al sujeto.
 */
interface CargaDocumentos
{
    /**
     * @param  string  $sujetoTipo  `proveedor`, `piloto`, `auxiliar` o `dron`.
     * @param  string  $tipo  Valor de `TipoDocumento`.
     * @param  string|null  $venceEn  Fecha `Y-m-d`; `null` si el documento no vence.
     * @return int Id del documento, que queda `en_revision`.
     *
     * @throws DocumentoInvalido si el documento no se puede aceptar (por ejemplo, ya venció).
     */
    public function cargar(string $sujetoTipo, int $sujetoId, string $tipo, UploadedFile $archivo, ?string $venceEn, int $usuarioId): int;
}
