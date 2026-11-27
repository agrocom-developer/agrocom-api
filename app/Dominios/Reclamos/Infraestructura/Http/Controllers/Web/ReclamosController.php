<?php

namespace App\Dominios\Reclamos\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Reclamos\Aplicacion\AgregarMensajeDeReclamo;
use App\Dominios\Reclamos\Aplicacion\ResolverReclamo;
use App\Dominios\Reclamos\Aplicacion\TomarReclamo;
use App\Dominios\Reclamos\Dominio\EstadoReclamo;
use App\Dominios\Reclamos\Dominio\Excepciones\ReclamoInvalido;
use App\Dominios\Reclamos\Dominio\ResultadoReclamo;
use App\Dominios\Reclamos\Infraestructura\Eloquent\MensajeDeReclamo;
use App\Dominios\Reclamos\Infraestructura\Eloquent\Reclamo;
use App\Dominios\Reclamos\Infraestructura\Eloquent\ResolucionDeReclamo;
use App\Dominios\Reclamos\Infraestructura\Http\Requests\MensajeDeReclamoRequest;
use App\Dominios\Reclamos\Infraestructura\Http\Requests\ResolverReclamoRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Solicitudes\Contratos\LecturaServicios;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Panel de reclamos (ADR 0033 y 0039): `GET /panel/reclamos` (la cola, las que
 * esperan primero), `GET /panel/reclamos/{reclamo}` (la ficha con la conversación y
 * las resoluciones), `POST .../tomar` (soporte la toma), `POST .../mensajes`
 * (soporte escribe o pide información) y `POST .../resolver` (un administrador
 * fija resultado, reembolso y motivo). Los reembolsos se liquidan entre las partes
 * fuera de la aplicación (ADR 0034).
 *
 * Gateada por `reclamos.reclamo.ver` (más `.atender` y `.resolver` para las
 * acciones), verificados DENTRO del controlador contra el ROL ACTIVO (invariante 10).
 */
final class ReclamosController
{
    private const PERMISO_VER = 'reclamos.reclamo.ver';

    private const PERMISO_ATENDER = 'reclamos.reclamo.atender';

    private const PERMISO_RESOLVER = 'reclamos.reclamo.resolver';

    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly LecturaServicios $servicios,
        private readonly LecturaProveedores $proveedores,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $estado = EstadoReclamo::tryFrom(TextoDeFiltro::de($request, 'estado'));

        $reclamos = Reclamo::query()
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado->value))
            ->orderByRaw("CASE estado WHEN 'abierta' THEN 0 WHEN 'en_revision' THEN 1 WHEN 'resuelta' THEN 2 ELSE 3 END")
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('reclamos::pages.reclamos.index', [
            ...$this->autorizacion->cascara($request),
            'reclamos' => $reclamos,
            'nombres' => $this->nombresDeProveedores($reclamos->getCollection()->all()),
            'filtros' => ['estado' => $estado?->value],
            'estadosFiltro' => EstadoReclamo::cases(),
        ]);
    }

    public function show(Request $request, Reclamo $reclamo): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $reclamo->load(['mensajes', 'resoluciones']);
        $cascara = $this->autorizacion->cascara($request);
        $zona = (string) ($cascara['zonaHoraria'] ?? '');
        $hora = fn (?Carbon $fecha): string => $fecha === null ? '—' : $fecha->copy()->timezone($zona ?: config('app.timezone'))->format('d/m H:i');

        $servicio = $this->servicios->datos($reclamo->solicitud_id);

        $conversacion = $reclamo->mensajes->map(fn (MensajeDeReclamo $mensaje): array => [
            'title' => $mensaje->texto,
            'meta' => implode(' · ', [__('reclamos.panel.autor_'.$mensaje->autor_rol), $mensaje->tipo === 'mensaje' ? null : __('reclamos.panel.tipo_'.$mensaje->tipo), $hora($mensaje->created_at)]),
            'tone' => match ($mensaje->autor_rol) {
                'soporte' => 'info',
                'cliente' => 'primary-2',
                default => 'warning',
            },
        ])->filter(fn (array $item): bool => $item['title'] !== '')->values()->all();

        $resoluciones = $reclamo->resoluciones->map(fn (ResolucionDeReclamo $resolucion): array => [
            'title' => __('reclamos.panel.resolucion_titulo', ['ronda' => $resolucion->ronda, 'resultado' => mb_strtolower(__('reclamos.resultado.'.$resolucion->resultado->value))]),
            'meta' => implode(' · ', array_filter([
                __('reclamos.panel.resolucion_reembolso', ['monto' => number_format((float) $resolucion->monto_reembolso, 2, ',', '.')]),
                (float) $resolucion->comision_ajuste > 0 ? __('reclamos.panel.resolucion_comision', ['monto' => number_format((float) $resolucion->comision_ajuste, 2, ',', '.')]) : null,
                $resolucion->derivada_a_seguro ? __('reclamos.panel.resolucion_seguro') : null,
                $resolucion->mala_fe_cliente ? __('reclamos.panel.resolucion_mala_fe') : null,
                $resolucion->motivo,
                $hora($resolucion->resuelta_en),
            ])),
            'tone' => 'success',
        ])->values()->all();

        $viva = $reclamo->estado->estaViva();

        return view('reclamos::pages.reclamos.show', [
            ...$cascara,
            'reclamo' => $reclamo,
            'servicio' => $servicio,
            'proveedor' => $reclamo->proveedor_id === null ? null : $this->proveedores->proveedor($reclamo->proveedor_id),
            'conversacion' => $conversacion,
            'resoluciones' => $resoluciones,
            'respondeAntesDe' => $hora($reclamo->responde_antes_de),
            'puedeTomar' => $reclamo->estado === EstadoReclamo::Abierta && $this->autorizacion->tienePermiso($request, self::PERMISO_ATENDER),
            'puedeMensajear' => $viva && $this->autorizacion->tienePermiso($request, self::PERMISO_ATENDER),
            'puedeResolver' => $viva && $this->autorizacion->tienePermiso($request, self::PERMISO_RESOLVER),
            'resultados' => ResultadoReclamo::cases(),
        ]);
    }

    public function tomar(Request $request, int $reclamo, TomarReclamo $tomar): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ATENDER), 403);

        try {
            $tomar->ejecutar($this->actorId($request), $reclamo);
        } catch (ReclamoInvalido $excepcion) {
            return redirect()->route('panel.reclamos.show', $reclamo)->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()->route('panel.reclamos.show', $reclamo)->with('estado', __('reclamos.panel.tomada'));
    }

    public function mensaje(MensajeDeReclamoRequest $request, int $reclamo, AgregarMensajeDeReclamo $agregar): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ATENDER), 403);

        try {
            $agregar->comoSoporte($this->actorId($request), $reclamo, (string) $request->validated('texto'), $request->boolean('pide_informacion'));
        } catch (ReclamoInvalido $excepcion) {
            return redirect()->route('panel.reclamos.show', $reclamo)->withInput()->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()->route('panel.reclamos.show', $reclamo)->with('estado', __('reclamos.panel.mensaje_enviado'));
    }

    public function resolver(ResolverReclamoRequest $request, int $reclamo, ResolverReclamo $resolver): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_RESOLVER), 403);

        try {
            $resolver->ejecutar(
                $this->actorId($request),
                $reclamo,
                ResultadoReclamo::from((string) $request->validated('resultado')),
                (string) $request->validated('motivo'),
                str_replace(',', '.', (string) $request->validated('monto_reembolso', '0')),
                $request->boolean('derivada_a_seguro'),
                $request->boolean('mala_fe_cliente'),
            );
        } catch (ReclamoInvalido $excepcion) {
            return redirect()->route('panel.reclamos.show', $reclamo)->withInput()->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()->route('panel.reclamos.show', $reclamo)->with('estado', __('reclamos.panel.resuelta'));
    }

    private function actorId(Request $request): int
    {
        return (int) $request->user('interno')->getAuthIdentifier();
    }

    /**
     * Nombre del proveedor de cada reclamo de la página, por el contrato de `Proveedores`.
     *
     * @param  list<Reclamo>  $reclamos
     * @return array<int, string> por id de reclamo.
     */
    private function nombresDeProveedores(array $reclamos): array
    {
        $nombres = [];

        foreach ($reclamos as $reclamo) {
            $nombres[$reclamo->id] = $reclamo->proveedor_id === null ? '—' : ($this->proveedores->proveedor($reclamo->proveedor_id)->razonSocial ?? "#{$reclamo->proveedor_id}");
        }

        return $nombres;
    }
}
