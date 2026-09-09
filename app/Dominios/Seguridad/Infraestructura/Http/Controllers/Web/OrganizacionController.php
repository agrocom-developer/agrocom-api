<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Aplicacion\GuardarDatosFiscales;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecDatosFiscales;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\CascaraPanel;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\ActualizarDatosFiscalesRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET /panel/organizacion` (`panel.organizacion.index`): pantalla de PRESENTACIÓN
 * del "Registro de la compañía" — mockup visual para una conversación sobre un pivot
 * SaaS multi-tenant que NO está decidido, sin ADR, sin tabla `tenant`/`organizacion`,
 * sin guard nuevo. Reconstruida sobre el arquetipo formulario (tarea 31, ver
 * docs/diseno/guia_pantalla_panel.md §6.3): es el caso de prueba del catálogo nuevo
 * (`page-header`, `tabs`, `form-section` evolucionado, `progress-meter`,
 * `summary-card`, `file-field`, `form-actions-bar`).
 *
 * La pestaña "Organización" (el mockup de arriba) sigue GET/solo-lectura, botones
 * "Guardar"/"Descartar" deshabilitados: nada de eso tiene tabla ni ADR todavía.
 * La pestaña "Facturación" (tarea 78, HU-55) ES real desde acá: pedido explícito del
 * dueño de separar los datos FISCALES de la empresa (con qué razón social/NIT se
 * factura) de la configuración de infraestructura (`/panel/configuracion`, llaves y
 * tokens). Tiene su propio formulario, su propio botón "Guardar" y persiste en
 * {@see SecDatosFiscales} vía {@see GuardarDatosFiscales} — no hay conflicto con el
 * párrafo anterior porque son dos pestañas con alcance distinto, no la misma mutación
 * fantasma que el diseño original de esta pantalla evitaba.
 *
 * Autorización: `ver` gatea la pantalla completa (tarea 62, fuga 2: antes era visible
 * sin permiso propio para cualquier usuario autenticado). `editar` gatea SOLO el guardado
 * de la pestaña Facturación — mismo criterio de grano fino que el resto del panel
 * (ver/crear/editar separados). El ítem de menú en `sec_menu` sigue gateado por `ver`
 * ({@see Database\Seeders\Catalogo\SecMenuSeeder}).
 *
 * Middleware `auth:interno` + `rol.activo`: para cuando este controlador se ejecuta,
 * `session('sec_rol_activo_id')` ya es un rol vivo válido de este usuario.
 *
 * Adaptador delgado (ADR 0008): resuelve el árbol de menú del ROL ACTIVO (nunca la unión
 * de todos los roles del usuario, invariante 10 de `CLAUDE.md`) y datos de cáscara que
 * `templates/panel-layout` espera.
 *
 * MOCK, solo en la pestaña "Organización" (sin persistencia real):
 * - Nombre/rubro/logo de empresa.
 * - Datos de contacto (email, teléfono, dirección).
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

        return view('seguridad::pages.organizacion.index', array_merge(
            $cascara->para($usuario, $idRolActivo),
            $this->datosMock($tabActiva),
            [
                'datosFiscales' => SecDatosFiscales::query()->first(),
                'puedeEditarFacturacion' => $autorizacion->tienePermiso($request, self::PERMISO_EDITAR),
            ],
        ));
    }

    /**
     * `POST /panel/organizacion/facturacion` (`panel.organizacion.facturacion.actualizar`).
     * Redirige a la propia pestaña (`?tab=facturacion`), nunca a la de "Organización" —
     * si no, el usuario guarda y aterriza mirando el mockup.
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
            'logoArchivo' => [
                'nombre' => __('seguridad.organizacion.mock_logo_nombre'),
                'peso' => __('seguridad.organizacion.mock_logo_peso'),
            ],
        ];
    }
}
