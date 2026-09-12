<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Aplicacion\GuardarDatosEmpresa;
use App\Dominios\Seguridad\Aplicacion\GuardarDatosFiscales;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecDatosEmpresa;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecDatosFiscales;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\CascaraPanel;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\ActualizarDatosEmpresaRequest;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\ActualizarDatosFiscalesRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * `GET /panel/organizacion` (`panel.organizacion.index`): pantalla de "Registro
 * de la compañía". Reconstruida sobre el arquetipo formulario (tarea 31, ver
 * docs/diseno/guia_pantalla_panel.md §6.3): es el caso de prueba del catálogo nuevo
 * (`page-header`, `tabs`, `form-section` evolucionado, `progress-meter`,
 * `summary-card`, `file-field`, `form-actions-bar`).
 *
 * De la pestaña "Organización", "Datos de empresa" y "Datos de contacto" (nombre,
 * rubro, email, teléfono, dirección) son reales desde el 11/9/2026: persisten en
 * {@see SecDatosEmpresa} vía {@see GuardarDatosEmpresa}, mismo criterio de fila
 * única que "Facturación". El logo TAMBIÉN es real (ADR 0019, excepción puntual
 * a ADR 0009 categoría 3 — "assets de marca van en el repo"): se guarda en el
 * disco `public` bajo `logos/empresa/`, nunca en `r2` (reservado a evidencias
 * privadas con URL firmada) ni versionado en git como el resto de los assets de
 * marca (favicon, iconografía del panel, plantillas PDF, que NO cambian por
 * esta excepción). El resto de la pestaña (plan de suscripción, multi-sucursal)
 * SIGUE siendo mockup — es la conversación sobre un pivot SaaS multi-tenant que
 * todavía NO está decidida: sin ADR, sin tabla `tenant`, sin guard nuevo.
 *
 * La pestaña "Facturación" (tarea 78, HU-55) es la otra mitad real: pedido
 * explícito del dueño de separar los datos FISCALES de la empresa (con qué razón
 * social/NIT se factura) de la configuración de infraestructura
 * (`/panel/configuracion`, llaves y tokens). Tiene su propio formulario, su propio
 * botón "Guardar" y persiste en {@see SecDatosFiscales} vía {@see GuardarDatosFiscales}.
 *
 * Autorización: `ver` gatea la pantalla completa (tarea 62, fuga 2: antes era visible
 * sin permiso propio para cualquier usuario autenticado). `editar` gatea el guardado
 * de "Datos de empresa"/"Datos de contacto" y de "Facturación" por igual (es la misma
 * mutación de fondo: datos de presentación de la empresa) — mismo criterio de grano
 * fino que el resto del panel (ver/crear/editar separados). El ítem de menú en
 * `sec_menu` sigue gateado por `ver` ({@see Database\Seeders\Catalogo\SecMenuSeeder}).
 *
 * Middleware `auth:interno` + `rol.activo`: para cuando este controlador se ejecuta,
 * `session('sec_rol_activo_id')` ya es un rol vivo válido de este usuario.
 *
 * Adaptador delgado (ADR 0008): resuelve el árbol de menú del ROL ACTIVO (nunca la unión
 * de todos los roles del usuario, invariante 10 de `CLAUDE.md`) y datos de cáscara que
 * `templates/panel-layout` espera.
 *
 * MOCK, solo en la pestaña "Organización" (sin persistencia real):
 * - 3 plan-card (Básico/Profesional/Enterprise).
 * - Switch de "multi-sucursal".
 * - Completitud de perfil (aside, `progress-meter`) y resumen de suscripción
 *   (aside, `summary-card`): ilustrativos, no derivados de los campos de arriba.
 */
final class OrganizacionController
{
    private const PERMISO_VER = 'seguridad.organizacion.ver';

    private const PERMISO_EDITAR = 'seguridad.organizacion.editar';

    public function index(Request $request, AutorizacionPanelWeb $autorizacion, CascaraPanel $cascara): View
    {
        abort_unless($autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        /** @var SecUser $usuario */
        $usuario = $request->user('interno');
        $idRolActivo = (int) $request->session()->get('sec_rol_activo_id');
        $tabActiva = $request->query('tab') === 'facturacion' ? 'facturacion' : 'organizacion';

        $datosEmpresa = SecDatosEmpresa::query()->first();

        return view('seguridad::pages.organizacion.index', array_merge(
            $cascara->para($usuario, $idRolActivo),
            $this->datosMock($tabActiva),
            [
                'datosEmpresa' => $datosEmpresa,
                'datosFiscales' => SecDatosFiscales::query()->first(),
                'puedeEditarOrganizacion' => $autorizacion->tienePermiso($request, self::PERMISO_EDITAR),
                'logoArchivo' => $this->logoArchivo($datosEmpresa),
            ],
        ));
    }

    /**
     * `POST /panel/organizacion/empresa` (`panel.organizacion.empresa.actualizar`).
     * Redirige a la propia pestaña (sin `?tab=`, es la que abre por defecto).
     */
    public function actualizarEmpresa(
        ActualizarDatosEmpresaRequest $request,
        AutorizacionPanelWeb $autorizacion,
        GuardarDatosEmpresa $guardar,
    ): RedirectResponse {
        abort_unless($autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $guardar->ejecutar(
            $request->safe()->only(['nombre', 'rubro', 'email', 'telefono', 'direccion']),
            $request->file('logo'),
            $request->boolean('logo_eliminar'),
        );

        return redirect()
            ->route('panel.organizacion.index')
            ->with('estado', __('seguridad.organizacion.empresa_guardada'));
    }

    /**
     * `logo_path` es una ruta relativa del disco `public` (ADR 0019) — acá se
     * resuelve a lo que la vista necesita para pintar el `file-field`: nombre
     * de archivo, peso legible y URL pública (requiere el symlink de
     * `php artisan storage:link`). Sin logo guardado, `null` — `file-field`
     * ya sabe mostrar el estado vacío.
     *
     * @return array{nombre: string, peso: string, url: string}|null
     */
    private function logoArchivo(?SecDatosEmpresa $datosEmpresa): ?array
    {
        if ($datosEmpresa?->logo_path === null) {
            return null;
        }

        $disco = Storage::disk('public');

        if (! $disco->exists($datosEmpresa->logo_path)) {
            return null;
        }

        return [
            'nombre' => basename($datosEmpresa->logo_path),
            'peso' => $this->pesoLegible($disco->size($datosEmpresa->logo_path)),
            'url' => $disco->url($datosEmpresa->logo_path),
        ];
    }

    private function pesoLegible(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        return round($bytes / 1024).' KB';
    }

    /**
     * `POST /panel/organizacion/facturacion` (`panel.organizacion.facturacion.actualizar`).
     * Redirige a la propia pestaña (`?tab=facturacion`), nunca a la de "Organización".
     */
    public function actualizarFacturacion(
        ActualizarDatosFiscalesRequest $request,
        AutorizacionPanelWeb $autorizacion,
        GuardarDatosFiscales $guardar,
    ): RedirectResponse {
        abort_unless($autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $guardar->ejecutar($request->validated());

        return redirect()
            ->route('panel.organizacion.index', ['tab' => 'facturacion'])
            ->with('estado', __('seguridad.organizacion.facturacion_guardada'));
    }

    /**
     * @return array<string, mixed>
     */
    private function datosMock(string $tabActiva): array
    {
        $completos = 5;
        $total = 6;

        return [
            'tabActiva' => $tabActiva,
            'tabs' => [
                ['id' => 'ag-tab-organizacion', 'label' => __('seguridad.organizacion.tab_organizacion'), 'active' => $tabActiva === 'organizacion'],
                ['id' => 'ag-tab-facturacion', 'label' => __('seguridad.organizacion.tab_facturacion'), 'active' => $tabActiva === 'facturacion'],
            ],
            'progreso' => [
                'percent' => (int) round($completos / $total * 100),
                'summaryLabel' => __('seguridad.organizacion.aside_progreso_resumen', ['completos' => $completos, 'total' => $total]),
                'items' => [
                    ['label' => __('seguridad.organizacion.aside_progreso_item_nombre'), 'complete' => true],
                    ['label' => __('seguridad.organizacion.aside_progreso_item_rubro'), 'complete' => true],
                    ['label' => __('seguridad.organizacion.aside_progreso_item_logo'), 'complete' => true],
                    ['label' => __('seguridad.organizacion.aside_progreso_item_contacto'), 'complete' => true],
                    ['label' => __('seguridad.organizacion.aside_progreso_item_domicilio_fiscal'), 'complete' => true],
                    ['label' => __('seguridad.organizacion.aside_progreso_item_datos_bancarios'), 'complete' => false],
                ],
            ],
            'suscripcion' => [
                ['label' => __('seguridad.organizacion.aside_suscripcion_plan'), 'value' => __('seguridad.organizacion.aside_suscripcion_plan_valor')],
                ['label' => __('seguridad.organizacion.aside_suscripcion_estado'), 'value' => __('seguridad.organizacion.aside_suscripcion_estado_valor'), 'badge' => true, 'variant' => 'success'],
                ['label' => __('seguridad.organizacion.aside_suscripcion_renueva'), 'value' => __('seguridad.organizacion.mock_renueva_fecha'), 'mono' => true],
                ['label' => __('seguridad.organizacion.aside_suscripcion_dispositivos'), 'value' => __('seguridad.organizacion.mock_dispositivos_valor'), 'mono' => true],
            ],
        ];
    }
}
