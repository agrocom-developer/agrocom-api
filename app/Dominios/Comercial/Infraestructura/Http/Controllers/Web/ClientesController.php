<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Aplicacion\ActualizarCliente;
use App\Dominios\Comercial\Aplicacion\CrearCliente;
use App\Dominios\Comercial\Aplicacion\EliminarCliente;
use App\Dominios\Comercial\Aplicacion\ListarClientes;
use App\Dominios\Comercial\Dominio\Excepciones\ClienteDuplicado;
use App\Dominios\Comercial\Dominio\TipoContactoCliente;
use App\Dominios\Comercial\Dominio\TipoPersonaCliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Http\Requests\ActualizarClienteRequest;
use App\Dominios\Comercial\Infraestructura\Http\Requests\CrearClienteRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function index(Request $request, ListarClientes $listarClientes): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();

        return view('comercial::pages.clientes.index', [
            ...$this->autorizacion->cascara($request),
            'clientes' => $listarClientes->ejecutar($busqueda !== '' ? $busqueda : null),
            'filtros' => ['q' => $busqueda],
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
        ]);
    }

    public function store(CrearClienteRequest $request, CrearCliente $crearCliente): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        /** @var list<array<string, mixed>> $contactosCrudos */
        $contactosCrudos = $datos['contactos'];

        try {
            $crearCliente->ejecutar(
                (string) $datos['razon_social'],
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

        return redirect()
            ->route('panel.clientes.index')
            ->with('estado', __('comercial.clientes.creado'));
    }

    public function edit(Request $request, Cliente $cliente): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('comercial::pages.clientes.edit', [
            ...$this->autorizacion->cascara($request),
            'cliente' => $cliente->load('contactos'),
            'tiposContacto' => TipoContactoCliente::cases(),
            'tiposPersona' => TipoPersonaCliente::cases(),
            'logoArchivo' => $this->logoArchivo($cliente),
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

        return redirect()
            ->route('panel.clientes.index')
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
     * @return array{tipo: string, nombre: string, telefono: string|null, email: string|null, observaciones: string|null}
     */
    private function normalizarContactoNuevo(array $contacto): array
    {
        return [
            'tipo' => (string) $contacto['tipo'],
            'nombre' => (string) $contacto['nombre'],
            'telefono' => $this->cadenaONull($contacto['telefono'] ?? null),
            'email' => $this->cadenaONull($contacto['email'] ?? null),
            'observaciones' => $this->cadenaONull($contacto['observaciones'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $contacto
     * @return array{id: int|null, tipo: string, nombre: string, telefono: string|null, email: string|null, observaciones: string|null}
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
