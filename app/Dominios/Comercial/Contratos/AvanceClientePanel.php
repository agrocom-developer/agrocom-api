<?php

namespace App\Dominios\Comercial\Contratos;

use App\Dominios\Comercial\Aplicacion\ObtenerAvanceComercial;

/**
 * Avance de un contrato para la sección "clientes" del dashboard (ADR 0003,
 * regla 2). Es la misma cuenta que el reporte comercial de HU-32
 * ({@see ObtenerAvanceComercial}), no una
 * segunda fórmula: el dashboard no puede decir un avance y el reporte otro.
 *
 * Todas las magnitudes son string decimal (invariante 6). `porcentaje` es un
 * entero 0-100 ya redondeado — es para pintar una barra, no para cuadrar.
 */
final readonly class AvanceClientePanel
{
    public function __construct(
        public int $contratoId,
        public string $clienteNombre,
        public string $hectareasContratadas,
        public string $hectareasAplicadas,
        public string $hectareasFacturadas,
        public string $montoFacturado,
        public int $porcentaje,
    ) {}
}
