<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use App\Dominios\Comercial\Aplicacion\Contrato\VerificadorLotesDelContrato;
use RuntimeException;

/**
 * Un lote elegido para el contrato no pertenece a ninguna propiedad del
 * `cliente_id` de ese contrato (tarea "contratos-lotes", 16/9/2026): guarda
 * de integridad cruzando `com_lotes` → `com_propiedades` → `com_clientes`.
 * Vive en `Aplicacion/` (via
 * {@see VerificadorLotesDelContrato::loteAjenoAlCliente()}),
 * no en el FormRequest, mismo criterio histórico documentado en
 * `CrearContratoRequest` para `campania_id`: cruzar tres tablas para decidir
 * "pertenece a este cliente" es una guarda de negocio (invariante 5 de
 * CLAUDE.md aplicada al panel interno), no un simple `exists`.
 */
final class LoteAjenoAlCliente extends RuntimeException
{
    public static function paraLote(string $codigoLote): self
    {
        return new self("El lote '{$codigoLote}' no pertenece a ninguna propiedad del cliente elegido.");
    }
}
