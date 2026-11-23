<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http;

use App\Dominios\Personal\Contratos\LecturaCuadrillasPorRecurso;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\Request;

/**
 * Tarjeta del resumen relacionado (aside de la ficha de edición, §6.3.1 de la
 * guía de pantalla) de una batería: las cuadrillas que la tienen asignada
 * (Personal). Llega por el `Contratos/` del módulo dueño (ADR 0003, regla 2) —
 * Mantenimiento no toca `per_equipo_recursos`.
 *
 * La tarjeta se gatea por el permiso de LO QUE MUESTRA contra el ROL ACTIVO
 * (invariante 10), no por el permiso de la entidad que se edita: ver las
 * cuadrillas de una batería es ver cuadrillas. Sin `.ver` se omite del todo
 * (devuelve `null`).
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
    ) {}

    /**
     * Cuadrillas que tienen (o tuvieron) el recurso. «Vigente» es lo mismo que
     * en el listado de cuadrillas. No hay atajo de alta: el recurso se asigna
     * desde la ficha de la cuadrilla.
     *
     * @param  LecturaCuadrillasPorRecurso::TIPO_*  $recursoTipo
     * @param  string  $vacioDetalle  frase del vacío, con el género del objeto («esta batería»).
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
}
