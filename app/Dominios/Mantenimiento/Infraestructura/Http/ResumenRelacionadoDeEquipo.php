<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http;

use App\Dominios\Finanzas\Contratos\LecturaCombustiblePorRecurso;
use App\Dominios\Personal\Contratos\LecturaCuadrillasPorRecurso;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\Request;

/**
 * Tarjetas del resumen relacionado (aside de la ficha de edición, §6.3.1 de
 * la guía de pantalla) que comparten la batería, el generador y el vehículo:
 * las cuadrillas que lo tienen asignado (Personal) y el combustible que se le
 * cargó (Finanzas). Ambas llegan por el `Contratos/` del módulo dueño (ADR
 * 0003, regla 2) — Mantenimiento no toca `per_equipo_recursos` ni
 * `fin_combustibles`.
 *
 * Cada tarjeta se gatea por el permiso de LO QUE MUESTRA contra el ROL ACTIVO
 * (invariante 10), no por el permiso de la entidad que se edita: ver las
 * cuadrillas de una batería es ver cuadrillas. Una categoría sin `.ver` NI
 * `.crear` se omite del todo (devuelve `null`); con `.crear` pero sin `.ver`
 * se ofrece el atajo sin revelar cifras.
 *
 * Vive junto al controlador y no en el catálogo de componentes: arma datos, no
 * pinta. La forma del arreglo es la que recorre
 * `mantenimiento::pages._resumen-relacionado`.
 *
 * @phpstan-type Tarjeta array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>}
 */
final class ResumenRelacionadoDeEquipo
{
    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly LecturaCuadrillasPorRecurso $lecturaCuadrillas,
        private readonly LecturaCombustiblePorRecurso $lecturaCombustible,
    ) {}

    /**
     * Cuadrillas que tienen (o tuvieron) el recurso. «Vigente» es lo mismo que
     * en el listado de cuadrillas. No hay atajo de alta: el recurso se asigna
     * desde la ficha de la cuadrilla.
     *
     * @param  LecturaCuadrillasPorRecurso::TIPO_*  $recursoTipo
     * @param  string  $vacioDetalle  frase del vacío, con el género del objeto («esta batería», «este vehículo»).
     * @return Tarjeta|null
     */
    public function cuadrillas(Request $request, string $recursoTipo, int $recursoId, string $vacioDetalle): ?array
    {
        if (! $this->autorizacion->tienePermiso($request, 'personal.equipo_trabajo.ver')) {
            return null;
        }

        $cuadrillas = $this->lecturaCuadrillas->deRecurso($recursoTipo, $recursoId, now()->toDateString());

        $items = [
            [
                'label' => __('mantenimiento.aside.cuadrillas_vigentes'),
                'value' => (string) $cuadrillas->vigentes,
                'mono' => true,
                'variant' => $cuadrillas->vigentes > 0 ? 'success' : 'neutral',
            ],
            ['label' => __('mantenimiento.aside.cuadrillas_historial'), 'value' => (string) $cuadrillas->historial, 'mono' => true],
        ];

        if ($cuadrillas->codigosVigentes !== []) {
            $items[] = [
                'label' => __('mantenimiento.aside.cuadrillas_actual'),
                'value' => implode(', ', $cuadrillas->codigosVigentes),
                'mono' => true,
            ];
        }

        return [
            'titulo' => __('mantenimiento.aside.cuadrillas_titulo'),
            'icono' => 'groups',
            'tieneDatos' => $cuadrillas->historial > 0,
            'items' => $items,
            'vacioTitulo' => __('mantenimiento.aside.cuadrillas_vacio_titulo'),
            'vacioDetalle' => $vacioDetalle,
            'acciones' => $cuadrillas->historial > 0 ? [[
                'label' => __('mantenimiento.aside.cuadrillas_accion_ver'),
                'href' => route('panel.cuadrillas.index'),
                'icono' => 'list',
            ]] : [],
        ];
    }

    /**
     * Cargas de combustible imputadas al recurso: cuántas y cuántos litros
     * suman. El alta de una carga no acepta el recurso precargado, así que el
     * atajo lleva al formulario y el vacío dice qué elegir.
     *
     * @param  LecturaCombustiblePorRecurso::TIPO_*  $recursoTipo
     * @param  array<string, string>  $origenNavegacion  memento `volver_a`/`volver_texto` de la ficha desde la que se sale.
     * @return Tarjeta|null
     */
    public function combustible(Request $request, string $recursoTipo, int $recursoId, array $origenNavegacion, string $vacioDetalle): ?array
    {
        $puedeVer = $this->autorizacion->tienePermiso($request, 'finanzas.combustible.ver');
        $puedeCargar = $this->autorizacion->tienePermiso($request, 'finanzas.combustible.crear');

        if (! $puedeVer && ! $puedeCargar) {
            return null;
        }

        $combustible = $puedeVer ? $this->lecturaCombustible->deRecurso($recursoTipo, $recursoId) : null;
        $cargas = $combustible->cargas ?? 0;

        $acciones = [];

        if ($cargas > 0) {
            $acciones[] = ['label' => __('mantenimiento.aside.combustible_accion_ver'), 'href' => route('panel.combustible.index'), 'icono' => 'list'];
        }

        if ($puedeCargar) {
            $acciones[] = [
                'label' => __('mantenimiento.aside.combustible_accion_cargar'),
                'href' => route('panel.combustible.create', $origenNavegacion),
                'icono' => 'add',
            ];
        }

        return [
            'titulo' => __('mantenimiento.aside.combustible_titulo'),
            'icono' => 'local_gas_station',
            'tieneDatos' => $cargas > 0,
            'items' => [
                ['label' => __('mantenimiento.aside.combustible_cargas'), 'value' => (string) $cargas, 'mono' => true],
                [
                    'label' => __('mantenimiento.aside.combustible_litros'),
                    'value' => __('mantenimiento.aside.litros_valor', ['cantidad' => number_format((float) ($combustible->litros ?? '0'), 2, ',', '.')]),
                    'mono' => true,
                ],
            ],
            'vacioTitulo' => __('mantenimiento.aside.combustible_vacio_titulo'),
            'vacioDetalle' => $vacioDetalle,
            'acciones' => $acciones,
        ];
    }
}
