<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Campania\Contratos\LecturaCampania;
use App\Dominios\Comercial\Aplicacion\Contrato\VerificadorLotesDelContrato;
use App\Dominios\Comercial\Aplicacion\MaquinaEstados\MaquinaEstadosContrato;
use App\Dominios\Comercial\Dominio\Excepciones\CampaniaCerrada;
use App\Dominios\Comercial\Dominio\Excepciones\LoteAjenoAlCliente;
use App\Dominios\Comercial\Dominio\Excepciones\LotesDePropiedadAgotados;
use App\Dominios\Comercial\Dominio\Excepciones\VentanasContratoSolapadas;
use App\Dominios\Comercial\Dominio\ValidadorSolapamientoVentanas;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * Alta de un contrato con sus ventanas horarias y sus lotes en una sola
 * operación (HU-23, tarea 34; lotes agregados en la tarea "contratos-lotes",
 * 16/9/2026): mismo criterio que `CrearCliente` — el formulario es uno solo,
 * así que el contrato, sus ventanas y sus lotes nacen en la misma
 * transacción.
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
 *
 * Lotes (pedido del dueño, tarea "contratos-lotes"): un contrato elige una
 * propiedad del cliente y uno o más lotes concretos de ella (pueden ser de
 * varias propiedades). Dos guardas antes de persistir, vía
 * {@see VerificadorLotesDelContrato} — ninguna expresable en un `CHECK` de
 * Postgres porque cruzan tablas (ver el docblock de la migración
 * `create_com_contrato_lotes_table`):
 * - cada lote elegido tiene que ser de una propiedad del `cliente_id` del
 *   contrato ({@see LoteAjenoAlCliente} si no);
 * - ninguna propiedad involucrada puede quedar con el 100% de sus lotes
 *   cubiertos por OTROS contratos `vigente` de la misma campaña
 *   ({@see LotesDePropiedadAgotados} si alguna lo está).
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
     * @param  list<int>  $loteIds  lotes concretos que cubre el contrato (de una o varias propiedades del cliente)
     *
     * @throws VentanasContratoSolapadas si dos ventanas del alta se solapan entre sí.
     * @throws CampaniaCerrada si la campaña elegida está `cerrada`.
     * @throws LoteAjenoAlCliente si algún lote no pertenece a una propiedad del cliente del contrato.
     * @throws LotesDePropiedadAgotados si alguna propiedad de los lotes elegidos ya está 100% cubierta por otros contratos vigentes de la misma campaña.
     */
    public function ejecutar(array $datosContrato, array $ventanas, array $loteIds): Contrato
    {
        $this->verificarCampania((int) $datosContrato['campania_id']);

        $solapamiento = ValidadorSolapamientoVentanas::primerSolapamiento($ventanas);

        if ($solapamiento !== null) {
            [$a, $b] = $solapamiento;

            throw VentanasContratoSolapadas::entre($a['hora_inicio'], $a['hora_fin'], $b['hora_inicio'], $b['hora_fin']);
        }

        $this->verificarLotes($loteIds, (int) $datosContrato['cliente_id'], (int) $datosContrato['campania_id'], null);

        return DB::transaction(function () use ($datosContrato, $ventanas, $loteIds): Contrato {
            $datosContrato['monto_total'] = $this->calcularMontoTotal(
                (string) $datosContrato['hectareas_contratadas'],
                (int) $datosContrato['aplicaciones_previstas'],
                (string) $datosContrato['precio_ha'],
            );

            $contrato = $this->maquinaEstados->crear($datosContrato);

            foreach ($ventanas as $ventana) {
                $contrato->ventanas()->create($ventana);
            }

            foreach ($loteIds as $loteId) {
                $contrato->lotes()->create(['lote_id' => $loteId]);
            }

            return $contrato->refresh();
        });
    }

    /**
     * @param  list<int>  $loteIds
     *
     * @throws LoteAjenoAlCliente si algún lote no pertenece a una propiedad del cliente.
     * @throws LotesDePropiedadAgotados si alguna propiedad involucrada quedó 100% cubierta por otros contratos vigentes.
     */
    private function verificarLotes(array $loteIds, int $clienteId, int $campaniaId, ?int $contratoIdExcluido): void
    {
        $loteAjeno = VerificadorLotesDelContrato::loteAjenoAlCliente($loteIds, $clienteId);

        if ($loteAjeno !== null) {
            throw LoteAjenoAlCliente::paraLote($loteAjeno);
        }

        $propiedadAgotada = VerificadorLotesDelContrato::propiedadAgotada($loteIds, $campaniaId, $contratoIdExcluido);

        if ($propiedadAgotada !== null) {
            throw LotesDePropiedadAgotados::paraPropiedad($propiedadAgotada['nombre']);
        }
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
