<?php

namespace App\Dominios\Inventario\Infraestructura\Http;

use App\Dominios\Inventario\Aplicacion\ResumirRepuesto;
use App\Dominios\Inventario\Dominio\SentidoAjusteInventario;
use App\Dominios\Inventario\Dominio\TipoMovimientoInventario;
use App\Dominios\Inventario\Infraestructura\Eloquent\Repuesto;
use App\Dominios\Mantenimiento\Contratos\LecturaResumenOrdenesMantenimiento;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Tarjetas del resumen relacionado (aside de la ficha de edición de un
 * repuesto, §6.3.1 de la guía de pantalla): lo que hay de él en las bases, sus
 * últimos movimientos y las órdenes de mantenimiento que lo consumieron.
 *
 * Las tres relaciones salen de las FK reales: `inv_stock.repuesto_id` e
 * `inv_movimientos.repuesto_id` (este módulo) y, por
 * `inv_movimientos.orden_mantenimiento_id`, las órdenes que cerraron
 * descontándolo. **No hay tarjeta de proveedores ni de bases**: el repuesto no
 * tiene proveedor en el esquema, y las bases ya están dentro de la existencia.
 *
 * Lo de este módulo lo lee {@see ResumirRepuesto}; lo de Mantenimiento llega por
 * su `Contratos/` (ADR 0003, regla 2), con los ids de orden que este módulo
 * escribió al cerrarlas — nunca el modelo ajeno ni un join a `man_`.
 *
 * Cada tarjeta se gatea por el permiso de LO QUE MUESTRA contra el ROL ACTIVO
 * (invariante 10), no por el del repuesto que se edita: ver el stock es
 * `inventario.movimiento.ver`, ver órdenes es `mantenimiento.orden.ver`. Una
 * categoría sin permiso se omite del todo, y no se consulta.
 *
 * Todo dato DECIMAL se formatea con {@see FormatoCantidad} sin pasar por `float`.
 * Las fechas se pasan a la zona horaria del usuario: la base guarda UTC, y un
 * movimiento de la noche se leería con el día siguiente.
 *
 * @phpstan-type Tarjeta array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>}
 */
final class ResumenRelacionadoDeRepuesto
{
    private const PERMISO_MOVIMIENTO_VER = 'inventario.movimiento.ver';

    private const PERMISO_MOVIMIENTO_CREAR = 'inventario.movimiento.crear';

    private const PERMISO_ORDEN_VER = 'mantenimiento.orden.ver';

    private const PERMISO_ORDEN_CREAR = 'mantenimiento.orden.crear';

    /** Cuántos asientos recientes se listan en la tarjeta de movimientos. */
    private const MOVIMIENTOS_VISIBLES = 3;

    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly ResumirRepuesto $resumirRepuesto,
        private readonly LecturaResumenOrdenesMantenimiento $lecturaOrdenes,
    ) {}

    /**
     * Las tarjetas de este repuesto, en orden de cercanía: lo que hay, cómo
     * llegó ahí y dónde se consumió. Las que el rol no puede ver quedan fuera.
     *
     * @return list<Tarjeta>
     */
    public function tarjetas(Request $request, Repuesto $repuesto, ?string $zonaHoraria = null): array
    {
        return array_values(array_filter([
            $this->existencias($request, $repuesto),
            $this->movimientos($request, $repuesto, $zonaHoraria),
            $this->ordenes($request, $repuesto, $zonaHoraria),
        ]));
    }

    /**
     * Cuánto queda en total, en cuántas bases y cuántas filas piden reposición
     * (la misma regla que la alerta del listado de stock).
     *
     * @return Tarjeta|null
     */
    private function existencias(Request $request, Repuesto $repuesto): ?array
    {
        if (! $this->puede($request, self::PERMISO_MOVIMIENTO_VER)) {
            return null;
        }

        $existencias = $this->resumirRepuesto->existencias($repuesto);
        $tieneDatos = $existencias['filas'] > 0;

        $acciones = [];

        // «Ver stock» solo tiene sentido si hay algo que ver: con la tarjeta
        // vacía el único atajo útil es registrar el primer movimiento.
        if ($tieneDatos) {
            $acciones[] = [
                'label' => __('inventario.aside.existencias_accion_ver'),
                'href' => route('panel.stock.index', ['q' => $repuesto->codigo]),
                'icono' => 'list',
            ];
        }

        if ($this->puede($request, self::PERMISO_MOVIMIENTO_CREAR)) {
            $acciones[] = [
                'label' => __('inventario.aside.accion_registrar_movimiento'),
                'href' => route('panel.stock.movimientos.create', ['repuesto_id' => $repuesto->id]),
                'icono' => 'add',
            ];
        }

        return [
            'titulo' => __('inventario.aside.existencias_titulo'),
            'icono' => 'inventory_2',
            'tieneDatos' => $tieneDatos,
            'items' => [
                ['label' => __('inventario.aside.existencias_total'), 'value' => $this->cantidad($existencias['total'], $repuesto), 'mono' => true],
                ['label' => __('inventario.aside.existencias_bases'), 'value' => (string) $existencias['basesConStock'], 'mono' => true],
                [
                    'label' => __('inventario.aside.existencias_bajo_minimo'),
                    'value' => (string) $existencias['bajoMinimo'],
                    'mono' => true,
                    'variant' => $existencias['bajoMinimo'] > 0 ? 'warning' : 'neutral',
                ],
            ],
            'vacioTitulo' => __('inventario.aside.existencias_vacio_titulo'),
            'vacioDetalle' => __('inventario.aside.existencias_vacio_detalle'),
            'acciones' => $acciones,
        ];
    }

    /**
     * Cuántos asientos tiene y los tres últimos, con su signo. Sin acción: el
     * atajo a registrar uno ya vive en la tarjeta de existencias, y repetirlo
     * acá dejaría dos botones iguales uno sobre otro.
     *
     * @return Tarjeta|null
     */
    private function movimientos(Request $request, Repuesto $repuesto, ?string $zonaHoraria): ?array
    {
        if (! $this->puede($request, self::PERMISO_MOVIMIENTO_VER)) {
            return null;
        }

        $movimientos = $this->resumirRepuesto->movimientos($repuesto, self::MOVIMIENTOS_VISIBLES);

        $items = [
            ['label' => __('inventario.aside.movimientos_total'), 'value' => (string) $movimientos['total'], 'mono' => true],
        ];

        foreach ($movimientos['ultimos'] as $movimiento) {
            $suma = $this->sumaStock($movimiento['tipo'], $movimiento['sentido']);
            $cantidad = $suma === null
                ? FormatoCantidad::decimal($movimiento['cantidad'])
                : FormatoCantidad::conSigno($movimiento['cantidad'], $suma);

            $items[] = [
                'label' => __('inventario.aside.movimiento_linea', [
                    'tipo' => __('inventario.aside.tipo_corto.'.$movimiento['tipo']),
                    'fecha' => $this->fecha($movimiento['instante'], $zonaHoraria),
                ]),
                'value' => $cantidad.' '.$repuesto->unidad,
                'mono' => true,
            ];
        }

        return [
            'titulo' => __('inventario.aside.movimientos_titulo'),
            'icono' => 'swap_horiz',
            'tieneDatos' => $movimientos['total'] > 0,
            'items' => $items,
            'vacioTitulo' => __('inventario.aside.movimientos_vacio_titulo'),
            'vacioDetalle' => __('inventario.aside.movimientos_vacio_detalle'),
            'acciones' => [],
        ];
    }

    /**
     * Las órdenes de mantenimiento que lo descontaron al cerrarse: cuántas,
     * cuántas fueron correctivas, cuántas unidades sumaron y cuándo fue la
     * última. Los ids salen de este módulo; lo que las órdenes son, de
     * Mantenimiento.
     *
     * @return Tarjeta|null
     */
    private function ordenes(Request $request, Repuesto $repuesto, ?string $zonaHoraria): ?array
    {
        if (! $this->puede($request, self::PERMISO_ORDEN_VER)) {
            return null;
        }

        $consumo = $this->resumirRepuesto->consumoEnOrdenes($repuesto);
        $ordenes = $this->lecturaOrdenes->deIds($consumo['ordenIds']);
        $tieneDatos = $ordenes->total > 0;

        $acciones = [];

        if ($tieneDatos) {
            $acciones[] = [
                'label' => __('inventario.aside.ordenes_accion_ver'),
                'href' => route('panel.ordenes-mantenimiento.index'),
                'icono' => 'list',
            ];
        } elseif ($this->puede($request, self::PERMISO_ORDEN_CREAR)) {
            $acciones[] = [
                'label' => __('inventario.aside.ordenes_accion_nueva'),
                'href' => route('panel.ordenes-mantenimiento.create'),
                'icono' => 'add',
            ];
        }

        return [
            'titulo' => __('inventario.aside.ordenes_titulo'),
            'icono' => 'build',
            'tieneDatos' => $tieneDatos,
            'items' => [
                ['label' => __('inventario.aside.ordenes_total'), 'value' => (string) $ordenes->total, 'mono' => true],
                ['label' => __('inventario.aside.ordenes_correctivas'), 'value' => (string) $ordenes->correctivas, 'mono' => true],
                ['label' => __('inventario.aside.ordenes_unidades'), 'value' => $this->cantidad($consumo['unidades'], $repuesto), 'mono' => true],
                ['label' => __('inventario.aside.ordenes_ultimo_cierre'), 'value' => $this->fecha($ordenes->ultimoCierre, $zonaHoraria), 'mono' => true],
            ],
            'vacioTitulo' => __('inventario.aside.ordenes_vacio_titulo'),
            'vacioDetalle' => __('inventario.aside.ordenes_vacio_detalle'),
            'acciones' => $acciones,
        ];
    }

    private function puede(Request $request, string $permiso): bool
    {
        return $this->autorizacion->tienePermiso($request, $permiso);
    }

    /** «12,00 kg»: la cantidad con la unidad del repuesto. */
    private function cantidad(string $valor, Repuesto $repuesto): string
    {
        return FormatoCantidad::decimal($valor).' '.$repuesto->unidad;
    }

    /**
     * Si el asiento suma al stock (`true`), resta (`false`) o solo lo mueve de
     * una base a otra (`null`: un traslado no cambia la existencia total).
     */
    private function sumaStock(string $tipo, ?string $sentido): ?bool
    {
        return match ($tipo) {
            TipoMovimientoInventario::Compra->value => true,
            TipoMovimientoInventario::Salida->value => false,
            TipoMovimientoInventario::Ajuste->value => $sentido === SentidoAjusteInventario::Incremento->value,
            default => null,
        };
    }

    /** `d/m/Y` en la zona del usuario; sin zona, la de la base. */
    private function fecha(?string $instante, ?string $zonaHoraria): string
    {
        if ($instante === null) {
            return '—';
        }

        $fecha = Carbon::parse($instante);

        if ($zonaHoraria !== null && $zonaHoraria !== '') {
            $fecha = $fecha->setTimezone($zonaHoraria);
        }

        return $fecha->format('d/m/Y');
    }
}
