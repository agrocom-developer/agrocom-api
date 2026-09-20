<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http;

use App\Dominios\Finanzas\Contratos\LecturaGastoMantenimiento;
use App\Dominios\Inventario\Contratos\LecturaConsumosPorOrden;
use App\Dominios\Mantenimiento\Aplicacion\ContarOrdenesDeEquipo;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\OrdenMantenimiento;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Vehiculo;
use App\Dominios\Operaciones\Contratos\LecturaDrones;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\Request;

/**
 * Tarjetas del resumen relacionado (aside de la ficha de la orden de
 * mantenimiento, §6.3.1 de la guía de pantalla): el equipo intervenido, los
 * repuestos que consumió y el gasto que generó. Hermano de
 * {@see ResumenRelacionadoDeEquipo}, que hace lo propio para batería,
 * generador y vehículo.
 *
 * Las tres relaciones salen de las columnas reales de la tabla —
 * `equipo_tipo`+`equipo_id` y `gasto_id`— y de las salidas de stock que el
 * cierre escribió con el id de la orden. **No hay tarjeta del plan que la
 * originó**: `man_ordenes_mantenimiento` no tiene `plan_id` y la apertura
 * automática por umbral no existe en este alcance (ver el docblock de
 * `PlanesMantenimientoController`), así que esa relación no está en el
 * esquema y no se inventa.
 *
 * Lo de otros módulos llega por su `Contratos/` (ADR 0003, regla 2): el dron
 * por `LecturaDrones` de Operaciones, los repuestos por
 * `LecturaConsumosPorOrden` de Inventario, el monto por
 * `LecturaGastoMantenimiento` de Finanzas. El vehículo sí es propio.
 *
 * Cada tarjeta se gatea por el permiso de LO QUE MUESTRA contra el ROL ACTIVO
 * (invariante 10), no por el de la orden que se mira: ver el dron intervenido
 * es ver drones. Una categoría sin permiso se omite del todo.
 *
 * @phpstan-type Tarjeta array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>}
 */
final class ResumenRelacionadoDeOrden
{
    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly LecturaDrones $lecturaDrones,
        private readonly LecturaConsumosPorOrden $lecturaConsumos,
        private readonly LecturaGastoMantenimiento $lecturaGasto,
        private readonly ContarOrdenesDeEquipo $contarOrdenesDeEquipo,
    ) {}

    /**
     * Las tarjetas de esta orden, en orden de cercanía: el equipo, lo que se
     * consumió y lo que costó. Las que el rol no puede ver quedan fuera.
     *
     * @return list<Tarjeta>
     */
    public function tarjetas(Request $request, OrdenMantenimiento $orden): array
    {
        return array_values(array_filter([
            $this->equipo($request, $orden),
            $this->repuestos($request, $orden),
            $this->gasto($request, $orden),
        ]));
    }

    /**
     * El dron o el vehículo al que se le hizo el trabajo, con cuántas órdenes
     * acumula. El atajo lleva a su ficha, que es donde se lo edita.
     *
     * @return Tarjeta|null
     */
    private function equipo(Request $request, OrdenMantenimiento $orden): ?array
    {
        $esDron = $orden->equipo_tipo === ContarOrdenesDeEquipo::TIPO_DRON;

        if (! $this->autorizacion->tienePermiso($request, $esDron ? 'operaciones.dron.ver' : 'mantenimiento.vehiculo.ver')) {
            return null;
        }

        [$identificador, $detalle, $href] = $esDron
            ? $this->dron($orden->equipo_id)
            : $this->vehiculo($orden->equipo_id);

        $ordenes = $this->contarOrdenesDeEquipo->ejecutar($orden->equipo_tipo, $orden->equipo_id);

        $items = [
            ['label' => __('mantenimiento.aside.orden_equipo_identificador'), 'value' => $identificador, 'mono' => true],
        ];

        if ($detalle !== null) {
            $items[] = ['label' => __('mantenimiento.aside.orden_equipo_detalle'), 'value' => $detalle];
        }

        $items[] = ['label' => __('mantenimiento.aside.orden_equipo_ordenes'), 'value' => (string) $ordenes['total'], 'mono' => true];
        $items[] = [
            'label' => __('mantenimiento.aside.orden_equipo_abiertas'),
            'value' => (string) $ordenes['abiertas'],
            'mono' => true,
            'variant' => $ordenes['abiertas'] > 0 ? 'warning' : 'neutral',
        ];

        return [
            'titulo' => __($esDron ? 'mantenimiento.aside.orden_equipo_titulo_dron' : 'mantenimiento.aside.orden_equipo_titulo_vehiculo'),
            'icono' => $esDron ? 'flight' : 'local_shipping',
            // El equipo existe desde que la orden se abrió: la tarjeta nunca
            // está vacía, salvo que lo hayan dado de baja.
            'tieneDatos' => $href !== null,
            'items' => $items,
            'vacioTitulo' => __('mantenimiento.aside.orden_equipo_vacio_titulo'),
            'vacioDetalle' => __('mantenimiento.aside.orden_equipo_vacio_detalle'),
            'acciones' => $href === null ? [] : [[
                'label' => __($esDron ? 'mantenimiento.aside.orden_equipo_accion_dron' : 'mantenimiento.aside.orden_equipo_accion_vehiculo'),
                'href' => $href,
                'icono' => 'open_in_new',
            ]],
        ];
    }

    /**
     * Repuestos que consumió el cierre: cuántas líneas, cuántas unidades y
     * cuánto costaron. Una orden abierta todavía no consumió nada — el aside
     * completo ni siquiera se arma en ese caso (ver la ficha).
     *
     * @return Tarjeta|null
     */
    private function repuestos(Request $request, OrdenMantenimiento $orden): ?array
    {
        if (! $this->autorizacion->tienePermiso($request, 'inventario.repuesto.ver')) {
            return null;
        }

        $consumos = $this->lecturaConsumos->resumenDeOrden($orden->id);

        return [
            'titulo' => __('mantenimiento.aside.orden_repuestos_titulo'),
            'icono' => 'inventory_2',
            'tieneDatos' => $consumos->lineas > 0,
            'items' => [
                ['label' => __('mantenimiento.aside.orden_repuestos_lineas'), 'value' => (string) $consumos->lineas, 'mono' => true],
                ['label' => __('mantenimiento.aside.orden_repuestos_unidades'), 'value' => $this->numero($consumos->unidades), 'mono' => true],
                ['label' => __('mantenimiento.aside.orden_repuestos_costo'), 'value' => $this->monto($consumos->costoTotal), 'mono' => true],
            ],
            'vacioTitulo' => __('mantenimiento.aside.orden_repuestos_vacio_titulo'),
            'vacioDetalle' => __('mantenimiento.aside.orden_repuestos_vacio_detalle'),
            'acciones' => $consumos->lineas > 0 ? [[
                'label' => __('mantenimiento.aside.orden_repuestos_accion'),
                'href' => route('panel.stock.index'),
                'icono' => 'list',
            ]] : [],
        ];
    }

    /**
     * El gasto que el cierre imputó a Finanzas. `gasto_id` es una FK real,
     * pero el monto se pide siempre por el contrato de lectura — nunca se
     * copia a `Mantenimiento`.
     *
     * @return Tarjeta|null
     */
    private function gasto(Request $request, OrdenMantenimiento $orden): ?array
    {
        if (! $this->autorizacion->tienePermiso($request, 'finanzas.gasto.ver')) {
            return null;
        }

        $monto = $orden->gasto_id === null ? null : $this->lecturaGasto->montoDe($orden->gasto_id);

        $items = [];

        if ($orden->gasto_id !== null) {
            $items[] = ['label' => __('mantenimiento.aside.orden_gasto_numero'), 'value' => __('mantenimiento.ordenes.detalle_gasto_valor', ['id' => $orden->gasto_id]), 'mono' => true];
            $items[] = ['label' => __('mantenimiento.aside.orden_gasto_monto'), 'value' => $this->monto($monto ?? '0'), 'mono' => true, 'variant' => 'success'];
        }

        return [
            'titulo' => __('mantenimiento.aside.orden_gasto_titulo'),
            'icono' => 'payments',
            'tieneDatos' => $orden->gasto_id !== null,
            'items' => $items,
            'vacioTitulo' => __('mantenimiento.aside.orden_gasto_vacio_titulo'),
            'vacioDetalle' => __('mantenimiento.aside.orden_gasto_vacio_detalle'),
            'acciones' => $orden->gasto_id === null ? [] : [[
                'label' => __('mantenimiento.aside.orden_gasto_accion'),
                'href' => route('panel.gastos.index'),
                'icono' => 'list',
            ]],
        ];
    }

    /**
     * Identificador, detalle y ficha de un dron, por el contrato de lectura de
     * Operaciones. Un dron dado de baja deja el `#id` crudo y sin atajo.
     *
     * @return array{0: string, 1: string|null, 2: string|null}
     */
    private function dron(int $dronId): array
    {
        $dron = $this->lecturaDrones->porIds([$dronId])[$dronId] ?? null;

        return $dron === null
            ? ["#{$dronId}", null, null]
            : [$dron->identificador, $dron->modelo, route('panel.drones.edit', $dronId)];
    }

    /**
     * Lo mismo para un vehículo, que sí es de este módulo.
     *
     * @return array{0: string, 1: string|null, 2: string|null}
     */
    private function vehiculo(int $vehiculoId): array
    {
        $vehiculo = Vehiculo::query()->find($vehiculoId);

        return $vehiculo === null
            ? ["#{$vehiculoId}", null, null]
            : [$vehiculo->identificador, trim(($vehiculo->marca ?? '').' '.($vehiculo->modelo ?? '')) ?: null, route('panel.vehiculos.edit', $vehiculoId)];
    }

    /** Cantidad con separadores locales, sin unidad. */
    private function numero(string $valor): string
    {
        return number_format((float) $valor, 2, ',', '.');
    }

    /** Monto en bolivianos, con la misma forma que el resto del panel. */
    private function monto(string $valor): string
    {
        return __('mantenimiento.aside.monto_valor', ['monto' => $this->numero($valor)]);
    }
}
