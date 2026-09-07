<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\CascaraPanel;
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
 * Deliberadamente GET/solo-lectura: NO hay ningún endpoint POST que "guarde" nada —
 * sería una mutación fantasma sin persistencia real y sin bitácora (rompe el invariante 9
 * de `CLAUDE.md`). Los botones "Guardar"/"Descartar" están deshabilitados.
 *
 * Autorización: gateada por `seguridad.organizacion.ver` (tarea 62, fuga 2: antes era
 * visible sin permiso propio para cualquier usuario autenticado — un `auxiliar` veía la
 * ficha completa de la compañía). El ítem de menú en `sec_menu` lleva ahora ese permiso
 * ({@see Database\Seeders\Catalogo\SecMenuSeeder}).
 *
 * Middleware `auth:interno` + `rol.activo`: para cuando este controlador se ejecuta,
 * `session('sec_rol_activo_id')` ya es un rol vivo válido de este usuario.
 *
 * Adaptador delgado (ADR 0008): resuelve el árbol de menú del ROL ACTIVO (nunca la unión
 * de todos los roles del usuario, invariante 10 de `CLAUDE.md`) y datos de cáscara que
 * `templates/panel-layout` espera — el contenido es MOCK (datos prellenados realistas)
 * para demostración visual.
 *
 * MOCK (este es el estado final, no hay persistencia real):
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

    public function index(Request $request, AutorizacionPanelWeb $autorizacion, CascaraPanel $cascara): View
    {
        abort_unless($autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        /** @var SecUser $usuario */
        $usuario = $request->user('interno');
        $idRolActivo = (int) $request->session()->get('sec_rol_activo_id');

        return view('seguridad::pages.organizacion.index', array_merge(
            $cascara->para($usuario, $idRolActivo),
            $this->datosMock(),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function datosMock(): array
    {
        $completos = 5;
        $total = 6;

        return [
            'tabs' => [
                ['id' => 'ag-tab-organizacion', 'label' => __('seguridad.organizacion.tab_organizacion'), 'active' => true],
                ['id' => 'ag-tab-facturacion', 'label' => __('seguridad.organizacion.tab_facturacion')],
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
