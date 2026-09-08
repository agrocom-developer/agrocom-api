<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Aplicacion\ActualizarContrato;
use App\Dominios\Comercial\Aplicacion\CambiarEstadoContrato;
use App\Dominios\Comercial\Aplicacion\CrearContrato;
use App\Dominios\Comercial\Aplicacion\ListarContratos;
use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Dominio\Excepciones\ActivacionContratoNoDisponible;
use App\Dominios\Comercial\Dominio\Excepciones\CampaniaCerrada;
use App\Dominios\Comercial\Dominio\Excepciones\CampaniaDeOtroCliente;
use App\Dominios\Comercial\Dominio\Excepciones\TransicionContratoNoPermitida;
use App\Dominios\Comercial\Dominio\Excepciones\VentanasContratoSolapadas;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Http\Requests\ActualizarContratoRequest;
use App\Dominios\Comercial\Infraestructura\Http\Requests\CambiarEstadoContratoRequest;
use App\Dominios\Comercial\Infraestructura\Http\Requests\CrearContratoRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST/PUT /panel/contratos*` (HU-23, tarea 34): alta y mantenimiento de
 * contratos con sus ventanas de aplicación. Mismo molde que
 * `ClientesController` (HU-22, tarea 33) — ver `prompts/33-abm-clientes.md`
 * para el detalle de las decisiones que este controlador reutiliza.
 *
 * Sin `destroy`: la baja de un contrato es una transición de estado
 * (`cambiarEstado` hacia `cancelado`), no un soft delete fuera de la máquina
 * de estados — invariante 7 de CLAUDE.md. Cuatro permisos de grano fino
 * (`comercial.contrato.ver`/`.crear`/`.editar`/`.cambiar_estado`),
 * verificados DENTRO del controlador contra el ROL ACTIVO vía
 * {@see AutorizacionPanelWeb}. Ninguna regla de negocio acá: los casos de
 * uso de `Aplicacion/` hacen el trabajo, incluido el cálculo de
 * `monto_total` y el upsert de contrato+ventanas en una sola transacción.
 *
 * `campaniasParaFormulario()`/`etiquetasCampania()` leen `cpn_campanias` con
 * `DB::table` directo (ADR 0003 regla 3, mismo criterio que
 * `clientesActivos()` con el modelo Eloquent — acá no aplica porque
 * `Campania` es de otro módulo), sin importar su modelo Eloquent.
 */
final class ContratosController
{
    private const PERMISO_VER = 'comercial.contrato.ver';

    private const PERMISO_CREAR = 'comercial.contrato.crear';

    private const PERMISO_EDITAR = 'comercial.contrato.editar';

    private const PERMISO_CAMBIAR_ESTADO = 'comercial.contrato.cambiar_estado';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarContratos $listarContratos): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();
        $campaniaId = $request->integer('campania_id') ?: null;

        $contratos = $listarContratos->ejecutar($busqueda !== '' ? $busqueda : null, $campaniaId);

        return view('comercial::pages.contratos.index', [
            ...$this->autorizacion->cascara($request),
            'contratos' => $contratos,
            'campaniasDisponibles' => $this->campaniasParaFiltro(),
            'filtros' => ['q' => $busqueda, 'campania_id' => $campaniaId],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('comercial::pages.contratos.create', [
            ...$this->autorizacion->cascara($request),
            'clientesDisponibles' => $this->clientesActivos(),
            'campaniasDisponibles' => $this->campaniasParaFormulario(),
        ]);
    }

    public function store(CrearContratoRequest $request, CrearContrato $crearContrato): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        /** @var list<array<string, mixed>> $ventanasCrudas */
        $ventanasCrudas = $datos['ventanas'];
        unset($datos['ventanas']);

        try {
            $crearContrato->ejecutar(
                $this->normalizarDatosContrato($datos),
                array_map($this->normalizarVentanaNueva(...), $ventanasCrudas),
            );
        } catch (VentanasContratoSolapadas $excepcion) {
            return redirect()
                ->route('panel.contratos.create')
                ->withInput()
                ->withErrors(['ventanas' => $excepcion->getMessage()]);
        } catch (CampaniaDeOtroCliente|CampaniaCerrada $excepcion) {
            return redirect()
                ->route('panel.contratos.create')
                ->withInput()
                ->withErrors(['campania_id' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.contratos.index')
            ->with('estado', __('comercial.contratos.creado'));
    }

    public function edit(Request $request, Contrato $contrato): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('comercial::pages.contratos.edit', [
            ...$this->autorizacion->cascara($request),
            'contrato' => $contrato->load('ventanas'),
            'clientesDisponibles' => $this->clientesActivos(),
            'campaniasDisponibles' => $this->campaniasParaFormulario(),
        ]);
    }

    public function update(ActualizarContratoRequest $request, Contrato $contrato, ActualizarContrato $actualizarContrato): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        /** @var list<array<string, mixed>> $ventanasCrudas */
        $ventanasCrudas = $datos['ventanas'];
        unset($datos['ventanas']);

        try {
            $actualizarContrato->ejecutar(
                $contrato,
                $this->normalizarDatosContrato($datos),
                array_map($this->normalizarVentanaExistente(...), $ventanasCrudas),
            );
        } catch (VentanasContratoSolapadas $excepcion) {
            return redirect()
                ->route('panel.contratos.edit', $contrato)
                ->withInput()
                ->withErrors(['ventanas' => $excepcion->getMessage()]);
        } catch (CampaniaDeOtroCliente|CampaniaCerrada $excepcion) {
            return redirect()
                ->route('panel.contratos.edit', $contrato)
                ->withInput()
                ->withErrors(['campania_id' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.contratos.index')
            ->with('estado', __('comercial.contratos.actualizado'));
    }

    public function cambiarEstado(CambiarEstadoContratoRequest $request, Contrato $contrato, CambiarEstadoContrato $cambiarEstadoContrato): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CAMBIAR_ESTADO), 403);

        $hacia = EstadoContrato::from((string) $request->validated('estado'));

        try {
            $cambiarEstadoContrato->ejecutar($contrato, $hacia);
        } catch (TransicionContratoNoPermitida|ActivacionContratoNoDisponible $excepcion) {
            return redirect()
                ->route('panel.contratos.index')
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.contratos.index')
            ->with('estado', __('comercial.contratos.estado_cambiado'));
    }

    /** @return Collection<int, string> */
    private function clientesActivos(): Collection
    {
        return Cliente::query()->orderBy('razon_social')->pluck('razon_social', 'id');
    }

    /**
     * Todas las campañas del sistema, con su `cliente_id` — el formulario
     * (`contratos-form.js`) filtra en cliente cuáles mostrar según el
     * cliente elegido, mismo patrón que rubro/subrubro en gastos.
     *
     * `stdClass`, no un shape tipado: es lo que devuelve el query builder
     * plano (ADR 0003 regla 3), no el modelo Eloquent `Campania` de otro
     * módulo. Trae `id`, `codigo` (string) y `cliente_id` (int).
     *
     * @return Collection<int, \stdClass>
     */
    private function campaniasParaFormulario(): Collection
    {
        return DB::table('cpn_campanias')
            ->whereNull('deleted_at')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'cliente_id']);
    }

    /** @return Collection<int, non-falsy-string> id => "código — cliente", para el filtro del listado. */
    private function campaniasParaFiltro(): Collection
    {
        return DB::table('cpn_campanias')
            ->join('com_clientes', 'com_clientes.id', '=', 'cpn_campanias.cliente_id')
            ->whereNull('cpn_campanias.deleted_at')
            ->orderBy('com_clientes.razon_social')
            ->orderBy('cpn_campanias.codigo')
            ->get(['cpn_campanias.id', 'cpn_campanias.codigo', 'com_clientes.razon_social'])
            ->mapWithKeys(fn (object $fila): array => [(int) $fila->id => sprintf('%s — %s', $fila->codigo, $fila->razon_social)]);
    }

    /**
     * @param  array<string, mixed>  $datos  validados, sin `ventanas`
     * @return array<string, mixed> listo para `Aplicacion/CrearContrato`/`ActualizarContrato`
     */
    private function normalizarDatosContrato(array $datos): array
    {
        return [
            'cliente_id' => (int) $datos['cliente_id'],
            'campania_id' => (int) $datos['campania_id'],
            'hectareas_contratadas' => (string) $datos['hectareas_contratadas'],
            'aplicaciones_previstas' => (int) $datos['aplicaciones_previstas'],
            'precio_ha' => (string) $datos['precio_ha'],
            'adelanto_monto' => $this->cadenaONull($datos['adelanto_monto'] ?? null),
            'adelanto_pct' => $this->cadenaONull($datos['adelanto_pct'] ?? null),
            'fecha_inicio' => (string) $datos['fecha_inicio'],
            'fecha_fin' => $this->cadenaONull($datos['fecha_fin'] ?? null),
            'viento_max_kmh' => $this->cadenaONull($datos['viento_max_kmh'] ?? null),
            'temperatura_max_c' => $this->cadenaONull($datos['temperatura_max_c'] ?? null),
            'humedad_min_pct' => $this->cadenaONull($datos['humedad_min_pct'] ?? null),
            'humedad_max_pct' => $this->cadenaONull($datos['humedad_max_pct'] ?? null),
            'velocidad_max_kmh' => $this->cadenaONull($datos['velocidad_max_kmh'] ?? null),
            'umbral_reporte_avance_ha' => $this->cadenaONull($datos['umbral_reporte_avance_ha'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $ventana
     * @return array{hora_inicio: string, hora_fin: string}
     */
    private function normalizarVentanaNueva(array $ventana): array
    {
        return [
            'hora_inicio' => (string) $ventana['hora_inicio'],
            'hora_fin' => (string) $ventana['hora_fin'],
        ];
    }

    /**
     * @param  array<string, mixed>  $ventana
     * @return array{id: int|null, hora_inicio: string, hora_fin: string}
     */
    private function normalizarVentanaExistente(array $ventana): array
    {
        return [
            'id' => isset($ventana['id']) && $ventana['id'] !== '' ? (int) $ventana['id'] : null,
            ...$this->normalizarVentanaNueva($ventana),
        ];
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
