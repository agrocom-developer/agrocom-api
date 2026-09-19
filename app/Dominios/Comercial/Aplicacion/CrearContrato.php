<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Campania\Contratos\LecturaCampania;
use App\Dominios\Comercial\Aplicacion\Contrato\VerificadorLotesDelContrato;
use App\Dominios\Comercial\Aplicacion\MaquinaEstados\MaquinaEstadosContrato;
use App\Dominios\Comercial\Dominio\Excepciones\CampaniaNoAbierta;
use App\Dominios\Comercial\Dominio\Excepciones\LoteAjenoAlCliente;
use App\Dominios\Comercial\Dominio\Excepciones\LotesYaContratados;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\ContratoLote;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * Alta de un contrato con sus lotes en una sola operación (HU-23, tarea 34;
 * lotes agregados en la tarea "contratos-lotes", 16/9/2026): mismo criterio
 * que `CrearCliente` — el formulario es uno solo, así que el contrato y sus
 * lotes nacen en la misma transacción.
 *
 * Sin ventanas de contrato (retiradas el 16/9/2026, reemplazo completo: ver
 * el docblock de {@see ContratoLote}):
 * el rango horario para fumigar ya no es un dato del contrato completo, es
 * un dato de CADA LOTE (`hora_inicio`/`hora_fin`, ambos NULL = día
 * completo). Como cada lote tiene A LO SUMO un rango (una columna, no una
 * lista por fila), no hay "solapamiento entre horarios del mismo lote" que
 * validar — el `ValidadorSolapamientoVentanas` que existía para las
 * ventanas del contrato (N filas por contrato) no tiene equivalente acá y
 * se eliminó sin reemplazo.
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
 * varias propiedades), cada uno con su propio rango horario opcional. Dos
 * guardas antes de persistir, vía {@see VerificadorLotesDelContrato} —
 * ninguna expresable en un `CHECK` de Postgres porque cruzan tablas (ver el
 * docblock de la migración `create_com_contrato_lotes_table`):
 * - cada lote elegido tiene que ser de una propiedad del `cliente_id` del
 *   contrato ({@see LoteAjenoAlCliente} si no);
 * - ningún lote elegido puede estar retenido por OTRO contrato `vigente` o
 *   `pausado` de la misma campaña ({@see LotesYaContratados} si alguno lo
 *   está; ADR 0021, reemplaza a la guarda anterior por propiedad agotada).
 *   Esta se verifica ADENTRO de la transacción y bajo candado de campaña,
 *   para no cruzarse con una aprobación simultánea de otro contrato.
 *
 * La consistencia de `hora_inicio`/`hora_fin` de cada lote ("las dos juntas
 * o ninguna", "`hora_fin` > `hora_inicio`") NO se re-valida acá: es una
 * regla de una sola fila, ya replicada en `CrearContratoRequest` (mismo
 * criterio que ya rige en esta misma clase para
 * `hectareas_contratadas`/`aplicaciones_previstas`/`precio_ha`, ninguno de
 * los cuales se re-verifica en `Aplicacion` tampoco). Los dos guardas que SÍ
 * viven acá (`LoteAjenoAlCliente`, `LotesYaContratados`) son,
 * justamente, los que cruzan tablas y que ningún `Request` puede expresar; un
 * horario inconsistente que sorteara el `Request` (un caller que no pase por
 * él) caería en el `CHECK` de Postgres, ni mejor ni peor que lo que ya le
 * pasaría hoy a una hectárea negativa en la misma situación.
 */
final class CrearContrato
{
    public function __construct(
        private readonly MaquinaEstadosContrato $maquinaEstados,
        private readonly LecturaCampania $lecturaCampania,
    ) {}

    /**
     * @param  array<string, mixed>  $datosContrato  sin `estado` ni `monto_total`: los fija esta clase.
     * @param  list<array{lote_id: int, hora_inicio: ?string, hora_fin: ?string}>  $lotes  lotes concretos que cubre el contrato (de una o varias propiedades del cliente), cada uno con su rango horario opcional
     *
     * @throws CampaniaNoAbierta si la campaña elegida no está `abierta`.
     * @throws LoteAjenoAlCliente si algún lote no pertenece a una propiedad del cliente del contrato.
     * @throws LotesYaContratados si algún lote elegido ya lo retiene otro contrato vigente o pausado de la misma campaña.
     */
    public function ejecutar(array $datosContrato, array $lotes): Contrato
    {
        $this->verificarCampania((int) $datosContrato['campania_id']);

        $loteIds = array_column($lotes, 'lote_id');

        $this->verificarLotesDelCliente($loteIds, (int) $datosContrato['cliente_id']);

        return DB::transaction(function () use ($datosContrato, $lotes, $loteIds): Contrato {
            $this->verificarLotesLibres($loteIds, (int) $datosContrato['campania_id']);

            $datosContrato['monto_total'] = $this->calcularMontoTotal(
                (string) $datosContrato['hectareas_contratadas'],
                (int) $datosContrato['aplicaciones_previstas'],
                (string) $datosContrato['precio_ha'],
            );

            $contrato = $this->maquinaEstados->crear($datosContrato);

            foreach ($lotes as $lote) {
                $contrato->lotes()->create([
                    'lote_id' => $lote['lote_id'],
                    'hora_inicio' => $lote['hora_inicio'],
                    'hora_fin' => $lote['hora_fin'],
                ]);
            }

            return $contrato->refresh();
        });
    }

    /**
     * @param  list<int>  $loteIds
     *
     * @throws LoteAjenoAlCliente si algún lote no pertenece a una propiedad del cliente.
     */
    private function verificarLotesDelCliente(array $loteIds, int $clienteId): void
    {
        $loteAjeno = VerificadorLotesDelContrato::loteAjenoAlCliente($loteIds, $clienteId);

        if ($loteAjeno !== null) {
            throw LoteAjenoAlCliente::paraLote($loteAjeno);
        }
    }

    /**
     * Bajo candado de campaña: si otra transacción está aprobando un contrato
     * con alguno de estos lotes, se espera a que termine y se ve su resultado.
     *
     * @param  list<int>  $loteIds
     *
     * @throws LotesYaContratados si algún lote ya lo retiene otro contrato de la campaña.
     */
    private function verificarLotesLibres(array $loteIds, int $campaniaId): void
    {
        VerificadorLotesDelContrato::bloquearCampania($campaniaId);

        $ocupados = VerificadorLotesDelContrato::lotesOcupados($loteIds, $campaniaId, null);

        if ($ocupados !== []) {
            throw LotesYaContratados::paraLotes($ocupados);
        }
    }

    /** @throws CampaniaNoAbierta si la campaña elegida no está `abierta`. */
    private function verificarCampania(int $campaniaId): void
    {
        $campania = $this->lecturaCampania->obtener($campaniaId);

        if ($campania === null) {
            return;
        }

        if (! $campania->admiteImputaciones()) {
            throw CampaniaNoAbierta::paraCampania($campania->codigo, $campania->cerrada);
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
