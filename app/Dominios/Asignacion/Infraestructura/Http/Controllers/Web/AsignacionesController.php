<?php

namespace App\Dominios\Asignacion\Infraestructura\Http\Controllers\Web;

use App\Dominios\Asignacion\Aplicacion\ForzarAsignacion;
use App\Dominios\Asignacion\Dominio\Excepciones\AsignacionInvalida;
use App\Dominios\Asignacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Asignacion\Infraestructura\Eloquent\Ronda;
use App\Dominios\Asignacion\Infraestructura\Http\Requests\ForzarAsignacionRequest;
use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Flota\Contratos\LecturaEquipos;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Solicitudes\Contratos\LecturaSolicitudesParaAsignar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Panel de asignaciones (ADR 0030 y 0038): `GET /panel/asignaciones` (la cola de
 * solicitudes que esperan o acaban de recibir proveedor), `GET
 * /panel/asignaciones/{solicitud}` (rondas y ofertas de una solicitud) y `POST
 * /panel/asignaciones/{solicitud}/forzar` (asignar o reasignar a mano, con
 * motivo). El motor genera las ofertas; el panel las consulta y, con el permiso
 * de forzar, decide donde el motor no pudo.
 *
 * Gateada por `asignacion.asignacion.ver` (y `.forzar` para la acción),
 * verificados DENTRO del controlador contra el ROL ACTIVO (invariante 10).
 */
final class AsignacionesController
{
    private const PERMISO_VER = 'asignacion.asignacion.ver';

    private const PERMISO_FORZAR = 'asignacion.asignacion.forzar';

    /** Estados desde los que un administrador puede asignar o reasignar. */
    private const FORZABLES = ['en_asignacion', 'sin_proveedor', 'asignada', 'en_camino'];

    private const ESTADOS_COLA = ['publicada', 'en_asignacion', 'sin_proveedor', 'asignada', 'en_camino'];

    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly LecturaSolicitudesParaAsignar $solicitudes,
        private readonly LecturaProveedores $proveedores,
        private readonly LecturaEquipos $equipos,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $estado = TextoDeFiltro::de($request, 'estado');
        $estado = in_array($estado, self::ESTADOS_COLA, true) ? $estado : null;

        $cola = $this->solicitudes->colaDeAsignacion($estado, 100);
        $ids = array_map(fn ($datos): int => $datos->id, $cola);

        $vivas = $ids === [] ? [] : Oferta::query()
            ->whereIn('solicitud_id', $ids)
            ->where('estado', 'ofrecida')
            ->selectRaw('solicitud_id, count(*) as total')
            ->groupBy('solicitud_id')
            ->pluck('total', 'solicitud_id')
            ->all();

        $rondas = $ids === [] ? [] : Ronda::query()
            ->whereIn('solicitud_id', $ids)
            ->selectRaw('solicitud_id, max(ciclo) as ciclo')
            ->groupBy('solicitud_id')
            ->pluck('ciclo', 'solicitud_id')
            ->all();

        return view('asignacion::pages.asignaciones.index', [
            ...$this->autorizacion->cascara($request),
            'cola' => $cola,
            'ofertasVivas' => $vivas,
            'ciclos' => $rondas,
            'filtros' => ['estado' => $estado],
            'estadosFiltro' => self::ESTADOS_COLA,
        ]);
    }

    public function show(Request $request, int $solicitud): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $datos = $this->solicitudes->datos($solicitud) ?? abort(404);
        $cascara = $this->autorizacion->cascara($request);
        $zona = $cascara['zonaHoraria'] ?? null;

        $ofertas = Oferta::query()->where('solicitud_id', $solicitud)->orderBy('ciclo')->orderBy('ronda')->orderBy('id')->get();
        $rondas = Ronda::query()->where('solicitud_id', $solicitud)->orderBy('ciclo')->orderBy('ronda')->get();

        $nombres = [];
        foreach ($ofertas->pluck('proveedor_id')->unique() as $proveedorId) {
            $nombres[$proveedorId] = $this->proveedores->proveedor((int) $proveedorId)->razonSocial ?? "#{$proveedorId}";
        }

        $hora = fn (?Carbon $fecha): string => $fecha === null ? '—' : $fecha->copy()->timezone($zona ?: config('app.timezone'))->format('d/m H:i');

        $lineaDeTiempo = $ofertas->map(fn (Oferta $oferta): array => [
            'title' => __('asignacion.panel.oferta_titulo', ['proveedor' => $nombres[$oferta->proveedor_id], 'estado' => mb_strtolower(__('asignacion.estado_oferta.'.$oferta->estado->value))]),
            'meta' => implode(' · ', array_filter([
                __('asignacion.panel.oferta_ronda', ['ciclo' => $oferta->ciclo, 'ronda' => $oferta->ronda, 'grupo' => mb_strtolower(__('asignacion.grupo.'.$oferta->grupo->value))]),
                $oferta->origen->value === 'administrador' ? __('asignacion.panel.oferta_manual') : null,
                __('asignacion.panel.oferta_ofrecida', ['hora' => $hora($oferta->ofrecida_en)]),
                $oferta->estado->esViva() ? __('asignacion.panel.oferta_vence', ['hora' => $hora($oferta->vence_en)]) : null,
                $oferta->motivo_rechazo,
                $oferta->motivo_retiro === null ? null : __('asignacion.motivo_retiro.'.$oferta->motivo_retiro->value),
                $oferta->distancia_km === null ? null : __('asignacion.panel.oferta_distancia', ['km' => number_format((float) $oferta->distancia_km, 1, ',', '.')]),
            ])),
            'tone' => $oferta->estado->tono(),
        ])->values()->all();

        $puedeForzar = $this->autorizacion->tienePermiso($request, self::PERMISO_FORZAR) && in_array($datos->estado, self::FORZABLES, true);

        return view('asignacion::pages.asignaciones.show', [
            ...$cascara,
            'solicitud' => $datos,
            'proveedorAsignado' => $datos->proveedorId === null ? null : $this->proveedores->proveedor($datos->proveedorId),
            'rondas' => $rondas,
            'ofertasVivas' => $ofertas->filter(fn (Oferta $oferta): bool => $oferta->estado->esViva())->count(),
            'lineaDeTiempo' => $lineaDeTiempo,
            'puedeForzar' => $puedeForzar,
            'opcionesProveedor' => $puedeForzar ? $this->opcionesDeProveedor() : [],
        ]);
    }

    public function forzar(ForzarAsignacionRequest $request, int $solicitud, ForzarAsignacion $forzar): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_FORZAR), 403);

        try {
            $forzar->ejecutar(
                (int) $request->user('interno')->getAuthIdentifier(),
                $solicitud,
                (int) $request->validated('proveedor_id'),
                $request->filled('equipo_id') ? (int) $request->validated('equipo_id') : null,
                (string) $request->validated('motivo'),
            );
        } catch (AsignacionInvalida $excepcion) {
            return redirect()
                ->route('panel.asignaciones.show', $solicitud)
                ->withInput()
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.asignaciones.show', $solicitud)
            ->with('estado', __('asignacion.panel.forzada'));
    }

    /**
     * Los proveedores a los que se puede asignar: aprobados y con un equipo apto
     * hoy, los propios primero (ADR 0030).
     *
     * @return array<int, string> id → «razón social (tipo)».
     */
    private function opcionesDeProveedor(): array
    {
        $opciones = [];

        foreach (['propio', 'tercero'] as $grupo) {
            foreach ($this->proveedores->aprobadosDelGrupo($grupo) as $proveedor) {
                if ($this->equipos->tieneEquipoApto($proveedor->id)) {
                    $opciones[$proveedor->id] = "{$proveedor->razonSocial} ({$proveedor->tipo})";
                }
            }
        }

        return $opciones;
    }
}
