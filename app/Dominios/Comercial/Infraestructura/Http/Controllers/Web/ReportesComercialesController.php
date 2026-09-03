<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Aplicacion\ObtenerAvanceComercial;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * `GET /panel/reportes/comercial*` (HU-32, tarea 46): "como dueño, quiero un
 * reporte comercial de avance por cliente, contrato y campaña, para saber
 * cuánto queda por aplicar y por cobrar" — cierra Sprint 9. Pantalla de solo
 * lectura: agrega datos ya persistidos, no genera ni muta nada.
 *
 * Un único permiso (`comercial.reporte.ver`), verificado DENTRO del
 * controlador contra el ROL ACTIVO, mismo criterio que el resto del panel.
 * Exclusivo del dueño (no entra en `PERMISOS_ENCARGADO_OPERACIONES` de
 * `SeguridadSeeder` — mismo criterio que `finanzas.planilla.aprobar`): la
 * HU dice literal "como dueño".
 */
final class ReportesComercialesController
{
    private const PERMISO_VER = 'comercial.reporte.ver';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ObtenerAvanceComercial $obtenerAvance): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        [$clienteId, $contratoId] = $this->filtros($request);

        return view('comercial::pages.reportes-comerciales.index', [
            ...$this->autorizacion->cascara($request),
            'avance' => $obtenerAvance->ejecutar($clienteId, $contratoId),
            'filtros' => ['cliente_id' => $clienteId, 'contrato_id' => $contratoId],
            'clientesDisponibles' => Cliente::query()
                ->whereHas('contratos')
                ->orderBy('razon_social')
                ->get(['id', 'razon_social']),
            'contratosDisponibles' => Contrato::query()
                ->with('cliente:id,razon_social')
                ->orderByDesc('id')
                ->get(['id', 'cliente_id']),
        ]);
    }

    /**
     * `GET /panel/reportes/comercial/exportar` — CSV nativo
     * (`StreamedResponse`, sin librería de Excel: `composer.json` no trae
     * ninguna), mismas columnas que la tabla, respetando el filtro activo.
     */
    public function exportar(Request $request, ObtenerAvanceComercial $obtenerAvance): StreamedResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        [$clienteId, $contratoId] = $this->filtros($request);

        $avance = $obtenerAvance->ejecutar($clienteId, $contratoId);

        return response()->streamDownload(function () use ($avance): void {
            $salida = fopen('php://output', 'w');

            if ($salida === false) {
                return;
            }

            fputcsv($salida, [
                __('comercial.reportes_comerciales.col_cliente'),
                __('comercial.reportes_comerciales.col_contrato'),
                __('comercial.reportes_comerciales.col_hectareas_contratadas'),
                __('comercial.reportes_comerciales.col_hectareas_aplicadas'),
                __('comercial.reportes_comerciales.col_hectareas_facturadas'),
                __('comercial.reportes_comerciales.col_monto_facturado'),
            ], ';');

            foreach ($avance as $fila) {
                fputcsv($salida, [
                    $fila['clienteNombre'],
                    $fila['contratoId'],
                    $fila['hectareasContratadas'],
                    $fila['hectareasAplicadas'],
                    $fila['hectareasFacturadas'],
                    $fila['montoFacturado'],
                ], ';');
            }

            fclose($salida);
        }, 'avance-comercial.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: ?int, 1: ?int} */
    private function filtros(Request $request): array
    {
        return [
            $request->filled('cliente_id') ? $request->integer('cliente_id') : null,
            $request->filled('contrato_id') ? $request->integer('contrato_id') : null,
        ];
    }
}
