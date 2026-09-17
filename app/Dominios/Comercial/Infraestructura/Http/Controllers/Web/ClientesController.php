<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Controllers\Web;

use App\Dominios\Campania\Contratos\DatosCampania;
use App\Dominios\Campania\Contratos\LecturaCampania;
use App\Dominios\Comercial\Aplicacion\ActualizarCliente;
use App\Dominios\Comercial\Aplicacion\CrearCliente;
use App\Dominios\Comercial\Aplicacion\EliminarCliente;
use App\Dominios\Comercial\Aplicacion\ListarClientes;
use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Dominio\Excepciones\ClienteDuplicado;
use App\Dominios\Comercial\Dominio\TipoContactoCliente;
use App\Dominios\Comercial\Dominio\TipoPersonaCliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Http\Requests\ActualizarClienteRequest;
use App\Dominios\Comercial\Infraestructura\Http\Requests\CrearClienteRequest;
use App\Dominios\Operaciones\Contratos\LecturaResumenOrdenesContrato;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/clientes*` (HU-22, tarea 33): alta y
 * mantenimiento de clientes con sus contactos. Primer ABM completo del
 * panel — molde de HU-23 a HU-27 y HU-45 (ver runs/33.md).
 *
 * Cuatro permisos de grano fino gatean cada acción
 * (`comercial.cliente.ver`/`.crear`/`.editar`/`.eliminar`), verificados
 * DENTRO del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}
 * — mismo criterio que `TrabajosController`/`VersionesApkController`, nunca
 * `SecUser`/`session()` directos (ADR 0003 regla 2). Ninguna regla de negocio
 * acá: los casos de uso de `Aplicacion/` hacen el trabajo, incluido el
 * upsert de cliente+contactos en una sola transacción.
 *
 * `resumenRelacionado()` (tarea "resumen de cliente"; corregida el
 * 15/9/2026): la ficha de edición dobla de vista, ya que no hay una pantalla
 * de "ver cliente" propia (mismo criterio documentado en
 * `_formulario.blade.php`) — el aside con Contratos/Propiedades/Aplicación
 * del cliente vive ahí. "Campañas" salió del aside ese mismo día: dejó de
 * ser del cliente (ADR 0015, corrección del 15/9/2026) — es catálogo
 * compartido, y lo que sí es del cliente es la Orden de Aplicación que
 * cuelga de su contrato. `store()` y `update()` redirigen a la propia ficha
 * de edición (no al listado) para que ese aside —con sus accesos directos a
 * "Nuevo contrato"/"Nueva propiedad"/"Nueva orden de aplicación"— quede a un
 * clic, sin pasar por el listado.
 *
 * `logoArchivo()` (HU-75, tarea 91): mismo criterio que
 * `OrganizacionController::logoArchivo()` (ADR 0019) — resuelve `logo_path`
 * a lo que `molecules/file-field` necesita para pintar el preview.
 */
final class ClientesController
{
    private const PERMISO_VER = 'comercial.cliente.ver';

    private const PERMISO_CREAR = 'comercial.cliente.crear';

    private const PERMISO_EDITAR = 'comercial.cliente.editar';

    private const PERMISO_ELIMINAR = 'comercial.cliente.eliminar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarClientes $listarClientes, LecturaCampania $lecturaCampania): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();
        $tipoPersona = $request->string('tipo_persona')->toString() ?: null;
        $campaniaId = $request->integer('campania_id') ?: null;
        $tipoContacto = $request->string('tipo_contacto')->toString() ?: null;

        return view('comercial::pages.clientes.index', [
            ...$this->autorizacion->cascara($request),
            'clientes' => $listarClientes->ejecutar($busqueda !== '' ? $busqueda : null, $tipoPersona, $campaniaId, $tipoContacto),
            'tiposPersonaFiltro' => TipoPersonaCliente::cases(),
            'tiposContactoFiltro' => TipoContactoCliente::cases(),
            'campaniasDisponibles' => $this->campaniasDisponibles($lecturaCampania),
            'filtros' => ['q' => $busqueda, 'tipo_persona' => $tipoPersona, 'campania_id' => $campaniaId, 'tipo_contacto' => $tipoContacto],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('comercial::pages.clientes.create', [
            ...$this->autorizacion->cascara($request),
            'tiposContacto' => TipoContactoCliente::cases(),
            'tiposPersona' => TipoPersonaCliente::cases(),
            'logoArchivo' => $this->logoArchivo(null),
            // Alta rápida desde otro formulario (tarea "contratos-lotes",
            // 16/9/2026): con ?volver_a=, al guardar se ofrece un botón para
            // volver a esa URL con este cliente ya preseleccionado.
            'volverA' => $request->query('volver_a'),
        ]);
    }

    public function store(CrearClienteRequest $request, CrearCliente $crearCliente): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        /** @var list<array<string, mixed>> $contactosCrudos */
        $contactosCrudos = $datos['contactos'];

        try {
            $cliente = $crearCliente->ejecutar(
                (string) $datos['razon_social'],
                isset($datos['nombre_comercial']) ? (string) $datos['nombre_comercial'] : null,
                isset($datos['nit']) ? (string) $datos['nit'] : null,
                (string) $datos['tipo_persona'],
                isset($datos['ubicacion_oficina']) ? (string) $datos['ubicacion_oficina'] : null,
                array_map($this->normalizarContactoNuevo(...), $contactosCrudos),
                $request->file('logo'),
            );
        } catch (ClienteDuplicado $excepcion) {
            return redirect()
                ->route('panel.clientes.create')
                ->withInput()
                ->withErrors(['nit' => $excepcion->getMessage()]);
        }

        // Se queda en la ficha de edición del cliente recién creado (no
        // vuelve al listado): es ahí donde vive el aside de Contratos/
        // Propiedades/Campañas (`resumenRelacionado()`), el siguiente paso
        // natural del flujo cliente → contrato/propiedad → lote. Crear un
        // cliente sin nada relacionado todavía es el caso más común, así que
        // arrancar ese flujo desde el listado sería un clic de más siempre.
        return redirect()
            ->route('panel.clientes.edit', $cliente)
            ->with('estado', __('comercial.clientes.creado'))
            ->with('volverA', $request->input('volver_a'));
    }

    public function edit(Request $request, Cliente $cliente, LecturaResumenOrdenesContrato $lecturaResumenOrdenes): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $cliente->load(['contactos', 'contratos', 'propiedades']);

        return view('comercial::pages.clientes.edit', [
            ...$this->autorizacion->cascara($request),
            'cliente' => $cliente,
            'tiposContacto' => TipoContactoCliente::cases(),
            'tiposPersona' => TipoPersonaCliente::cases(),
            'logoArchivo' => $this->logoArchivo($cliente),
            'resumenRelacionado' => $this->resumenRelacionado($cliente, $request, $lecturaResumenOrdenes),
            'volverA' => session('volverA'),
        ]);
    }

    public function update(ActualizarClienteRequest $request, Cliente $cliente, ActualizarCliente $actualizarCliente): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        /** @var list<array<string, mixed>> $contactosCrudos */
        $contactosCrudos = $datos['contactos'];

        try {
            $actualizarCliente->ejecutar(
                $cliente,
                (string) $datos['razon_social'],
                isset($datos['nombre_comercial']) ? (string) $datos['nombre_comercial'] : null,
                isset($datos['nit']) ? (string) $datos['nit'] : null,
                (string) $datos['tipo_persona'],
                isset($datos['ubicacion_oficina']) ? (string) $datos['ubicacion_oficina'] : null,
                array_map($this->normalizarContactoExistente(...), $contactosCrudos),
                $request->file('logo'),
                $request->boolean('logo_eliminar'),
            );
        } catch (ClienteDuplicado $excepcion) {
            return redirect()
                ->route('panel.clientes.edit', $cliente)
                ->withInput()
                ->withErrors(['nit' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado,
        // 15/9/2026 — pedido directo): mismo criterio que `store()` — es
        // donde vive el aside de Contratos/Propiedades/Campañas
        // (`resumenRelacionado()`), y de ahí es más común seguir editando o
        // encadenar una acción relacionada que volver al listado a mano.
        return redirect()
            ->route('panel.clientes.edit', $cliente)
            ->with('estado', __('comercial.clientes.actualizado'));
    }

    public function destroy(Request $request, Cliente $cliente, EliminarCliente $eliminarCliente): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarCliente->ejecutar($cliente);

        return redirect()
            ->route('panel.clientes.index')
            ->with('estado', __('comercial.clientes.eliminado'));
    }

    /**
     * @param  array<string, mixed>  $contacto
     * @return array{tipo: string, tipo_otro: string|null, nombre: string, telefono: string|null, email: string|null, observaciones: string|null}
     */
    private function normalizarContactoNuevo(array $contacto): array
    {
        return [
            'tipo' => (string) $contacto['tipo'],
            'tipo_otro' => $this->cadenaONull($contacto['tipo_otro'] ?? null),
            'nombre' => (string) $contacto['nombre'],
            'telefono' => $this->cadenaONull($contacto['telefono'] ?? null),
            'email' => $this->cadenaONull($contacto['email'] ?? null),
            'observaciones' => $this->cadenaONull($contacto['observaciones'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $contacto
     * @return array{id: int|null, tipo: string, tipo_otro: string|null, nombre: string, telefono: string|null, email: string|null, observaciones: string|null}
     */
    private function normalizarContactoExistente(array $contacto): array
    {
        return [
            'id' => isset($contacto['id']) ? (int) $contacto['id'] : null,
            ...$this->normalizarContactoNuevo($contacto),
        ];
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }

    /**
     * Todas las campañas del catálogo, para el filtro del listado — mismo
     * criterio que `ContratosController::campaniasDisponibles()`: vía
     * {@see LecturaCampania::todas()} (ADR 0003 regla 2), nunca el modelo
     * Eloquent `Campania` directo desde este módulo.
     *
     * @return Collection<int, string>
     */
    private function campaniasDisponibles(LecturaCampania $lecturaCampania): Collection
    {
        return collect($lecturaCampania->todas())
            ->mapWithKeys(fn (DatosCampania $campania): array => [$campania->id => $campania->codigo]);
    }

    /**
     * Resumen de Contratos/Propiedades/Aplicación de un cliente, para el
     * aside de `edit.blade.php` (tarea "resumen de cliente"): solo tiene
     * sentido en edición — un cliente recién creado nunca puede tener ya
     * contratos, propiedades ni órdenes de aplicación propias (todos nacen
     * de un `cliente_id`/`contrato_id` de un cliente que ya existe).
     *
     * Gateado por los permisos de grano fino de CADA módulo contra el ROL
     * ACTIVO (invariante 10 de CLAUDE.md), no por `comercial.cliente.*`: ver
     * el resumen de contratos de un cliente es ver contratos, así que exige
     * `comercial.contrato.ver`, no el permiso de cliente. Una categoría sin
     * `.ver` NI `.crear` se omite del todo (el usuario no tiene nada que
     * hacer ahí); con `.ver` pero sin `.crear` se puede mirar el resumen pero
     * no aparece el atajo de alta; sin `.ver` pero con `.crear` se ofrece el
     * atajo sin revelar conteos que el usuario no puede consultar. También
     * evita la consulta cuando no hace falta (sin `.ver` no se cuenta nada).
     *
     * Corrección del 15/9/2026 (ADR 0015): "Campañas" sale del aside — dejó
     * de ser del cliente, es catálogo compartido sin `cliente_id`. En su
     * lugar entra "Aplicación" (Orden de Aplicación), que sí es del cliente
     * vía su contrato. Aplicación es lectura cross-módulo (ADR 0003 regla
     * 2): vía {@see LecturaResumenOrdenesContrato}, la frontera de
     * `Operaciones` — nunca su tabla `ope_ordenes_aplicacion` ni su modelo
     * Eloquent `OrdenAplicacion` ni su enum `EstadoOrdenAplicacion`
     * cruzando a Comercial. Contratos y Propiedades sí usan las relaciones
     * Eloquent de `Cliente` (mismo módulo).
     *
     * @return list<array{
     *     titulo: string,
     *     icono: string,
     *     tieneDatos: bool,
     *     items: list<array{label: string, value: string, mono?: bool}>,
     *     vacioTitulo: string,
     *     vacioDetalle: string,
     *     mostrarAccion: bool,
     *     accion: array{label: string, href: string},
     * }>
     */
    private function resumenRelacionado(Cliente $cliente, Request $request, LecturaResumenOrdenesContrato $lecturaResumenOrdenes): array
    {
        $resumen = [];

        // Memento de navegación (17/9/2026): los 3 accesos directos de este
        // aside apilan ESTA ficha de cliente como origen — así el "Volver"
        // de la pantalla de destino (nuevo contrato/propiedad/orden, y de lo
        // que esa pantalla encadene después) sabe adónde volver, en vez de
        // caer siempre al listado de su propio módulo. Ver
        // RecordarOrigenNavegacion.
        $origenNavegacion = ['volver_a' => route('panel.clientes.edit', $cliente), 'volver_texto' => $cliente->razon_social];

        $puedeVerContratos = $this->autorizacion->tienePermiso($request, 'comercial.contrato.ver');
        $puedeCrearContratos = $this->autorizacion->tienePermiso($request, 'comercial.contrato.crear');

        if ($puedeVerContratos || $puedeCrearContratos) {
            $contratos = $puedeVerContratos ? $cliente->contratos : collect();
            $totalContratos = $contratos->count();
            $contratosVigentes = $contratos->filter(fn ($contrato) => $contrato->estado === EstadoContrato::Vigente)->count();

            $resumen[] = [
                'titulo' => __('comercial.clientes.aside_contratos_titulo'),
                'icono' => 'description',
                'tieneDatos' => $puedeVerContratos && $totalContratos > 0,
                'items' => [
                    ['label' => __('comercial.clientes.aside_contratos_total'), 'value' => (string) $totalContratos, 'mono' => true],
                    ['label' => __('comercial.clientes.aside_contratos_vigentes'), 'value' => (string) $contratosVigentes, 'mono' => true],
                ],
                'vacioTitulo' => __('comercial.clientes.aside_contratos_vacio_titulo'),
                'vacioDetalle' => __('comercial.clientes.aside_contratos_vacio_detalle'),
                'mostrarAccion' => $puedeCrearContratos,
                'accion' => [
                    'label' => __('comercial.clientes.aside_contratos_accion'),
                    'href' => route('panel.contratos.create', ['cliente_id' => $cliente->id, ...$origenNavegacion]),
                ],
            ];
        }

        $puedeVerPropiedades = $this->autorizacion->tienePermiso($request, 'comercial.propiedad.ver');
        $puedeCrearPropiedades = $this->autorizacion->tienePermiso($request, 'comercial.propiedad.crear');

        if ($puedeVerPropiedades || $puedeCrearPropiedades) {
            $propiedades = $puedeVerPropiedades ? $cliente->propiedades : collect();
            $totalPropiedades = $propiedades->count();
            $totalLotes = $totalPropiedades > 0
                ? Lote::whereIn('propiedad_id', $propiedades->pluck('id'))->count()
                : 0;

            $resumen[] = [
                'titulo' => __('comercial.clientes.aside_propiedades_titulo'),
                'icono' => 'domain',
                'tieneDatos' => $puedeVerPropiedades && $totalPropiedades > 0,
                'items' => [
                    ['label' => __('comercial.clientes.aside_propiedades_total'), 'value' => (string) $totalPropiedades, 'mono' => true],
                    ['label' => __('comercial.clientes.aside_propiedades_lotes'), 'value' => (string) $totalLotes, 'mono' => true],
                ],
                'vacioTitulo' => __('comercial.clientes.aside_propiedades_vacio_titulo'),
                'vacioDetalle' => __('comercial.clientes.aside_propiedades_vacio_detalle'),
                'mostrarAccion' => $puedeCrearPropiedades,
                'accion' => [
                    'label' => __('comercial.clientes.aside_propiedades_accion'),
                    'href' => route('panel.propiedades.create', ['cliente_id' => $cliente->id, ...$origenNavegacion]),
                ],
            ];
        }

        $puedeVerOrdenes = $this->autorizacion->tienePermiso($request, 'operaciones.orden.ver');
        $puedeCrearOrdenes = $this->autorizacion->tienePermiso($request, 'operaciones.orden.crear');

        if ($puedeVerOrdenes || $puedeCrearOrdenes) {
            $totalOrdenes = 0;
            $ordenesVigentes = 0;

            if ($puedeVerOrdenes) {
                $contratoIds = $cliente->contratos->pluck('id')->all();
                $resumenOrdenes = $lecturaResumenOrdenes->resumen($contratoIds);
                $totalOrdenes = $resumenOrdenes['total'];
                $ordenesVigentes = $resumenOrdenes['vigentes'];
            }

            $resumen[] = [
                'titulo' => __('comercial.clientes.aside_ordenes_titulo'),
                'icono' => 'assignment',
                'tieneDatos' => $puedeVerOrdenes && $totalOrdenes > 0,
                'items' => [
                    ['label' => __('comercial.clientes.aside_ordenes_total'), 'value' => (string) $totalOrdenes, 'mono' => true],
                    ['label' => __('comercial.clientes.aside_ordenes_vigentes'), 'value' => (string) $ordenesVigentes, 'mono' => true],
                ],
                'vacioTitulo' => __('comercial.clientes.aside_ordenes_vacio_titulo'),
                'vacioDetalle' => __('comercial.clientes.aside_ordenes_vacio_detalle'),
                'mostrarAccion' => $puedeCrearOrdenes,
                'accion' => [
                    'label' => __('comercial.clientes.aside_ordenes_accion'),
                    'href' => route('panel.ordenes.create', $origenNavegacion),
                ],
            ];
        }

        return $resumen;
    }

    /**
     * `logo_path` es una ruta relativa del disco `public` (mismo criterio
     * que `SecDatosEmpresa`, ADR 0019) — acá se resuelve a lo que la vista
     * necesita para pintar el `file-field`: nombre de archivo, peso legible
     * y URL pública. Sin logo guardado, `null` — `file-field` ya sabe
     * mostrar el estado vacío.
     *
     * @return array{nombre: string, peso: string, url: string}|null
     */
    private function logoArchivo(?Cliente $cliente): ?array
    {
        if ($cliente?->logo_path === null) {
            return null;
        }

        $disco = Storage::disk('public');

        if (! $disco->exists($cliente->logo_path)) {
            return null;
        }

        return [
            'nombre' => basename($cliente->logo_path),
            'peso' => $this->pesoLegible($disco->size($cliente->logo_path)),
            'url' => $disco->url($cliente->logo_path),
        ];
    }

    private function pesoLegible(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        return round($bytes / 1024).' KB';
    }
}
