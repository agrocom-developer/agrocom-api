<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Dominio\Macrorregion;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Negociacion\Contratos\LecturaNegociacionPanel;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Contratos\LecturaCuentas;
use App\Dominios\Solicitudes\Aplicacion\CancelarSolicitud;
use App\Dominios\Solicitudes\Contratos\LecturaContextoSolicitudes;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Dominio\Excepciones\TransicionSolicitudNoPermitida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Zona;
use App\Dominios\Solicitudes\Infraestructura\Http\Requests\CancelarSolicitudRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Panel de solicitudes de servicio: `GET /panel/solicitudes` (cola, con filtros
 * por estado, región y zona), `GET /panel/solicitudes/{solicitud}` (ficha con el
 * trato, la ejecución y el historial), la foto de un avance y
 * `POST /panel/solicitudes/{solicitud}/cancelar` (la intervención del
 * administrador: cancela con motivo). El cliente las crea desde la app; el panel
 * las consulta e interviene, nunca las edita.
 *
 * Permisos verificados DENTRO del controlador contra el ROL ACTIVO (invariante
 * 10): `solicitudes.solicitud.ver` para la cola y la ficha,
 * `solicitudes.solicitud.ver_seguimiento` para el chat, las fotos y la
 * ubicación del piloto (son datos de las partes) y `.cancelar` para intervenir.
 */
final class SolicitudesController
{
    private const PERMISO_VER = 'solicitudes.solicitud.ver';

    private const PERMISO_SEGUIMIENTO = 'solicitudes.solicitud.ver_seguimiento';

    private const PERMISO_CANCELAR = 'solicitudes.solicitud.cancelar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, LecturaNegociacionPanel $negociacion): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = TextoDeFiltro::de($request, 'q');
        $estado = EstadoSolicitud::tryFrom(TextoDeFiltro::de($request, 'estado'));
        $region = Macrorregion::tryFrom(TextoDeFiltro::de($request, 'region'));
        $zonaId = ctype_digit(TextoDeFiltro::de($request, 'zona')) ? (int) TextoDeFiltro::de($request, 'zona') : null;

        $solicitudes = SolicitudServicio::query()
            ->with(['terreno:id,nombre,zona_id', 'terreno.zona:id,nombre,macrorregion', 'cliente:id,razon_social'])
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado->value))
            ->when($region !== null, fn ($consulta) => $consulta->whereHas('terreno.zona', fn ($zona) => $zona->where('macrorregion', $region->value)))
            ->when($zonaId !== null, fn ($consulta) => $consulta->whereHas('terreno', fn ($terreno) => $terreno->where('zona_id', $zonaId)))
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
            'ofertasVigentes' => $negociacion->ofertasVigentesPorSolicitud($solicitudes->getCollection()->pluck('id')->all()),
            'filtros' => ['q' => $busqueda, 'estado' => $estado?->value, 'region' => $region?->value, 'zona' => $zonaId],
            'estadosFiltro' => EstadoSolicitud::cases(),
            'regionesFiltro' => Macrorregion::cases(),
            'zonasFiltro' => Zona::query()->orderBy('nombre')->pluck('nombre', 'id')->all(),
        ]);
    }

    public function show(
        Request $request,
        SolicitudServicio $solicitud,
        LecturaCuentas $cuentas,
        LecturaContextoSolicitudes $contexto,
        LecturaNegociacionPanel $negociacion,
        LecturaProveedores $proveedores,
    ): View {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $solicitud->load(['terreno.zona', 'cliente', 'historial']);
        $cascara = $this->autorizacion->cascara($request);
        $zona = $cascara['zonaHoraria'] ?? null;
        $puedeVerSeguimiento = $this->autorizacion->tienePermiso($request, self::PERMISO_SEGUIMIENTO);

        $ofertas = $negociacion->ofertas($solicitud->id);
        $nombresProveedor = [];
        foreach (array_unique(array_column($ofertas, 'proveedor_id')) as $proveedorId) {
            $nombresProveedor[$proveedorId] = $proveedores->proveedor($proveedorId)->razonSocial ?? "#{$proveedorId}";
        }

        $datos = [
            ...$cascara,
            'solicitud' => $solicitud,
            'puedeCancelar' => $this->autorizacion->tienePermiso($request, self::PERMISO_CANCELAR) && $solicitud->estado->admiteCancelacion(),
            'puedeVerProveedor' => $this->autorizacion->tienePermiso($request, 'proveedores.proveedor.ver'),
            'puedeVerCliente' => $this->autorizacion->tienePermiso($request, 'solicitudes.cliente.ver'),
            'lineaDeTiempo' => $this->lineaDeTiempo($solicitud, $cuentas, $zona),
            'contexto' => $contexto->porIds([$solicitud->id])[$solicitud->id] ?? null,
            'negociaciones' => $this->negociaciones($ofertas, $nombresProveedor, $zona),
            'nombreProveedor' => $solicitud->proveedor_id === null ? null : ($nombresProveedor[$solicitud->proveedor_id] ?? $proveedores->proveedor($solicitud->proveedor_id)->razonSocial ?? "#{$solicitud->proveedor_id}"),
            'puedeVerSeguimiento' => $puedeVerSeguimiento,
            'avances' => [],
            'evidencias' => [],
            'mensajes' => [],
            'ubicacion' => null,
        ];

        if ($puedeVerSeguimiento) {
            $chat = $negociacion->mensajes($solicitud->id);
            $autores = $cuentas->porIds(array_values(array_unique(array_column($chat, 'autor_usuario_id'))));
            $datos['avances'] = array_map(fn (array $a): array => [...$a, 'registrado_en' => $this->fechaHora($a['registrado_en'], $zona)], $negociacion->avances($solicitud->id));
            $datos['evidencias'] = $negociacion->evidencias($solicitud->id);
            $datos['mensajes'] = array_map(fn (array $m): array => [
                ...$m,
                'autor' => $autores[$m['autor_usuario_id']]->nombre ?? "#{$m['autor_usuario_id']}",
                'creado_en' => $this->fechaHora($m['creado_en'], $zona),
            ], $chat);
            $ubicacion = $negociacion->ubicacion($solicitud->id);
            $datos['ubicacion'] = $ubicacion === null ? null : [...$ubicacion, 'capturado_en' => $this->fechaHora($ubicacion['capturado_en'], $zona)];
        }

        return view('solicitudes::pages.solicitudes.show', $datos);
    }

    /** La foto de un servicio, desde el disco privado: nunca una URL pública. */
    public function evidencia(Request $request, SolicitudServicio $solicitud, int $evidencia, LecturaNegociacionPanel $negociacion): BinaryFileResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_SEGUIMIENTO), 403);

        $pertenece = in_array($evidencia, array_column($negociacion->evidencias($solicitud->id), 'id'), true);
        $ruta = $pertenece ? $negociacion->rutaDeEvidencia($evidencia) : null;

        abort_if($ruta === null, 404);

        return response()->file($ruta, ['Cache-Control' => 'private, max-age=300']);
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
     * Las ofertas, una negociación por proveedor con sus rondas en orden.
     *
     * @param  list<array<string, mixed>>  $ofertas
     * @param  array<int, string>  $nombres
     * @return list<array{proveedor_id: int, proveedor: string, rondas: list<array<string, mixed>>}>
     */
    private function negociaciones(array $ofertas, array $nombres, ?string $zona): array
    {
        $porProveedor = [];

        foreach ($ofertas as $oferta) {
            $porProveedor[$oferta['proveedor_id']][] = [
                ...$oferta,
                'vence_en' => $this->fechaHora($oferta['vence_en'], $zona),
                'respondida_en' => $oferta['respondida_en'] === null ? null : $this->fechaHora($oferta['respondida_en'], $zona),
            ];
        }

        $negociaciones = [];
        foreach ($porProveedor as $proveedorId => $rondas) {
            $negociaciones[] = ['proveedor_id' => $proveedorId, 'proveedor' => $nombres[$proveedorId] ?? "#{$proveedorId}", 'rondas' => $rondas];
        }

        return $negociaciones;
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

    private function fechaHora(string $iso, ?string $zonaHoraria): string
    {
        return Carbon::parse($iso)->timezone($zonaHoraria ?: config('app.timezone'))->format('d/m/Y H:i');
    }
}
