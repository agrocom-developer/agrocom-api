<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Aplicacion\FiltrosInformeAvanceContratos;
use App\Dominios\Comercial\Aplicacion\ObtenerInformeAvanceContratos;
use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Dominio\SaldoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET /panel/reportes/comercial` (HU-52, tarea 75, espec §9.1): informe de
 * avance de contratos por cultivo y por cliente — reemplaza a la pantalla
 * plana de HU-32 (tarea 46) en la misma ruta/permiso (`comercial.reporte.ver`,
 * exclusivo del dueño, ítem de menú `menu.reportes.items.comerciales` sin
 * cambios). Pantalla de solo lectura: agrega datos ya persistidos, no genera
 * ni muta nada.
 *
 * Entrada obligatoria (espec §9.1): al menos un cliente y un cultivo. El
 * marcador `consultado` (hidden siempre presente en el formulario de
 * entrada) distingue "primera visita" (sin marcador → estado vacío, sin
 * errores) de "se intentó generar sin completar la entrada" (marcador
 * presente, alguno de los dos arrays vacío → mensaje de error bajo el
 * selector que falta, `ObtenerInformeAvanceContratos` ni se llama).
 *
 * Sin `exportar` (a diferencia de HU-32): el CSV plano de una fila por
 * contrato no tiene una forma razonable para un informe agrupado en dos
 * niveles (cultivo → contrato, o cultivo → cliente → contrato) — no está en
 * el alcance de esta tarea, se retoma si se pide explícitamente.
 */
final class ReportesComercialesController
{
    private const PERMISO_VER = 'comercial.reporte.ver';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ObtenerInformeAvanceContratos $obtenerInforme): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $consultado = $request->has('consultado');
        $clienteIds = $this->idsDe($request, 'cliente_ids');
        $cultivoIds = $this->idsDe($request, 'cultivo_ids');

        $erroresEntrada = [];
        $informe = null;

        if ($consultado) {
            if ($clienteIds === []) {
                $erroresEntrada['cliente_ids'] = __('comercial.reportes_comerciales.entrada.error_cliente');
            }

            if ($cultivoIds === []) {
                $erroresEntrada['cultivo_ids'] = __('comercial.reportes_comerciales.entrada.error_cultivo');
            }

            if ($erroresEntrada === []) {
                $informe = $obtenerInforme->ejecutar($this->filtrosDesde($request, $clienteIds, $cultivoIds));
            }
        }

        $tabActiva = $request->string('tab')->value() === 'por_cliente' ? 'por_cliente' : 'por_cultivo';

        return view('comercial::pages.reportes-comerciales.index', [
            ...$this->autorizacion->cascara($request),
            'consultado' => $consultado,
            'erroresEntrada' => $erroresEntrada,
            'informe' => $informe,
            'tabActiva' => $tabActiva,
            'clienteIds' => $clienteIds,
            'cultivoIds' => $cultivoIds,
            'campaniaIds' => $this->idsDe($request, 'campania_ids'),
            'fechaDesde' => $request->string('fecha_desde')->value() ?: null,
            'fechaHasta' => $request->string('fecha_hasta')->value() ?: null,
            'estado' => EstadoContrato::tryFrom((string) $request->string('estado')),
            'saldo' => SaldoContrato::tryFrom((string) $request->string('saldo')),
            'incluirDeshabilitados' => $request->boolean('incluir_deshabilitados'),
            'clientesDisponibles' => Cliente::query()
                ->whereHas('contratos')
                ->orderBy('razon_social')
                ->get(['id', 'razon_social']),
            'cultivosDisponibles' => Cultivo::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
            'campaniasDisponibles' => $this->campaniasDe($clienteIds),
            'estadosDisponibles' => EstadoContrato::cases(),
            'saldosDisponibles' => SaldoContrato::cases(),
        ]);
    }

    /** @return list<int> */
    private function idsDe(Request $request, string $clave): array
    {
        /** @var array<int, mixed> $valores */
        $valores = (array) $request->input($clave, []);

        return collect($valores)
            ->filter(fn ($valor) => $valor !== null && $valor !== '')
            ->map(fn ($valor) => (int) $valor)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Campañas de los clientes ya elegidos (ADR 0015: la campaña es del
     * cliente, no hay campaña global) — `DB::table` directo sobre
     * `cpn_campanias`, mismo criterio que `ContratosController::campaniasParaFormulario()`
     * (ADR 0003 regla 3, `Campania` es de otro módulo). Trae `razon_social`
     * (join, mismo criterio que `campaniasParaFiltro()` de ese controlador):
     * con más de un cliente elegido, dos campañas del mismo `codigo`
     * "2025-2026" (una por cliente, nace así de la migración de la tarea 69)
     * son indistinguibles en la lista sin el nombre del cliente al lado.
     *
     * @param  list<int>  $clienteIds
     * @return Collection<int, \stdClass>
     */
    private function campaniasDe(array $clienteIds): Collection
    {
        if ($clienteIds === []) {
            return collect();
        }

        return DB::table('cpn_campanias')
            ->join('com_clientes', 'com_clientes.id', '=', 'cpn_campanias.cliente_id')
            ->whereIn('cpn_campanias.cliente_id', $clienteIds)
            ->whereNull('cpn_campanias.deleted_at')
            ->orderBy('com_clientes.razon_social')
            ->orderBy('cpn_campanias.codigo')
            ->get(['cpn_campanias.id', 'cpn_campanias.codigo', 'cpn_campanias.cliente_id', 'com_clientes.razon_social']);
    }

    /**
     * @param  list<int>  $clienteIds
     * @param  list<int>  $cultivoIds
     */
    private function filtrosDesde(Request $request, array $clienteIds, array $cultivoIds): FiltrosInformeAvanceContratos
    {
        return new FiltrosInformeAvanceContratos(
            clienteIds: $clienteIds,
            cultivoIds: $cultivoIds,
            campaniaIds: $this->idsDe($request, 'campania_ids'),
            fechaDesde: $request->string('fecha_desde')->value() ?: null,
            fechaHasta: $request->string('fecha_hasta')->value() ?: null,
            estado: EstadoContrato::tryFrom((string) $request->string('estado')),
            saldo: SaldoContrato::tryFrom((string) $request->string('saldo')),
            incluirDeshabilitados: $request->boolean('incluir_deshabilitados'),
        );
    }
}
