<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Campania\Contratos\LecturaCampania;
use App\Dominios\Comercial\Aplicacion\MaquinaEstados\MaquinaEstadosContrato;
use App\Dominios\Comercial\Dominio\Excepciones\CampaniaCerrada;
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
 *
 * Guarda central de HU-46 (ADR 0015 punto 1, corregida el 15/9/2026): la
 * campaña elegida no puede estar `cerrada` — ya no se verifica de qué
 * cliente es, porque desde la corrección del 15/9/2026 la campaña es un
 * catálogo compartido, sin dueño. Se lee vía {@see LecturaCampania} (ADR
 * 0003 regla 2, frontera de `Campania`) — no con `DB::table` directo, porque
 * "está cerrada" es lógica de negocio de `Campania`, no una lectura plana
 * por FK.
 */
final class CrearContrato
{
    public function __construct(
        private readonly MaquinaEstadosContrato $maquinaEstados,
        private readonly LecturaCampania $lecturaCampania,
    ) {}

    /**
     * @param  array<string, mixed>  $datosContrato  sin `estado` ni `monto_total`: los fija esta clase.
     * @param  list<array{hora_inicio: string, hora_fin: string}>  $ventanas
     *
     * @throws VentanasContratoSolapadas si dos ventanas del alta se solapan entre sí.
     * @throws CampaniaCerrada si la campaña elegida está `cerrada`.
     */
    public function ejecutar(array $datosContrato, array $ventanas): Contrato
    {
        $this->verificarCampania((int) $datosContrato['campania_id']);

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

    /** @throws CampaniaCerrada si la campaña elegida está `cerrada`. */
    private function verificarCampania(int $campaniaId): void
    {
        $campania = $this->lecturaCampania->obtener($campaniaId);

        if ($campania === null) {
            return;
        }

        if ($campania->cerrada) {
            throw CampaniaCerrada::paraCampania($campania->codigo);
        }
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
