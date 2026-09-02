<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Aplicacion\MaquinaEstados\MaquinaEstadosContrato;
use App\Dominios\Comercial\Dominio\Excepciones\VentanasContratoSolapadas;
use App\Dominios\Comercial\Dominio\ValidadorSolapamientoVentanas;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * Alta de un contrato con sus ventanas horarias en una sola operación (HU-23,
 * tarea 34): mismo criterio que `CrearCliente` — el formulario es uno solo,
 * así que el contrato y sus ventanas nacen en la misma transacción.
 *
 * El estado inicial (`borrador`) lo fija
 * {@see MaquinaEstadosContrato::crear()}, nunca esta clase directamente
 * (invariante 7).
 */
final class CrearContrato
{
    public function __construct(private readonly MaquinaEstadosContrato $maquinaEstados) {}

    /**
     * @param  array<string, mixed>  $datosContrato  sin `estado` ni `monto_total`: los fija esta clase.
     * @param  list<array{hora_inicio: string, hora_fin: string}>  $ventanas
     *
     * @throws VentanasContratoSolapadas si dos ventanas del alta se solapan entre sí.
     */
    public function ejecutar(array $datosContrato, array $ventanas): Contrato
    {
        $solapamiento = ValidadorSolapamientoVentanas::primerSolapamiento($ventanas);

        if ($solapamiento !== null) {
            [$a, $b] = $solapamiento;

            throw VentanasContratoSolapadas::entre($a['hora_inicio'], $a['hora_fin'], $b['hora_inicio'], $b['hora_fin']);
        }

        return DB::transaction(function () use ($datosContrato, $ventanas): Contrato {
            $datosContrato['monto_total'] = $this->calcularMontoTotal(
                (string) $datosContrato['hectareas_contratadas'],
                (int) $datosContrato['aplicaciones_previstas'],
                (string) $datosContrato['precio_ha'],
            );

            $contrato = $this->maquinaEstados->crear($datosContrato);

            foreach ($ventanas as $ventana) {
                $contrato->ventanas()->create($ventana);
            }

            return $contrato->refresh();
        });
    }

    /**
     * `monto_total` NUNCA es input libre (invariante 6 de CLAUDE.md): el
     * comentario de la migración lo dice explícito — "Recalculable:
     * hectareas_contratadas × aplicaciones_previstas × precio_ha". Con
     * `Brick\Math\BigDecimal` en vez de `bcmath` (no instalado en este repo,
     * ver `Finanzas\Aplicacion\GenerarDevengosSesion::calcularMonto()` para
     * el precedente y el porqué) y redondeo al centavo con `HalfUp`: truncar
     * en vez de redondear sesgaría el monto sistemáticamente hacia abajo.
     */
    private function calcularMontoTotal(string $hectareas, int $aplicaciones, string $precioHa): string
    {
        return (string) BigDecimal::of($hectareas)
            ->multipliedBy($aplicaciones)
            ->multipliedBy($precioHa)
            ->toScale(2, RoundingMode::HalfUp);
    }
}
