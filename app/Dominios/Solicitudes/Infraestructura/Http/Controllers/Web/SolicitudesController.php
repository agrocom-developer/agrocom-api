<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Negociacion\Contratos\LecturaNegociacionParaPanel;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Contratos\LecturaCuentas;
use App\Dominios\Solicitudes\Aplicacion\CancelarSolicitud;
use App\Dominios\Solicitudes\Contratos\LecturaContextoSolicitudes;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Dominio\Excepciones\TransicionSolicitudNoPermitida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use App\Dominios\Solicitudes\Infraestructura\Http\Requests\CancelarSolicitudRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Panel de solicitudes de servicio (ADR 0027): `GET /panel/solicitudes` (cola y
 * seguimiento), `GET /panel/solicitudes/{solicitud}` (ficha con su historial, sus
 * negociaciones, el chat, las fotos y la última ubicación del piloto) y
 * `POST /panel/solicitudes/{solicitud}/cancelar` (soporte cancela con motivo).
 * El cliente las crea desde la app; el panel solo las consulta y las cancela.
 *
 * Gateada por `solicitudes.solicitud.ver` (y `.cancelar` para la acción),
 * verificados DENTRO del controlador contra el ROL ACTIVO (invariante 10).
 */
final class SolicitudesController
{
    private const PERMISO_VER = 'solicitudes.solicitud.ver';

    private const PERMISO_CANCELAR = 'solicitudes.solicitud.cancelar';

    private const PERMISO_INTERVENIR = 'solicitudes.solicitud.intervenir';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = TextoDeFiltro::de($request, 'q');
        $estado = EstadoSolicitud::tryFrom(TextoDeFiltro::de($request, 'estado'));

        $solicitudes = SolicitudServicio::query()
            ->with(['terreno:id,nombre', 'cliente:id,razon_social'])
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado->value))
            ->when($busqueda !== '', function ($consulta) use ($busqueda): void {
                $consulta->where(function ($interna) use ($busqueda): void {
                    BusquedaTexto::aplicar($interna, ['producto_indicado', 'cultivo'], $busqueda);

                    if (ctype_digit(ltrim($busqueda, '#'))) {
                        $interna->orWhere('id', (int) ltrim($busqueda, '#'));
                    }
                });
            })
            ->orderByRaw("CASE estado WHEN 'expirada' THEN 0 WHEN 'abierta' THEN 1 WHEN 'con_ofertas' THEN 2 ELSE 3 END")
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('solicitudes::pages.solicitudes.index', [
            ...$this->autorizacion->cascara($request),
            'solicitudes' => $solicitudes,
            'filtros' => ['q' => $busqueda, 'estado' => $estado?->value],
            'estadosFiltro' => EstadoSolicitud::cases(),
        ]);
    }

    public function show(Request $request, SolicitudServicio $solicitud, LecturaCuentas $cuentas, LecturaContextoSolicitudes $contexto, LecturaNegociacionParaPanel $negociacion): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $solicitud->load(['terreno', 'cliente', 'historial']);
        $cascara = $this->autorizacion->cascara($request);

        return view('solicitudes::pages.solicitudes.show', [
            ...$cascara,
            'solicitud' => $solicitud,
            'puedeCancelar' => $this->autorizacion->tienePermiso($request, self::PERMISO_CANCELAR) && $solicitud->estado->admiteCancelacion(),
            'puedeIntervenir' => $this->autorizacion->tienePermiso($request, self::PERMISO_INTERVENIR) && $solicitud->estado->enMercado(),
            'negocia' => $negociacion->deLaSolicitud($solicitud->id),
            'lineaDeTiempo' => $this->lineaDeTiempo($solicitud, $cuentas, $cascara['zonaHoraria'] ?? null),
            'contexto' => $contexto->porIds([$solicitud->id])[$solicitud->id] ?? null,
        ]);
    }

    public function cancelar(CancelarSolicitudRequest $request, SolicitudServicio $solicitud, CancelarSolicitud $cancelar): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CANCELAR), 403);

        try {
            $cancelar->porAdministrador((int) $request->user('interno')->getAuthIdentifier(), $solicitud->id, (string) $request->validated('motivo'));
        } catch (SolicitudInvalida|TransicionSolicitudNoPermitida $excepcion) {
            return redirect()
                ->route('panel.solicitudes.show', $solicitud)
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.solicitudes.show', $solicitud)
            ->with('estado', __('solicitudes.panel.cancelada'));
    }

    /**
     * El historial inmutable de la solicitud, del primer al último paso, con
     * el autor ya resuelto a nombre.
     *
     * @return list<array{title: string, meta: string, tone: string}>
     */
    private function lineaDeTiempo(SolicitudServicio $solicitud, LecturaCuentas $cuentas, ?string $zonaHoraria): array
    {
        $actores = $cuentas->porIds($solicitud->historial->pluck('actor_usuario_id')->filter()->unique()->values()->all());

        return $solicitud->historial->map(function ($paso) use ($actores, $zonaHoraria): array {
            $autor = $paso->actor_usuario_id === null ? null : ($actores[$paso->actor_usuario_id]->nombre ?? "#{$paso->actor_usuario_id}");
            $partes = [
                Carbon::instance($paso->created_at)->timezone($zonaHoraria ?: config('app.timezone'))->format('d/m/Y H:i'),
                $autor ?? __('solicitudes.origen.'.$paso->origen->value),
            ];

            if ($paso->motivo !== null && $paso->motivo !== '') {
                $partes[] = $paso->motivo;
            }

            return [
                'title' => $paso->estado_anterior === null
                    ? __('solicitudes.panel.historial_creada')
                    : __('solicitudes.panel.historial_cambio', [
                        'de' => __('solicitudes.estado.'.$paso->estado_anterior->value),
                        'a' => __('solicitudes.estado.'.$paso->estado_nuevo->value),
                    ]),
                'meta' => implode(' · ', $partes),
                'tone' => $paso->estado_nuevo->tono(),
            ];
        })->values()->all();
    }
}
