<?php

namespace App\Dominios\Operaciones\Infraestructura\Pdf;

/**
 * Única fuente de los colores de los PDF (acta y reporte técnico).
 *
 * dompdf renderiza fuera del navegador: no carga las hojas de Vite ni resuelve
 * `var(--ag-…)`, y un PDF descargado no reacciona a `[data-bs-theme]`. Por eso
 * los colores de los documentos viven aquí, como constantes con nombre
 * semántico, y las plantillas Blade los referencian por constante — nunca por
 * literal ni por nombre CSS (`TokensColorTest`, invariante 11 de CLAUDE.md).
 */
final class PaletaPdf
{
    /** Texto principal del documento. */
    public const TEXTO = '#000000';

    /** Texto secundario: subtítulos, notas, leyendas. */
    public const TEXTO_SUAVE = '#696969';

    /** Bordes de tablas, títulos de sección y capturas. */
    public const BORDE = '#808080';

    /** Fondo de las celdas de encabezado. */
    public const FONDO_ENCABEZADO = '#f5f5f5';
}
