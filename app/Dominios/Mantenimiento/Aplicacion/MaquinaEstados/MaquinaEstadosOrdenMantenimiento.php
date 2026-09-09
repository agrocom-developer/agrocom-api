<?php

namespace App\Dominios\Mantenimiento\Aplicacion\MaquinaEstados;

use App\Dominios\Finanzas\Contratos\EscrituraGastoMantenimiento;
use App\Dominios\Inventario\Contratos\EscrituraConsumoStock;
use App\Dominios\Inventario\Contratos\Excepciones\StockInsuficiente;
use App\Dominios\Mantenimiento\Dominio\EstadoOrdenMantenimiento;
use App\Dominios\Mantenimiento\Dominio\Excepciones\RepuestosInsuficientes;
use App\Dominios\Mantenimiento\Dominio\Excepciones\TransicionOrdenMantenimientoNoPermitida;
use App\Dominios\Mantenimiento\Dominio\MaquinaEstados\TransicionesOrdenMantenimiento;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\OrdenMantenimiento;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Única clase que crea/muta `man_ordenes_mantenimiento.estado` (invariante 7
 * de CLAUDE.md; HU-37, tarea 53), mismo criterio que
 * `Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosOrden`.
 *
 * `abrir()` fija el único estado de alta (`Abierta`), sin guarda de negocio.
 *
 * `cerrar()` es la única transición real (`Abierta → Cerrada`), con guarda de
 * stock disponible y efecto de dominio: consume `inv_stock` línea por línea
 * a través de {@see EscrituraConsumoStock} y, si TODAS las líneas salen bien,
 * genera el gasto a través de {@see EscrituraGastoMantenimiento}. Todo dentro
 * de una ÚNICA transacción abierta acá (`DB::transaction()`) — ni
 * `Inventario` ni `Finanzas` abren la suya propia para este flujo (sus
 * `DB::transaction()` internos anidan como `SAVEPOINT` en Postgres). Si
 * cualquier línea lanza {@see StockInsuficiente}, se envuelve en
 * {@see RepuestosInsuficientes} y todo se revierte — nada de stock consumido
 * a medias, ningún gasto huérfano.
 */
final class MaquinaEstadosOrdenMantenimiento
{
    public function __construct(
        private readonly EscrituraConsumoStock $escrituraConsumoStock,
        private readonly EscrituraGastoMantenimiento $escrituraGastoMantenimiento,
    ) {}

    /**
     * @param  array<string, mixed>  $atributos  sin `estado`/`fecha_apertura`: los fija esta clase.
     */
    public function abrir(array $atributos): OrdenMantenimiento
    {
        return OrdenMantenimiento::query()->create([
            ...$atributos,
            'estado' => EstadoOrdenMantenimiento::Abierta,
            'fecha_apertura' => Carbon::now(),
        ]);
    }

    /**
     * @param  list<array{repuesto_id: int, base_id: int, cantidad: string}>  $lineasRepuestos
     *
     * @throws TransicionOrdenMantenimientoNoPermitida si `$orden` no está `Abierta`.
     * @throws RepuestosInsuficientes si el stock de algún repuesto no alcanza.
     */
    public function cerrar(OrdenMantenimiento $orden, array $lineasRepuestos): OrdenMantenimiento
    {
        $desde = $orden->estado;
        $hasta = EstadoOrdenMantenimiento::Cerrada;

        if (! TransicionesOrdenMantenimiento::permitida($desde, $hasta)) {
            throw TransicionOrdenMantenimientoNoPermitida::entre($desde, $hasta);
        }

        DB::transaction(function () use ($orden, $lineasRepuestos): void {
            $montoTotal = BigDecimal::zero();

            foreach ($lineasRepuestos as $linea) {
                try {
                    $costoLinea = $this->escrituraConsumoStock->consumir(
                        $linea['repuesto_id'],
                        $linea['base_id'],
                        $linea['cantidad'],
                        $orden->id,
                    );
                } catch (StockInsuficiente $excepcion) {
                    throw RepuestosInsuficientes::paraRepuesto($linea['repuesto_id'], $excepcion->getMessage());
                }

                $montoTotal = $montoTotal->plus(BigDecimal::of($costoLinea));
            }

            $fechaCierre = Carbon::now();

            $gastoId = $this->escrituraGastoMantenimiento->registrarPorCierreDeOrden(
                $fechaCierre->toDateString(),
                (string) $montoTotal,
            );

            $orden->estado = EstadoOrdenMantenimiento::Cerrada;
            $orden->fecha_cierre = $fechaCierre;
            $orden->gasto_id = $gastoId;
            $orden->save();
        });

        return $orden->refresh();
    }
}
