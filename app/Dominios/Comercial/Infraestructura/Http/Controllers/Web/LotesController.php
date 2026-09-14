<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Aplicacion\ActualizarLote;
use App\Dominios\Comercial\Aplicacion\CrearLote;
use App\Dominios\Comercial\Aplicacion\EliminarLote;
use App\Dominios\Comercial\Aplicacion\ListarLotes;
use App\Dominios\Comercial\Aplicacion\Lote\GuardadoLote;
use App\Dominios\Comercial\Aplicacion\ResolverProveedorMapa;
use App\Dominios\Comercial\Dominio\Excepciones\LoteConHistorialAsociado;
use App\Dominios\Comercial\Dominio\Excepciones\LoteDuplicado;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Comercial\Infraestructura\Http\Requests\ActualizarLoteRequest;
use App\Dominios\Comercial\Infraestructura\Http\Requests\CrearLoteRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/lotes*` (tarea 77, HU-54, etapa 2): ficha
 * propia de un lote — antes solo se podía tocar un lote entrando por su
 * propiedad (`CamposController`). Mismo molde que `CamposController`.
 *
 * Cuatro permisos de grano fino
 * (`comercial.lote.ver`/`.crear`/`.editar`/`.eliminar`, sembrados en la
 * etapa 1 de esta tarea), verificados DENTRO del controlador contra el ROL
 * ACTIVO vía {@see AutorizacionPanelWeb}. Ninguna regla de negocio acá: los
 * casos de uso de `Aplicacion/` hacen el trabajo, y el guardado en sí es el
 * MISMO colaborador que usa `CrearCampo`/`ActualizarCampo`
 * ({@see GuardadoLote}) — no hay dos
 * formas de crear o editar un lote, solo dos puertas de entrada.
 */
final class LotesController
{
    private const PERMISO_VER = 'comercial.lote.ver';

    private const PERMISO_CREAR = 'comercial.lote.crear';

    private const PERMISO_EDITAR = 'comercial.lote.editar';

    private const PERMISO_ELIMINAR = 'comercial.lote.eliminar';

    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly ResolverProveedorMapa $resolverProveedorMapa,
    ) {}

    public function index(Request $request, ListarLotes $listarLotes): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();
        $clienteId = $request->filled('cliente_id') ? $request->integer('cliente_id') : null;
        $campoId = $request->filled('campo_id') ? $request->integer('campo_id') : null;

        return view('comercial::pages.lotes.index', [
            ...$this->autorizacion->cascara($request),
            'lotes' => $listarLotes->ejecutar($clienteId, $campoId, $busqueda !== '' ? $busqueda : null),
            'filtros' => ['q' => $busqueda, 'cliente_id' => $clienteId, 'campo_id' => $campoId],
            'clientesDisponibles' => $this->clientesActivos(),
            'propiedadesDisponibles' => $this->propiedadesActivas(),
            'camposDisponibles' => $this->camposActivos(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('comercial::pages.lotes.create', [
            ...$this->autorizacion->cascara($request),
            'clientesDisponibles' => $this->clientesActivos(),
            'propiedadesDisponibles' => $this->propiedadesActivas(),
            'camposDisponibles' => $this->camposActivos(),
            'proveedorMapa' => $this->resolverProveedorMapa->ejecutar(),
        ]);
    }

    public function store(CrearLoteRequest $request, CrearLote $crearLote): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $crearLote->ejecutar((int) $datos['campo_id'], $this->normalizarDatos($datos));
        } catch (LoteDuplicado $excepcion) {
            return redirect()
                ->route('panel.lotes.create')
                ->withInput()
                ->withErrors(['codigo' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.lotes.index')
            ->with('estado', __('comercial.lotes.creado'));
    }

    public function edit(Request $request, Lote $lote): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('comercial::pages.lotes.edit', [
            ...$this->autorizacion->cascara($request),
            'lote' => $lote->load('campo.propiedad'),
            'clientesDisponibles' => $this->clientesActivos(),
            'propiedadesDisponibles' => $this->propiedadesActivas(),
            'camposDisponibles' => $this->camposActivos(),
            'proveedorMapa' => $this->resolverProveedorMapa->ejecutar(),
        ]);
    }

    public function update(ActualizarLoteRequest $request, Lote $lote, ActualizarLote $actualizarLote): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $actualizarLote->ejecutar($lote, (int) $datos['campo_id'], $this->normalizarDatos($datos));
        } catch (LoteDuplicado $excepcion) {
            return redirect()
                ->route('panel.lotes.edit', $lote)
                ->withInput()
                ->withErrors(['codigo' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.lotes.index')
            ->with('estado', __('comercial.lotes.actualizado'));
    }

    public function destroy(Request $request, Lote $lote, EliminarLote $eliminarLote): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        try {
            $eliminarLote->ejecutar($lote);
        } catch (LoteConHistorialAsociado $excepcion) {
            return redirect()
                ->route('panel.lotes.index')
                ->withErrors(['lote' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.lotes.index')
            ->with('estado', __('comercial.lotes.eliminado'));
    }

    /** @return Collection<int, string> */
    private function clientesActivos(): Collection
    {
        return Cliente::query()->orderBy('razon_social')->pluck('razon_social', 'id');
    }

    /**
     * Propiedades activas con su `cliente_id`, para el primer nivel del
     * cascade cliente → propiedad → campo (ADR 0018 introdujo el nivel de
     * propiedad entre cliente y campo).
     *
     * @return Collection<int, Propiedad> id => Propiedad (con `cliente_id`, `nombre`)
     */
    private function propiedadesActivas(): Collection
    {
        return Propiedad::query()
            ->orderBy('nombre')
            ->get(['id', 'cliente_id', 'nombre'])
            ->keyBy('id');
    }

    /**
     * Campos activos con su `propiedad_id`, para el segundo nivel del
     * cascade cliente → propiedad → campo y para el select final del que
     * cuelga el lote. Antes de ADR 0018 este método se llamaba
     * `propiedadesActivas()` y devolvía lo mismo que hoy es `Campo` — el
     * nombre quedó incorrecto cuando "Propiedad" pasó a ser una entidad
     * propia (ver ADR 0018, punto 1).
     *
     * @return Collection<int, Campo> id => Campo (con `propiedad_id`, `nombre`)
     */
    private function camposActivos(): Collection
    {
        return Campo::query()
            ->orderBy('nombre')
            ->get(['id', 'propiedad_id', 'nombre'])
            ->keyBy('id');
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{codigo: string, hectareas: string, geometria: array<string, mixed>|null, restricciones: string|null, desnivel: string|null, limpieza: string|null}
     */
    private function normalizarDatos(array $datos): array
    {
        $lote = $datos['lote'];

        return [
            'codigo' => (string) $lote['codigo'],
            'hectareas' => (string) $lote['hectareas'],
            'geometria' => $this->decodificarGeometria($lote['geometria'] ?? null),
            'restricciones' => $this->cadenaONull($lote['restricciones'] ?? null),
            'desnivel' => $this->cadenaONull($lote['desnivel'] ?? null),
            'limpieza' => $this->cadenaONull($lote['limpieza'] ?? null),
        ];
    }

    /**
     * El Form Request ya validó que, si viene, es JSON bien formado con la
     * forma mínima de un GeoJSON `Polygon` — acá solo se decodifica, no se
     * revalida.
     *
     * @return array<string, mixed>|null
     */
    private function decodificarGeometria(mixed $valor): ?array
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        /** @var array<string, mixed> $decodificado */
        $decodificado = json_decode((string) $valor, true);

        return $decodificado;
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
