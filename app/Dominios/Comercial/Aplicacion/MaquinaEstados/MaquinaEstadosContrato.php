<?php

namespace App\Dominios\Comercial\Aplicacion\MaquinaEstados;

use App\Dominios\Comercial\Aplicacion\Contrato\VerificadorLotesDelContrato;
use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Dominio\Excepciones\ActivacionContratoNoDisponible;
use App\Dominios\Comercial\Dominio\Excepciones\TransicionContratoNoPermitida;
use App\Dominios\Comercial\Dominio\MaquinaEstados\TransicionesContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\ContratoLote;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Única clase que crea/muta el `estado` de `contrato` (invariante 7 de
 * CLAUDE.md), mismo criterio que `MaquinaEstadosActa`/`MaquinaEstadosTrabajo`.
 *
 * Las guardas de AUTORIZACIÓN ("¿quién puede cambiar este estado?") no viven
 * acá — igual que `MaquinaEstadosSesion::validar()` no decide "validador ≠
 * piloto" (eso es `Aplicacion/ValidarSesion`). Las guardas de `activar()` sí
 * viven acá porque son de DATOS del propio contrato (¿su fecha de inicio ya
 * pasó?, ¿alguno de sus lotes lo retiene otro contrato?), no de quién
 * ejecuta la acción: no hay ningún otro caso de uso que necesite evaluarlas
 * antes de invocar la transición.
 *
 * Exclusividad de lotes (ADR 0021, 18/9/2026): los estados `vigente` y
 * `pausado` RETIENEN los lotes de su contrato — ver
 * {@see EstadoContrato::retieneLotes()} — para toda la campaña, hasta que el
 * contrato se cancele o finalice. Los contratos `borrador`/`conflicto` de la
 * misma campaña que comparten un lote con uno que retiene lotes están en
 * `conflicto`; {@see self::reconciliarConflictos()} es la ÚNICA que mueve
 * contratos entre `borrador` y `conflicto`, y se invoca cada vez que cambia
 * qué lotes están retenidos (aprobar, cancelar, finalizar, o editar los
 * lotes de un contrato — esto último desde `Aplicacion/ActualizarContrato`).
 */
final class MaquinaEstadosContrato
{
    /**
     * @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase.
     */
    public function crear(array $atributos): Contrato
    {
        return Contrato::create([...$atributos, 'estado' => EstadoContrato::Borrador]);
    }

    /**
     * `borrador → vigente` (HU-23, tarea 34). Dos guardas de negocio —
     * decisión razonable a falta de una regla más específica en la
     * especificación funcional, documentadas acá porque es donde se aplican:
     *
     * - **`fecha_inicio` no en el pasado**: vigenciar retroactivamente un
     *   contrato cuya vigencia ya debería haber empezado no tiene sentido de
     *   negocio — lo razonable es corregir la fecha antes de activar, no
     *   activar tarde.
     * - **Ningún lote retenido por otro contrato de la misma campaña** (ADR
     *   0021): aprobar es lo que hace que el contrato RETENGA sus lotes, así
     *   que ahí se verifica, bajo candado de campaña para que dos
     *   aprobaciones simultáneas se turnen. Un contrato en `conflicto` ni
     *   siquiera llega hasta acá: la tabla no permite `conflicto → vigente`.
     *   Al quedar `vigente`, los demás `borrador` que comparten algún lote
     *   pasan solos a `conflicto` ({@see self::reconciliarConflictos()}).
     *
     * Hasta la tarea 70 (HU-47) había una tercera guarda ("al menos una
     * ventana horaria cargada"): se retiró por pedido explícito del dueño
     * del 7/9/2026 — cero ventanas es un contrato válido de "día completo",
     * no un contrato incompleto.
     *
     * @throws TransicionContratoNoPermitida si `$contrato` no está `borrador`.
     * @throws ActivacionContratoNoDisponible si `fecha_inicio` ya pasó o algún lote lo retiene otro contrato.
     */
    public function activar(Contrato $contrato): Contrato
    {
        $desde = $contrato->estado;
        $hasta = EstadoContrato::Vigente;

        if (! TransicionesContrato::permitida($desde, $hasta)) {
            throw TransicionContratoNoPermitida::entre($desde, $hasta);
        }

        if ($contrato->fecha_inicio->startOfDay()->lt(CarbonImmutable::today())) {
            throw ActivacionContratoNoDisponible::porFechaInicioEnElPasado($contrato->id, $contrato->fecha_inicio);
        }

        return DB::transaction(function () use ($contrato, $hasta): Contrato {
            $campaniaId = $contrato->campania_id;

            if ($campaniaId !== null) {
                VerificadorLotesDelContrato::bloquearCampania($campaniaId);

                $ocupados = VerificadorLotesDelContrato::lotesOcupados($this->loteIdsDe($contrato), $campaniaId, $contrato->id);

                if ($ocupados !== []) {
                    throw ActivacionContratoNoDisponible::porLotesOcupados($contrato->id, $ocupados);
                }
            }

            $contrato->estado = $hasta;
            $contrato->save();

            if ($campaniaId !== null) {
                $this->reconciliarConflictos($campaniaId);
            }

            return $contrato;
        });
    }

    /**
     * `vigente → finalizado` (HU-23, tarea 34). Sin guarda adicional: el
     * cierre real por consumo de hectáreas es de otro dominio
     * (`Operaciones`), que todavía no dispara esta transición. Libera los
     * lotes que el contrato retenía: los contratos en `conflicto` que ya no
     * choquen con ninguno vuelven a `borrador`.
     *
     * @throws TransicionContratoNoPermitida si `$contrato` no está `vigente`.
     */
    public function finalizar(Contrato $contrato): Contrato
    {
        return $this->transicionarLiberandoLotes($contrato, EstadoContrato::Finalizado);
    }

    /**
     * `borrador → cancelado`, `conflicto → cancelado` o `vigente → cancelado`
     * (HU-23, tarea 34): baja anticipada. Sin guarda adicional — cancelar es
     * siempre una salida disponible desde cualquier estado no terminal. Si el
     * contrato retenía lotes (`vigente`), los libera: los contratos en
     * `conflicto` que ya no choquen con ninguno vuelven a `borrador`.
     *
     * @throws TransicionContratoNoPermitida si `$contrato` ya está `finalizado` o `cancelado`.
     */
    public function cancelar(Contrato $contrato): Contrato
    {
        return $this->transicionarLiberandoLotes($contrato, EstadoContrato::Cancelado);
    }

    /**
     * `vigente → pausado` (HU-71, tarea 87): interrupción del contrato
     * vigente, no una cancelación. Sin guarda adicional — pausar es una
     * decisión del encargado, no depende de fechas como `activar()`. NO libera
     * lotes: un contrato pausado sigue reteniéndolos (ADR 0021).
     *
     * @throws TransicionContratoNoPermitida si `$contrato` no está `vigente`.
     */
    public function pausar(Contrato $contrato): Contrato
    {
        return $this->transicionar($contrato, EstadoContrato::Pausado);
    }

    /**
     * `pausado → vigente` (HU-71, tarea 87): vuelta de una interrupción. Sin
     * guarda adicional, mismo criterio que `pausar()`: sus lotes nunca
     * dejaron de estar retenidos, así que no hay nada que re-verificar.
     *
     * @throws TransicionContratoNoPermitida si `$contrato` no está `pausado`.
     */
    public function reanudar(Contrato $contrato): Contrato
    {
        return $this->transicionar($contrato, EstadoContrato::Vigente);
    }

    /**
     * Punto de entrada único para `Aplicacion/CambiarEstadoContrato` (HTTP):
     * resuelve a qué método de transición corresponde `$hacia` sin que el
     * llamador tenga que conocer el nombre de cada uno. `Borrador` y
     * `Conflicto` NUNCA son un destino válido desde acá — los fija solo
     * {@see self::reconciliarConflictos()} (y `crear()` para el alta) — así
     * que se rechazan aunque la tabla de transiciones sí admita
     * `borrador ↔ conflicto` para el sistema.
     *
     * `Vigente` como destino tiene dos orígenes posibles con semántica
     * distinta (HU-71, tarea 87): desde `borrador` es `activar()`, con sus
     * guardas; desde `pausado` es `reanudar()`, sin ellas — un contrato que
     * ya estuvo vigente y se pausó casi siempre tiene `fecha_inicio` en el
     * pasado, así que aplicarle la guarda de `activar()` lo dejaría sin poder
     * reanudarse nunca. Por eso se distingue por el estado ACTUAL de
     * `$contrato`, antes del `match` por destino.
     *
     * @throws TransicionContratoNoPermitida si la transición no está en la tabla o el destino es del sistema.
     * @throws ActivacionContratoNoDisponible si el destino es `vigente` desde `borrador` y falta alguna guarda.
     */
    public function cambiarA(Contrato $contrato, EstadoContrato $hacia): Contrato
    {
        if ($hacia === EstadoContrato::Vigente && $contrato->estado === EstadoContrato::Pausado) {
            return $this->reanudar($contrato);
        }

        return match ($hacia) {
            EstadoContrato::Vigente => $this->activar($contrato),
            EstadoContrato::Finalizado => $this->finalizar($contrato),
            EstadoContrato::Cancelado => $this->cancelar($contrato),
            EstadoContrato::Pausado => $this->pausar($contrato),
            EstadoContrato::Borrador, EstadoContrato::Conflicto => throw TransicionContratoNoPermitida::entre($contrato->estado, $hacia),
        };
    }

    /**
     * Reconcilia, para UNA campaña, qué contratos están en `conflicto`
     * (ADR 0021). Idempotente: se puede llamar cuantas veces haga falta, y
     * siempre deja el mismo resultado para el mismo estado de la base.
     *
     * - Un contrato `borrador` con al menos un lote retenido por otro
     *   contrato (`vigente`/`pausado`) de la campaña pasa a `conflicto`.
     * - Un contrato `conflicto` que ya no comparte ningún lote con uno que
     *   retenga lotes vuelve a `borrador`.
     *
     * Un contrato `borrador`/`conflicto` nunca retiene lotes, así que todo lote
     * retenido que aparezca en sus filas es, por definición, de OTRO contrato.
     * Es la única que mueve contratos entre esos dos estados; corre bajo
     * candado de campaña y adentro de una transacción (la propia o la del
     * llamador).
     */
    public function reconciliarConflictos(int $campaniaId): void
    {
        DB::transaction(function () use ($campaniaId): void {
            VerificadorLotesDelContrato::bloquearCampania($campaniaId);

            $retenidos = array_flip(array_map(
                static fn (mixed $id): int => (int) $id,
                ContratoLote::query()
                    ->whereHas('contrato', function (Builder $query) use ($campaniaId): void {
                        $query->where('campania_id', $campaniaId)
                            ->whereIn('estado', EstadoContrato::valoresQueRetienenLotes());
                    })
                    ->pluck('lote_id')
                    ->all(),
            ));

            $candidatos = Contrato::query()
                ->where('campania_id', $campaniaId)
                ->whereIn('estado', [EstadoContrato::Borrador->value, EstadoContrato::Conflicto->value])
                ->with('lotes:id,contrato_id,lote_id')
                ->get();

            foreach ($candidatos as $candidato) {
                $choca = $candidato->lotes->contains(
                    fn (ContratoLote $fila): bool => isset($retenidos[$fila->lote_id]),
                );

                if ($choca && $candidato->estado === EstadoContrato::Borrador) {
                    $this->transicionar($candidato, EstadoContrato::Conflicto);
                } elseif (! $choca && $candidato->estado === EstadoContrato::Conflicto) {
                    $this->transicionar($candidato, EstadoContrato::Borrador);
                }
            }
        });
    }

    /**
     * Transición terminal (`finalizado`/`cancelado`): si el contrato
     * retenía lotes, al soltarlos se reconcilia la campaña para que los
     * contratos en `conflicto` que ya no choquen vuelvan a `borrador`. Todo
     * en una transacción: o cambia el estado Y se reconcilian los demás, o
     * nada.
     *
     * @throws TransicionContratoNoPermitida si la transición no está permitida.
     */
    private function transicionarLiberandoLotes(Contrato $contrato, EstadoContrato $hasta): Contrato
    {
        $retenia = $contrato->estado->retieneLotes();

        return DB::transaction(function () use ($contrato, $hasta, $retenia): Contrato {
            $this->transicionar($contrato, $hasta);

            if ($retenia && $contrato->campania_id !== null) {
                $this->reconciliarConflictos($contrato->campania_id);
            }

            return $contrato;
        });
    }

    /** @throws TransicionContratoNoPermitida si la transición no está permitida. */
    private function transicionar(Contrato $contrato, EstadoContrato $hasta): Contrato
    {
        $desde = $contrato->estado;

        if (! TransicionesContrato::permitida($desde, $hasta)) {
            throw TransicionContratoNoPermitida::entre($desde, $hasta);
        }

        $contrato->estado = $hasta;
        $contrato->save();

        return $contrato;
    }

    /** @return list<int> */
    private function loteIdsDe(Contrato $contrato): array
    {
        return array_values(array_map(
            static fn (mixed $id): int => (int) $id,
            $contrato->lotes()->pluck('lote_id')->all(),
        ));
    }
}
