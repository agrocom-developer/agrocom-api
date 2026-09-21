<?php

namespace App\Dominios\Operaciones\Aplicacion\MaquinaEstados;

use App\Dominios\Operaciones\Contratos\Eventos\AplicacionCerrada;
use App\Dominios\Operaciones\Dominio\CausaCancelacionOrden;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\Excepciones\CierreOrdenNoPermitido;
use App\Dominios\Operaciones\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Operaciones\Dominio\Excepciones\TransicionOrdenNoPermitida;
use App\Dominios\Operaciones\Dominio\MaquinaEstados\TransicionesOrden;
use App\Dominios\Operaciones\Dominio\PoliticaCierreOrden;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenLote;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Única clase que crea/muta el `estado` de `ope_ordenes_aplicacion`
 * (invariante 7 de CLAUDE.md), mismo criterio que `MaquinaEstadosContrato`.
 *
 * Reforma 19/9/2026 (ADR 0022): la orden es UNA aplicación completa del
 * contrato. Ya no hay guarda de "una única orden vigente por lote": la
 * exclusividad de los lotes se garantiza ANTES, entre contratos (ADR 0021), y
 * la orden solo se ocupa de la aplicación. Lo que sí se garantiza, en el alta y
 * en la base, es que un contrato tenga una sola aplicación abierta por vez —
 * ver `Dominio/NumeracionAplicaciones`.
 *
 * Toda transición se aplica dentro de una transacción sobre la orden
 * BLOQUEADA y releída (`FOR UPDATE`, sin efecto en SQLite): así dos
 * operadores que actúan a la vez sobre la misma orden (uno la cierra, otro la
 * cancela) se turnan, y el segundo valida contra el estado ya definitivo y no
 * contra el que había cargado al abrir la pantalla.
 *
 * Las acciones que solo decide el panel (pausar, cancelar, cerrar) nunca las
 * dispara la app de campo: lo que ella registra (incidencias, pausas de
 * sesión) informa al operador, que decide.
 */
final class MaquinaEstadosOrden
{
    /**
     * @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase.
     */
    public function crear(array $atributos): OrdenAplicacion
    {
        return OrdenAplicacion::create([...$atributos, 'estado' => EstadoOrdenAplicacion::Emitida]);
    }

    /**
     * `emitida → vigente` (HU-25, tarea 38): publica la orden al catálogo de
     * la app de campo. Sin guarda de negocio propia desde la reforma del
     * 19/9/2026 (ver docblock de la clase).
     *
     * @throws TransicionOrdenNoPermitida si `$orden` no está `emitida`.
     */
    public function activar(OrdenAplicacion $orden): OrdenAplicacion
    {
        return $this->transicionar($orden, EstadoOrdenAplicacion::Vigente);
    }

    /**
     * `vigente → pausada`: la aplicación se detiene hasta resolver un problema
     * (clima, logística, insumos, pago…). Exige el motivo; el operador lo
     * escribe a la luz de lo que reportó el equipo y de lo hablado con el
     * dueño. Sale del catálogo de campo, que solo sirve órdenes `vigente`.
     *
     * @throws MotivoRequerido si el motivo viene vacío.
     * @throws TransicionOrdenNoPermitida si `$orden` no está `vigente`.
     */
    public function pausar(OrdenAplicacion $orden, string $motivo): OrdenAplicacion
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw MotivoRequerido::paraPausar();
        }

        return $this->transicionar($orden, EstadoOrdenAplicacion::Pausada, [
            'motivo_pausa' => $motivo,
            'pausada_at' => now(),
        ]);
    }

    /**
     * `pausada → vigente`: se resolvió el problema y la aplicación sigue.
     *
     * @throws TransicionOrdenNoPermitida si `$orden` no está `pausada`.
     */
    public function reanudar(OrdenAplicacion $orden): OrdenAplicacion
    {
        return $this->transicionar($orden, EstadoOrdenAplicacion::Vigente, [
            'reanudada_at' => now(),
        ]);
    }

    /**
     * `vigente → consumida`: la aplicación se cumplió. Acción manual del
     * encargado, con el informe del equipo a la vista — nada la dispara sola.
     * Guarda ({@see PoliticaCierreOrden}, ADR 0022 adenda 19/9/2026): solo se
     * cierra si la orden tiene al menos una orden de trabajo, todas sus hectáreas
     * asignadas a algún equipo y todos los equipos terminaron los suyos; la cuenta
     * se hace DENTRO de la transacción, con la orden bloqueada, así un trabajo que
     * se abre justo entonces no se cuela.
     * Anuncia {@see AplicacionCerrada} recién DESPUÉS de persistir el cierre; el
     * oyente de Comercial finaliza el contrato si era su última aplicación.
     *
     * @throws TransicionOrdenNoPermitida si `$orden` no está `vigente`.
     * @throws CierreOrdenNoPermitido si no tiene trabajos, le faltan hectáreas por asignar o alguno sigue abierto.
     */
    public function cerrar(OrdenAplicacion $orden): OrdenAplicacion
    {
        $cerrada = $this->transicionar($orden, EstadoOrdenAplicacion::Consumida, [
            'cerrada_at' => now(),
        ], function (OrdenAplicacion $actual): void {
            $trabajos = Trabajo::query()->where('orden_id', $actual->id);
            $total = (clone $trabajos)->count();
            $abiertos = (clone $trabajos)->where('estado', EstadoTrabajo::Abierto)->count();
            $solicitadas = (string) OrdenLote::query()->where('orden_id', $actual->id)->sum('hectareas_solicitadas');
            $asignadas = (string) (clone $trabajos)->sum('hectareas_declaradas');

            $impedimento = PoliticaCierreOrden::impedimento($total, $abiertos, $solicitadas, $asignadas);

            if ($impedimento !== null) {
                throw CierreOrdenNoPermitido::por(
                    $impedimento,
                    $abiertos,
                    (string) PoliticaCierreOrden::hectareasSinAsignar($solicitadas, $asignadas),
                );
            }
        });

        event(new AplicacionCerrada($cerrada->id, $cerrada->contrato_id, $cerrada->nro_aplicacion));

        return $cerrada;
    }

    /**
     * `vigente → cancelada` o `pausada → cancelada`: baja anticipada, siempre
     * por decisión del panel. Exige causa (`cliente` | `dueno` | `factor_externo`) y motivo.
     * La causa decide si la aplicación consume su número correlativo
     * ({@see CausaCancelacionOrden::consumeNumero()}). Cancelar no toca los
     * trabajos ya cargados: son horas de campo reales, con sus devengos.
     *
     * @throws MotivoRequerido si el motivo viene vacío.
     * @throws TransicionOrdenNoPermitida si `$orden` no está `vigente` ni `pausada`.
     */
    public function cancelar(OrdenAplicacion $orden, CausaCancelacionOrden $causa, string $motivo): OrdenAplicacion
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw MotivoRequerido::paraCancelar();
        }

        return $this->transicionar($orden, EstadoOrdenAplicacion::Cancelada, [
            'causa_cancelacion' => $causa,
            'motivo_cancelacion' => $motivo,
            'cancelada_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $extras  columnas que acompañan al cambio de estado (fecha, causa, motivo).
     * @param  Closure(OrdenAplicacion): void|null  $guarda  regla propia de la transición: corre dentro de la
     *                                                       transacción, con la orden bloqueada y ya validada
     *                                                       contra la tabla; lanza si no se cumple.
     *
     * @throws TransicionOrdenNoPermitida si la transición no está en la tabla.
     */
    private function transicionar(OrdenAplicacion $orden, EstadoOrdenAplicacion $hasta, array $extras = [], ?Closure $guarda = null): OrdenAplicacion
    {
        return DB::transaction(function () use ($orden, $hasta, $extras, $guarda): OrdenAplicacion {
            $actual = OrdenAplicacion::query()->lockForUpdate()->findOrFail($orden->id);
            $desde = $actual->estado;

            if (! TransicionesOrden::permitida($desde, $hasta)) {
                throw TransicionOrdenNoPermitida::entre($desde, $hasta);
            }

            if ($guarda !== null) {
                $guarda($actual);
            }

            $actual->estado = $hasta;
            $actual->fill($extras);
            $actual->save();

            return $actual;
        });
    }
}
