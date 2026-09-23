<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Campania\Contratos\LecturaCampania;
use App\Dominios\Comercial\Aplicacion\Contrato\VerificadorCultivoDelContrato;
use App\Dominios\Comercial\Aplicacion\Contrato\VerificadorLotesDelContrato;
use App\Dominios\Comercial\Aplicacion\MaquinaEstados\MaquinaEstadosContrato;
use App\Dominios\Comercial\Dominio\Excepciones\CampaniaNoAbierta;
use App\Dominios\Comercial\Dominio\Excepciones\LoteAjenoAlCliente;
use App\Dominios\Comercial\Dominio\Excepciones\LotesDeDistintoCultivo;
use App\Dominios\Comercial\Dominio\Excepciones\LotesYaContratados;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\ContratoLote;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Edición de un contrato con sus lotes en una sola operación (HU-23, tarea
 * 34; lotes agregados en la tarea "contratos-lotes", 16/9/2026): mismo
 * criterio que `ActualizarCliente` — una sola transacción para contrato y
 * lotes.
 *
 * El contrato solo dice QUÉ lotes entran: sin ventanas horarias (retiradas
 * el 16/9/2026) y sin horario por lote (retirado del contrato el 21/9/2026,
 * se carga en la orden de trabajo — ver `CrearContrato`). Las columnas
 * `hora_inicio`/`hora_fin` de {@see ContratoLote} quedan con lo que tuvieran;
 * esta clase no las escribe.
 *
 * El set de `$lotes` recibido es el COMPLETO y definitivo, no un delta: los
 * que faltan respecto a los actuales se dan de baja (soft delete), los que
 * faltan en el set actual se crean — mismo criterio que
 * `ActualizarCliente::sincronizarContactos`. A diferencia de los contactos,
 * no hay `id` de fila que enviar de vuelta: el propio `lote_id` identifica
 * la fila, porque `UNIQUE (contrato_id, lote_id)` parcial ya impide que se
 * repita dentro del mismo contrato — ver {@see self::sincronizarLotes()}
 * para el criterio de qué fila se considera "cambiada" (y por lo tanto se
 * guarda) frente a "sigue igual" (y se deja intacta).
 *
 * `estado` nunca viaja en `$datosContrato` (invariante 7): esta clase no lo
 * toca — el cambio de estado es responsabilidad exclusiva de
 * `Aplicacion/CambiarEstadoContrato`.
 *
 * Misma guarda de campaña que `CrearContrato` (ADR 0015 punto 1, corregida
 * el 15/9/2026): la campaña elegida no puede estar `cerrada` — ver ese
 * docblock para el criterio de lectura vía `LecturaCampania`.
 *
 * Mismas dos guardas de lotes que `CrearContrato` (ver su docblock), vía
 * {@see VerificadorLotesDelContrato} — con dos diferencias. La guarda de lotes
 * ocupados excluye al propio `$contrato` ("en edición, el propio contrato no
 * se cuenta contra sí mismo") y solo mira los lotes NUEVOS (o todos, si
 * cambió la campaña): un contrato en `conflicto` puede guardarse mientras
 * arrastra los lotes que lo tienen en conflicto — lo que no puede es sumar
 * otro ocupado. Y después de guardar reconcilia los conflictos de la campaña
 * ({@see MaquinaEstadosContrato::reconciliarConflictos()}): al quitar un
 * lote, el contrato puede salir de `conflicto`; al agregar uno a un contrato
 * que retiene lotes (`vigente`/`pausado`), los `borrador` que lo comparten
 * pasan a `conflicto` (ADR 0021).
 */
final class ActualizarContrato
{
    public function __construct(
        private readonly LecturaCampania $lecturaCampania,
        private readonly MaquinaEstadosContrato $maquinaEstados,
    ) {}

    /**
     * @param  array<string, mixed>  $datosContrato  sin `estado` ni `monto_total`: este último lo recalcula esta clase.
     * @param  list<array{lote_id: int}>  $lotes  set completo y definitivo de lotes que cubre el contrato, cada uno con su rango horario opcional
     *
     * @throws CampaniaNoAbierta si la campaña elegida está `cerrada`, o no está `abierta` y el contrato se la asigna ahora.
     * @throws LoteAjenoAlCliente si algún lote no pertenece a una propiedad del cliente del contrato.
     * @throws LotesDeDistintoCultivo si los lotes sembrados mezclan cultivos o etapas en la campaña.
     * @throws LotesYaContratados si algún lote NUEVO ya lo retiene otro contrato vigente o pausado de la misma campaña.
     */
    public function ejecutar(Contrato $contrato, array $datosContrato, array $lotes): Contrato
    {
        $this->verificarCampania((int) $datosContrato['campania_id'], $contrato);

        $loteIds = array_column($lotes, 'lote_id');

        $this->verificarLotesDelCliente($loteIds, (int) $datosContrato['cliente_id']);
        // Un contrato agrupa lotes del mismo cultivo y la misma etapa (22/9/2026).
        VerificadorCultivoDelContrato::verificar($loteIds, (int) $datosContrato['campania_id']);

        return DB::transaction(function () use ($contrato, $datosContrato, $lotes, $loteIds): Contrato {
            $campaniaId = (int) $datosContrato['campania_id'];
            $campaniaAnterior = $contrato->campania_id;

            $this->verificarLotesNuevosLibres($contrato, $loteIds, $campaniaId, $campaniaAnterior);

            $datosContrato['monto_total'] = $this->calcularMontoTotal(
                (string) $datosContrato['hectareas_contratadas'],
                (int) $datosContrato['aplicaciones_previstas'],
                (string) $datosContrato['precio_ha'],
            );

            $contrato->fill($datosContrato);
            $contrato->save();

            $this->sincronizarLotes($contrato, $lotes);

            $this->maquinaEstados->reconciliarConflictos($campaniaId);

            if ($campaniaAnterior !== null && $campaniaAnterior !== $campaniaId) {
                $this->maquinaEstados->reconciliarConflictos($campaniaAnterior);
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
     * Solo los lotes que el contrato NO tenía todavía (los que ya tenía no
     * se re-juzgan: si hoy están en `conflicto` es justamente por ellos y el
     * usuario tiene que poder guardar para quitarlos). Si cambió la campaña,
     * todos cuentan como nuevos: se comparan contra otro ciclo productivo.
     * Bajo candado de campaña, como en `CrearContrato`.
     *
     * @param  list<int>  $loteIds
     *
     * @throws LotesYaContratados si algún lote nuevo ya lo retiene otro contrato de la campaña.
     */
    private function verificarLotesNuevosLibres(Contrato $contrato, array $loteIds, int $campaniaId, ?int $campaniaAnterior): void
    {
        VerificadorLotesDelContrato::bloquearCampania($campaniaId);

        $candidatos = $campaniaAnterior === $campaniaId
            ? array_values(array_diff($loteIds, $this->loteIdsActualesDe($contrato)))
            : $loteIds;

        $ocupados = VerificadorLotesDelContrato::lotesOcupados($candidatos, $campaniaId, $contrato->id);

        if ($ocupados !== []) {
            throw LotesYaContratados::paraLotes($ocupados);
        }
    }

    /** @return list<int> */
    private function loteIdsActualesDe(Contrato $contrato): array
    {
        return array_values(array_map(
            static fn (mixed $id): int => (int) $id,
            $contrato->lotes()->pluck('lote_id')->all(),
        ));
    }

    /**
     * Reconcilia el set completo y definitivo de `$lotes` contra
     * `$contrato->lotes()` actual, por `lote_id`: da de baja (soft delete)
     * las filas {@see ContratoLote} cuyo `lote_id` ya no está en el set
     * nuevo, crea las que faltan, y a las que siguen en ambos lados NO las
     * toca: un lote-en-contrato no tiene más dato propio que el lote, así que
     * no hay nada que pueda haber cambiado — y la bitácora no se ensucia con
     * lo que no cambió.
     *
     * @param  list<array{lote_id: int}>  $lotes
     */
    private function sincronizarLotes(Contrato $contrato, array $lotes): void
    {
        $loteIds = array_column($lotes, 'lote_id');

        // `whereNotIn` con un array vacío no excluye nada (Laravel: "verdadero
        // para toda fila"): si `$lotes` llegara vacío esto daría de baja a
        // todos los lotes actuales — no debería pasar (`ActualizarContratoRequest`
        // exige `lotes` con `min:1`), pero el comportamiento es correcto igual:
        // el set enviado reemplaza por completo al actual.
        $contrato->lotes()
            ->whereNotIn('lote_id', $loteIds)
            ->get()
            ->each(function (ContratoLote $contratoLote): void {
                $usuarioId = Auth::id();

                if ($usuarioId !== null) {
                    $contratoLote->updated_by = (int) $usuarioId;
                    $contratoLote->save();
                }

                $contratoLote->delete();
            });

        $existentes = $contrato->lotes()->pluck('lote_id')->map(static fn (mixed $id): int => (int) $id)->all();

        foreach ($lotes as $datos) {
            if (! in_array($datos['lote_id'], $existentes, true)) {
                $contrato->lotes()->create(['lote_id' => $datos['lote_id']]);
            }
        }
    }

    /**
     * Una campaña `cerrada` no admite ningún cambio. Una que no está `abierta`
     * (la `planificada`) solo se rechaza si el contrato se la asigna AHORA: el
     * que ya la tenía, de antes de que se exigiera `abierta`, se sigue
     * pudiendo editar sin cambiarla.
     *
     * @throws CampaniaNoAbierta
     */
    private function verificarCampania(int $campaniaId, Contrato $contrato): void
    {
        $campania = $this->lecturaCampania->obtener($campaniaId);

        if ($campania === null) {
            return;
        }

        $seAsignaAhora = (int) $contrato->campania_id !== $campaniaId;

        if ($campania->cerrada || ($seAsignaAhora && ! $campania->admiteImputaciones())) {
            throw CampaniaNoAbierta::paraCampania($campania->codigo, $campania->cerrada);
        }
    }

    /**
     * Mismo cálculo que `CrearContrato::calcularMontoTotal()` — `monto_total`
     * se recalcula siempre desde los tres factores de origen (invariante 6),
     * nunca se acepta el valor que venga en el formulario.
     */
    private function calcularMontoTotal(string $hectareas, int $aplicaciones, string $precioHa): string
    {
        return (string) BigDecimal::of($hectareas)
            ->multipliedBy($aplicaciones)
            ->multipliedBy($precioHa)
            ->toScale(2, RoundingMode::HalfUp);
    }
}
