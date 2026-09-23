<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Operaciones\Aplicacion\ListarReportesTecnicos;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * `GET /panel/reportes/tecnicos` (HU-43, tarea 57): "como encargado, quiero
 * listar y descargar los reportes técnicos generados, para reenviarlos al
 * agrónomo" (`plan_sprints.md` Sprint 12, §252). Pantalla de solo lectura —
 * agrega datos ya persistidos, no genera ni muta nada. La descarga de cada
 * fila reusa `panel.trabajos.reporte-pdf` (`TrabajosController::reporteTecnicoPdf`,
 * HU-18); esta pantalla nunca sirve el PDF.
 *
 * Un único permiso (`operaciones.reporte.ver`, ya usado por la descarga
 * individual — mismo criterio del prompt: "misma acción de negocio"),
 * verificado DENTRO del controlador contra el ROL ACTIVO, mismo patrón que
 * el resto del panel.
 *
 * `clientesDisponibles` sale de una segunda llamada SIN filtro al caso de
 * uso, no de `Cliente::query()` directo: `Operaciones` no puede importar el
 * modelo Eloquent de `Comercial` (ADR 0003, regla 2) — mismo motivo por el
 * que `ListarReportesTecnicos` compone vía `LecturaContrato` en vez de un
 * JOIN. El volumen de reportes no lo justifica (mismo criterio que el resto
 * de los contratos de lectura de esta tarea).
 */
final class ReportesTecnicosController
{
    private const PERMISO_VER = 'operaciones.reporte.ver';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarReportesTecnicos $listarReportes): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        [$clienteId, $desde, $hasta] = $this->filtros($request);

        return view('operaciones::pages.reportes-tecnicos.index', [
            ...$this->autorizacion->cascara($request),
            'reportes' => $listarReportes->ejecutar($clienteId, $desde, $hasta),
            'filtros' => ['cliente_id' => $clienteId, 'desde' => $desde, 'hasta' => $hasta],
            'clientesDisponibles' => $this->clientesDisponibles($listarReportes),
        ]);
    }

    /** @return array{0: ?int, 1: ?string, 2: ?string} */
    private function filtros(Request $request): array
    {
        return [
            $request->filled('cliente_id') ? $request->integer('cliente_id') : null,
            $request->filled('desde') ? TextoDeFiltro::de($request, 'desde') : null,
            $request->filled('hasta') ? TextoDeFiltro::de($request, 'hasta') : null,
        ];
    }

    /** @return Collection<int, string> id => nombre, sin duplicados */
    private function clientesDisponibles(ListarReportesTecnicos $listarReportes): Collection
    {
        return collect($listarReportes->ejecutar())
            ->unique('clienteId')
            ->sortBy('clienteNombre')
            ->pluck('clienteNombre', 'clienteId');
    }
}
