<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http;

use App\Dominios\Mantenimiento\Aplicacion\ContarOrdenesDeDrones;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\PlanMantenimiento;
use App\Dominios\Operaciones\Contratos\LecturaDrones;
use App\Dominios\Operaciones\Contratos\LecturaHorasVueloPorModelo;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\Request;

/**
 * Tarjetas del resumen relacionado (aside de la ficha de un plan de
 * mantenimiento preventivo, §6.3.1 de la guía de pantalla): los drones a los
 * que el plan aplica y el mantenimiento que esos drones acumulan. Hermano de
 * {@see ResumenRelacionadoDeOrden} y {@see ResumenRelacionadoDeEquipo}.
 *
 * **El plan no «genera» órdenes, y por eso no hay una tarjeta que lo diga**:
 * `man_ordenes_mantenimiento` no tiene `plan_id` y la apertura automática al
 * cruzar el umbral está fuera de alcance (ver el docblock de
 * `PlanesMantenimientoController`). La segunda tarjeta cuenta las órdenes de
 * los DRONES del modelo, que es la relación que el esquema sí tiene; el copy
 * lo dice con esas palabras para no prometer una trazabilidad que no existe.
 *
 * El plan se cruza con el dron por IGUALDAD DE TEXTO del modelo, nunca por FK
 * (ver el docblock de `PlanMantenimiento`): los drones llegan por
 * {@see LecturaDrones} y sus horas por {@see LecturaHorasVueloPorModelo}, los
 * dos contratos de `Operaciones` (ADR 0003, regla 2). Las órdenes sí son de
 * este módulo.
 *
 * Cada tarjeta se gatea por el permiso de LO QUE MUESTRA contra el ROL ACTIVO
 * (invariante 10), no por el del plan que se edita: ver los drones del modelo
 * es ver drones.
 *
 * @phpstan-type Tarjeta array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>}
 */
final class ResumenRelacionadoDePlan
{
    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly LecturaDrones $lecturaDrones,
        private readonly LecturaHorasVueloPorModelo $lecturaHorasVuelo,
        private readonly ContarOrdenesDeDrones $contarOrdenesDeDrones,
    ) {}

    /**
     * Las tarjetas de este plan, en orden de cercanía: a quiénes aplica y qué
     * mantenimiento llevan. Las que el rol no puede ver quedan fuera.
     *
     * @return list<Tarjeta>
     */
    public function tarjetas(Request $request, PlanMantenimiento $plan): array
    {
        $dronIds = $this->dronIdsDelModelo($plan->modelo);

        return array_values(array_filter([
            $this->drones($request, $plan, $dronIds),
            $this->ordenes($request, $dronIds),
        ]));
    }

    /**
     * Los drones del modelo del plan: cuántos son, cuántos ya volaron lo
     * suficiente para tener horas registradas y cuántos cruzaron el umbral —
     * que es exactamente lo que enciende la alerta del listado.
     *
     * @param  list<int>  $dronIds
     * @return Tarjeta|null
     */
    private function drones(Request $request, PlanMantenimiento $plan, array $dronIds): ?array
    {
        if (! $this->autorizacion->tienePermiso($request, 'operaciones.dron.ver')) {
            return null;
        }

        $horasPorDron = $this->lecturaHorasVuelo->horasAcumuladasPorModelo($plan->modelo);
        $umbral = (float) $plan->horas_umbral;
        $enUmbral = count(array_filter($horasPorDron, fn (float $horas): bool => $horas >= $umbral));

        return [
            'titulo' => __('mantenimiento.aside.plan_drones_titulo'),
            'icono' => 'flight',
            'tieneDatos' => $dronIds !== [],
            'items' => [
                ['label' => __('mantenimiento.aside.plan_drones_total'), 'value' => (string) count($dronIds), 'mono' => true],
                ['label' => __('mantenimiento.aside.plan_drones_con_horas'), 'value' => (string) count($horasPorDron), 'mono' => true],
                [
                    'label' => __('mantenimiento.aside.plan_drones_en_umbral'),
                    'value' => (string) $enUmbral,
                    'mono' => true,
                    'variant' => $enUmbral > 0 ? 'warning' : 'neutral',
                ],
            ],
            'vacioTitulo' => __('mantenimiento.aside.plan_drones_vacio_titulo'),
            'vacioDetalle' => __('mantenimiento.aside.plan_drones_vacio_detalle'),
            'acciones' => [[
                'label' => __('mantenimiento.aside.plan_drones_accion'),
                'href' => route('panel.drones.index'),
                'icono' => 'list',
            ]],
        ];
    }

    /**
     * El mantenimiento que acumulan esos drones. No son «las órdenes del
     * plan» —esa relación no existe en el esquema— sino las de los equipos a
     * los que el plan aplica.
     *
     * @param  list<int>  $dronIds
     * @return Tarjeta|null
     */
    private function ordenes(Request $request, array $dronIds): ?array
    {
        if (! $this->autorizacion->tienePermiso($request, 'mantenimiento.orden.ver')) {
            return null;
        }

        $ordenes = $this->contarOrdenesDeDrones->ejecutar($dronIds);

        return [
            'titulo' => __('mantenimiento.aside.plan_ordenes_titulo'),
            'icono' => 'build',
            'tieneDatos' => $ordenes['total'] > 0,
            'items' => [
                ['label' => __('mantenimiento.aside.plan_ordenes_total'), 'value' => (string) $ordenes['total'], 'mono' => true],
                [
                    'label' => __('mantenimiento.aside.plan_ordenes_abiertas'),
                    'value' => (string) $ordenes['abiertas'],
                    'mono' => true,
                    'variant' => $ordenes['abiertas'] > 0 ? 'warning' : 'neutral',
                ],
            ],
            'vacioTitulo' => __('mantenimiento.aside.plan_ordenes_vacio_titulo'),
            'vacioDetalle' => __('mantenimiento.aside.plan_ordenes_vacio_detalle'),
            'acciones' => [[
                'label' => __('mantenimiento.aside.plan_ordenes_accion'),
                'href' => route('panel.ordenes-mantenimiento.index'),
                'icono' => 'list',
            ]],
        ];
    }

    /**
     * Ids de los drones vivos cuyo modelo es exactamente el del plan. El
     * catálogo operativo es corto y llega entero por el contrato: filtrar acá
     * evita sumarle a `Operaciones` un método que solo usa esta pantalla.
     *
     * @return list<int>
     */
    private function dronIdsDelModelo(string $modelo): array
    {
        return array_values(array_map(
            fn ($dron): int => $dron->id,
            array_filter($this->lecturaDrones->disponibles(), fn ($dron): bool => $dron->modelo === $modelo),
        ));
    }
}
