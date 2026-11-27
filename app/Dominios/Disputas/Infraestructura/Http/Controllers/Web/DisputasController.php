<?php

namespace App\Dominios\Disputas\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Disputas\Aplicacion\AgregarMensajeDeDisputa;
use App\Dominios\Disputas\Aplicacion\ResolverDisputa;
use App\Dominios\Disputas\Aplicacion\TomarDisputa;
use App\Dominios\Disputas\Dominio\EstadoDisputa;
use App\Dominios\Disputas\Dominio\Excepciones\DisputaInvalida;
use App\Dominios\Disputas\Dominio\ResultadoDisputa;
use App\Dominios\Disputas\Infraestructura\Eloquent\Disputa;
use App\Dominios\Disputas\Infraestructura\Eloquent\MensajeDeDisputa;
use App\Dominios\Disputas\Infraestructura\Eloquent\ResolucionDeDisputa;
use App\Dominios\Disputas\Infraestructura\Http\Requests\MensajeDeDisputaRequest;
use App\Dominios\Disputas\Infraestructura\Http\Requests\ResolverDisputaRequest;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Solicitudes\Contratos\LecturaServicios;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Panel de disputas (ADR 0033 y 0039): `GET /panel/disputas` (la cola, las que
 * esperan primero), `GET /panel/disputas/{disputa}` (la ficha con la conversación y
 * las resoluciones), `POST .../tomar` (soporte la toma), `POST .../mensajes`
 * (soporte escribe o pide información) y `POST .../resolver` (un administrador
 * fija resultado, reembolso y motivo). Los reembolsos se liquidan entre las partes
 * fuera de la aplicación (ADR 0034).
 *
 * Gateada por `disputas.disputa.ver` (más `.atender` y `.resolver` para las
 * acciones), verificados DENTRO del controlador contra el ROL ACTIVO (invariante 10).
 */
final class DisputasController
{
    private const PERMISO_VER = 'disputas.disputa.ver';

    private const PERMISO_ATENDER = 'disputas.disputa.atender';

    private const PERMISO_RESOLVER = 'disputas.disputa.resolver';

    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly LecturaServicios $servicios,
        private readonly LecturaProveedores $proveedores,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $estado = EstadoDisputa::tryFrom(TextoDeFiltro::de($request, 'estado'));

        $busqueda = TextoDeFiltro::de($request, 'q');

        $disputas = Disputa::query()
            ->when($busqueda !== '', function ($consulta) use ($busqueda): void {
                BusquedaTexto::aplicar($consulta, ['detalle'], $busqueda);
            })
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado->value))
            ->orderByRaw("CASE estado WHEN 'abierta' THEN 0 WHEN 'en_revision' THEN 1 WHEN 'resuelta' THEN 2 ELSE 3 END")
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('disputas::pages.disputas.index', [
            ...$this->autorizacion->cascara($request),
            'disputas' => $disputas,
            'nombres' => $this->nombresDeProveedores($disputas->getCollection()->all()),
            'filtros' => ['q' => $busqueda, 'estado' => $estado?->value],
            'estadosFiltro' => EstadoDisputa::cases(),
        ]);
    }

    public function show(Request $request, Disputa $disputa): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $disputa->load(['mensajes', 'resoluciones']);
        $cascara = $this->autorizacion->cascara($request);
        $zona = (string) ($cascara['zonaHoraria'] ?? '');
        $hora = fn (?Carbon $fecha): string => $fecha === null ? '—' : $fecha->copy()->timezone($zona ?: config('app.timezone'))->format('d/m H:i');

        $servicio = $this->servicios->datos($disputa->solicitud_id);

        $conversacion = $disputa->mensajes->map(fn (MensajeDeDisputa $mensaje): array => [
            'title' => $mensaje->texto,
            'meta' => implode(' · ', [__('disputas.panel.autor_'.$mensaje->autor_rol), $mensaje->tipo === 'mensaje' ? null : __('disputas.panel.tipo_'.$mensaje->tipo), $hora($mensaje->created_at)]),
            'tone' => match ($mensaje->autor_rol) {
                'soporte' => 'info',
                'cliente' => 'primary-2',
                default => 'warning',
            },
        ])->filter(fn (array $item): bool => $item['title'] !== '')->values()->all();

        $resoluciones = $disputa->resoluciones->map(fn (ResolucionDeDisputa $resolucion): array => [
            'title' => __('disputas.panel.resolucion_titulo', ['ronda' => $resolucion->ronda, 'resultado' => mb_strtolower(__('disputas.resultado.'.$resolucion->resultado->value))]),
            'meta' => implode(' · ', array_filter([
                __('disputas.panel.resolucion_reembolso', ['monto' => number_format((float) $resolucion->monto_reembolso, 2, ',', '.')]),
                (float) $resolucion->comision_ajuste > 0 ? __('disputas.panel.resolucion_comision', ['monto' => number_format((float) $resolucion->comision_ajuste, 2, ',', '.')]) : null,
                $resolucion->derivada_a_seguro ? __('disputas.panel.resolucion_seguro') : null,
                $resolucion->mala_fe_cliente ? __('disputas.panel.resolucion_mala_fe') : null,
                $resolucion->motivo,
                $hora($resolucion->resuelta_en),
            ])),
            'tone' => 'success',
        ])->values()->all();

        $viva = $disputa->estado->estaViva();

        return view('disputas::pages.disputas.show', [
            ...$cascara,
            'disputa' => $disputa,
            'servicio' => $servicio,
            'proveedor' => $disputa->proveedor_id === null ? null : $this->proveedores->proveedor($disputa->proveedor_id),
            'conversacion' => $conversacion,
            'resoluciones' => $resoluciones,
            'respondeAntesDe' => $hora($disputa->responde_antes_de),
            'puedeTomar' => $disputa->estado === EstadoDisputa::Abierta && $this->autorizacion->tienePermiso($request, self::PERMISO_ATENDER),
            'puedeMensajear' => $viva && $this->autorizacion->tienePermiso($request, self::PERMISO_ATENDER),
            'puedeResolver' => $viva && $this->autorizacion->tienePermiso($request, self::PERMISO_RESOLVER),
            'resultados' => ResultadoDisputa::cases(),
        ]);
    }

    public function tomar(Request $request, int $disputa, TomarDisputa $tomar): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ATENDER), 403);

        try {
            $tomar->ejecutar($this->actorId($request), $disputa);
        } catch (DisputaInvalida $excepcion) {
            return redirect()->route('panel.disputas.show', $disputa)->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()->route('panel.disputas.show', $disputa)->with('estado', __('disputas.panel.tomada'));
    }

    public function mensaje(MensajeDeDisputaRequest $request, int $disputa, AgregarMensajeDeDisputa $agregar): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ATENDER), 403);

        try {
            $agregar->comoSoporte($this->actorId($request), $disputa, (string) $request->validated('texto'), $request->boolean('pide_informacion'));
        } catch (DisputaInvalida $excepcion) {
            return redirect()->route('panel.disputas.show', $disputa)->withInput()->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()->route('panel.disputas.show', $disputa)->with('estado', __('disputas.panel.mensaje_enviado'));
    }

    public function resolver(ResolverDisputaRequest $request, int $disputa, ResolverDisputa $resolver): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_RESOLVER), 403);

        try {
            $resolver->ejecutar(
                $this->actorId($request),
                $disputa,
                ResultadoDisputa::from((string) $request->validated('resultado')),
                (string) $request->validated('motivo'),
                str_replace(',', '.', (string) $request->validated('monto_reembolso', '0')),
                $request->boolean('derivada_a_seguro'),
                $request->boolean('mala_fe_cliente'),
            );
        } catch (DisputaInvalida $excepcion) {
            return redirect()->route('panel.disputas.show', $disputa)->withInput()->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()->route('panel.disputas.show', $disputa)->with('estado', __('disputas.panel.resuelta'));
    }

    private function actorId(Request $request): int
    {
        return (int) $request->user('interno')->getAuthIdentifier();
    }

    /**
     * Nombre del proveedor de cada disputa de la página, por el contrato de `Proveedores`.
     *
     * @param  list<Disputa>  $disputas
     * @return array<int, string> por id de disputa.
     */
    private function nombresDeProveedores(array $disputas): array
    {
        $nombres = [];

        foreach ($disputas as $disputa) {
            $nombres[$disputa->id] = $disputa->proveedor_id === null ? '—' : ($this->proveedores->proveedor($disputa->proveedor_id)->razonSocial ?? "#{$disputa->proveedor_id}");
        }

        return $nombres;
    }
}
