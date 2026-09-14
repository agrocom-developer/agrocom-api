<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Aplicacion\ActualizarCampo;
use App\Dominios\Comercial\Aplicacion\CrearCampo;
use App\Dominios\Comercial\Aplicacion\EliminarCampo;
use App\Dominios\Comercial\Aplicacion\ListarCampos;
use App\Dominios\Comercial\Aplicacion\ResolverProveedorMapa;
use App\Dominios\Comercial\Dominio\Excepciones\CampaniaDeOtroCliente;
use App\Dominios\Comercial\Dominio\Excepciones\CampoDuplicado;
use App\Dominios\Comercial\Dominio\Excepciones\LoteConHistorialAsociado;
use App\Dominios\Comercial\Dominio\Excepciones\LoteDuplicado;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Comercial\Infraestructura\Http\Requests\ActualizarCampoRequest;
use App\Dominios\Comercial\Infraestructura\Http\Requests\CrearCampoRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/campos*` (HU-24, tarea 35): alta y
 * mantenimiento de campos con sus lotes. Mismo molde que `ClientesController`
 * (HU-22, tarea 33) — ver `prompts/33-abm-clientes.md` para el detalle de
 * las decisiones que este controlador reutiliza.
 *
 * Cuatro permisos de grano fino
 * (`comercial.campo.ver`/`.crear`/`.editar`/`.eliminar`), verificados DENTRO
 * del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}. El
 * alta de un campo sigue trayendo sus lotes en la misma transacción
 * (`CrearCampo`/`ActualizarCampo`) — eso no cambió con la tarea 77; lo que
 * se agregó es poder entrar por el lote también, vía `comercial.lote.*` y
 * `LotesController`, sin quitarle nada a este camino. Ninguna regla de
 * negocio acá: los casos de uso de `Aplicacion/` hacen el trabajo, incluido
 * el upsert de campo+lotes en una sola transacción.
 */
final class CamposController
{
    private const PERMISO_VER = 'comercial.campo.ver';

    private const PERMISO_CREAR = 'comercial.campo.crear';

    private const PERMISO_EDITAR = 'comercial.campo.editar';

    private const PERMISO_ELIMINAR = 'comercial.campo.eliminar';

    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly ResolverProveedorMapa $resolverProveedorMapa,
    ) {}

    public function index(Request $request, ListarCampos $listarCampos): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();

        return view('comercial::pages.campos.index', [
            ...$this->autorizacion->cascara($request),
            'campos' => $listarCampos->ejecutar($busqueda !== '' ? $busqueda : null),
            'filtros' => ['q' => $busqueda],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('comercial::pages.campos.create', [
            ...$this->autorizacion->cascara($request),
            'clientesDisponibles' => $this->clientesActivos(),
            'propiedadesDisponibles' => $this->propiedadesActivas(),
            'proveedorMapa' => $this->resolverProveedorMapa->ejecutar(),
            'cultivosDisponibles' => $this->cultivosActivos(),
            'campaniasDisponibles' => $this->campaniasActivas(),
        ]);
    }

    public function store(CrearCampoRequest $request, CrearCampo $crearCampo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        /** @var list<array<string, mixed>> $lotesCrudos */
        $lotesCrudos = $datos['lotes'];

        try {
            $crearCampo->ejecutar(
                (int) $datos['propiedad_id'],
                (string) $datos['nombre'],
                array_map($this->normalizarLoteNuevo(...), $lotesCrudos),
                isset($datos['cultivo_id']) ? (int) $datos['cultivo_id'] : null,
                isset($datos['campania_id']) ? (int) $datos['campania_id'] : null,
            );
        } catch (CampoDuplicado $excepcion) {
            return redirect()
                ->route('panel.campos.create')
                ->withInput()
                ->withErrors(['nombre' => $excepcion->getMessage()]);
        } catch (LoteDuplicado $excepcion) {
            return redirect()
                ->route('panel.campos.create')
                ->withInput()
                ->withErrors(['lotes' => $excepcion->getMessage()]);
        } catch (CampaniaDeOtroCliente $excepcion) {
            return redirect()
                ->route('panel.campos.create')
                ->withInput()
                ->withErrors(['campania_id' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.campos.index')
            ->with('estado', __('comercial.campos.creado'));
    }

    public function edit(Request $request, Campo $campo): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('comercial::pages.campos.edit', [
            ...$this->autorizacion->cascara($request),
            'campo' => $campo->load('lotes', 'propiedad'),
            'clientesDisponibles' => $this->clientesActivos(),
            'propiedadesDisponibles' => $this->propiedadesActivas(),
            'proveedorMapa' => $this->resolverProveedorMapa->ejecutar(),
        ]);
    }

    public function update(ActualizarCampoRequest $request, Campo $campo, ActualizarCampo $actualizarCampo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        /** @var list<array<string, mixed>> $lotesCrudos */
        $lotesCrudos = $datos['lotes'];

        try {
            $actualizarCampo->ejecutar(
                $campo,
                (int) $datos['propiedad_id'],
                (string) $datos['nombre'],
                array_map($this->normalizarLoteExistente(...), $lotesCrudos),
            );
        } catch (CampoDuplicado $excepcion) {
            return redirect()
                ->route('panel.campos.edit', $campo)
                ->withInput()
                ->withErrors(['nombre' => $excepcion->getMessage()]);
        } catch (LoteDuplicado|LoteConHistorialAsociado $excepcion) {
            return redirect()
                ->route('panel.campos.edit', $campo)
                ->withInput()
                ->withErrors(['lotes' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.campos.index')
            ->with('estado', __('comercial.campos.actualizado'));
    }

    public function destroy(Request $request, Campo $campo, EliminarCampo $eliminarCampo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarCampo->ejecutar($campo);

        return redirect()
            ->route('panel.campos.index')
            ->with('estado', __('comercial.campos.eliminado'));
    }

    /** @return Collection<int, string> */
    private function clientesActivos(): Collection
    {
        return Cliente::query()->orderBy('razon_social')->pluck('razon_social', 'id');
    }

    /**
     * Propiedades activas con su `cliente_id`, para el cascade cliente →
     * propiedad del formulario de campo (ADR 0018: el campo ahora cuelga de
     * una propiedad, no directo de un cliente).
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

    /** @return Collection<int, string> id => nombre, cultivos activos (HU-72, tarea 88: cultivo por defecto del generador). */
    private function cultivosActivos(): Collection
    {
        return Cultivo::query()->where('activo', true)->orderBy('nombre')->pluck('nombre', 'id');
    }

    /**
     * Campañas no dadas de baja de TODOS los clientes, con su `cliente_id`
     * (HU-72, tarea 88): a diferencia de `SiembraController::campaniasDelCliente()`,
     * acá todavía no existe un campo del que derivar el cliente — el
     * generador filtra client-side contra el cliente elegido, mismo patrón
     * que `propiedadesActivas()` con `data-mapa-cliente-propiedad`. Lectura
     * directa de `cpn_campanias` (ADR 0003 regla 3), sin el modelo Eloquent
     * `Campania` de otro módulo.
     *
     * @return Collection<int, \stdClass>
     */
    private function campaniasActivas(): Collection
    {
        return DB::table('cpn_campanias')
            ->whereNull('deleted_at')
            ->orderByDesc('fecha_inicio')
            ->get(['id', 'cliente_id', 'codigo'])
            ->mapWithKeys(fn (object $fila): array => [(int) $fila->id => $fila]);
    }

    /**
     * @param  array<string, mixed>  $lote
     * @return array{codigo: string, hectareas: string, geometria: array<string, mixed>|null, restricciones: string|null}
     */
    private function normalizarLoteNuevo(array $lote): array
    {
        return [
            'codigo' => (string) $lote['codigo'],
            'hectareas' => (string) $lote['hectareas'],
            'geometria' => $this->decodificarGeometria($lote['geometria'] ?? null),
            'restricciones' => $this->cadenaONull($lote['restricciones'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $lote
     * @return array{id: int|null, codigo: string, hectareas: string, geometria: array<string, mixed>|null, restricciones: string|null}
     */
    private function normalizarLoteExistente(array $lote): array
    {
        return [
            'id' => isset($lote['id']) && $lote['id'] !== '' ? (int) $lote['id'] : null,
            ...$this->normalizarLoteNuevo($lote),
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
