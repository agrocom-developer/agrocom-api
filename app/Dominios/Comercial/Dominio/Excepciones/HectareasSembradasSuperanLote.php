<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Guarda de aplicación de HU-48 (tarea 71, etapa 2, prompt punto 3): un
 * lote no puede declararse sembrado con más hectáreas de las que tiene. La
 * verifica `Aplicacion/Siembra/GuardarSiembra` comparando con
 * `Brick\Math\BigDecimal` (invariante 6 de CLAUDE.md; `bcmath` no está
 * instalado en este entorno) — nunca con `float`, que redondea distinto
 * según la implementación y puede dejar pasar un lote sobre-sembrado por un
 * error de precisión.
 */
final class HectareasSembradasSuperanLote extends RuntimeException
{
    public static function paraLote(string $codigoLote, string $hectareasSembradas, string $hectareasLote): self
    {
        return new self(Texto::de('comercial.errores.hectareas_sembradas_superan_lote', [
            'sembradas' => $hectareasSembradas,
            'codigo' => $codigoLote,
            'hectareas' => $hectareasLote,
        ]));
    }
}
