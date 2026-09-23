<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Presentacion;

use App\Dominios\Comercial\Contratos\LecturaContadoresPanel as LecturaContadoresPanelComercial;
use App\Dominios\Finanzas\Contratos\LecturaContadoresPanel as LecturaContadoresPanelFinanzas;
use App\Dominios\Inventario\Contratos\LecturaContadoresPanel as LecturaContadoresPanelInventario;
use App\Dominios\Notificaciones\Contratos\LecturaNotificaciones;
use App\Dominios\Notificaciones\Contratos\NotificacionPanel;
use App\Dominios\Operaciones\Contratos\LecturaContadoresPanel as LecturaContadoresPanelOperaciones;
use App\Dominios\Operaciones\Contratos\LecturaPanelOperaciones;
use App\Dominios\Seguridad\Aplicacion\ListarRolesDisponibles;
use App\Dominios\Seguridad\Aplicacion\ObtenerMenuPorRolActivo;
use App\Dominios\Seguridad\Dominio\TemaPreferencia;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserPreferencia;
use Illuminate\Support\Carbon;

/**
 * Datos de cáscara que `templates/panel-layout` + `templates/panel-shell`
 * esperan, resueltos una sola vez para cualquier página del panel (dashboard,
 * usuarios, organización): árbol de menú del ROL ACTIVO (nunca la unión —
 * CLAUDE.md invariante 10), roles disponibles, rol activo legible, tema
 * persistido, el chrome del header y los badges del menú (TE-14, tarea 60 —
 * contadores reales de cada módulo dueño, ver {@see menuBadges()}).
 *
 * Desde la tarea 67 no queda nada de maqueta acá: las notificaciones de la
 * campana son las alertas por excepción reales (HU-19) y la versión del pie
 * sale de `config('app.version')`. Desde la tarea 141 la campana mezcla dos
 * fuentes (ver {@see notificaciones()}): esas alertas técnicas y los avisos
 * de flujo de negocio del módulo `Notificaciones`.
 *
 * El 9/9/2026 el header se quedó además sin contexto de negocio, mirando el
 * panel andando: cayeron el chip de campaña (ADR 0015 — la campaña es del
 * cliente, se elige dentro del cliente o del contrato, nunca es un contexto
 * ambiente de la sesión) y el selector de período, que mostraba el mes en
 * curso sin filtrar nada y se leía justamente como si fuera esa campaña.
 *
 * Existe para que cada controlador de página no re-arme (ni desincronice)
 * esta misma docena de props — los controladores siguen siendo adaptadores
 * delgados (ADR 0008) que agregan solo los datos propios de su pantalla.
 */
final class CascaraPanel
{
    private const ALERTAS_NOTIFICACION = 5;

    /** Tope de avisos que muestra la campana, sumadas las dos fuentes. */
    private const MAXIMO_EN_CAMPANA = 10;

    public function __construct(
        private readonly ObtenerMenuPorRolActivo $obtenerMenu,
        private readonly ListarRolesDisponibles $listarRolesDisponibles,
        private readonly LecturaContadoresPanelOperaciones $contadoresOperaciones,
        private readonly LecturaContadoresPanelComercial $contadoresComercial,
        private readonly LecturaPanelOperaciones $panelOperaciones,
        private readonly LecturaContadoresPanelInventario $contadoresInventario,
        private readonly LecturaContadoresPanelFinanzas $contadoresFinanzas,
        private readonly LecturaNotificaciones $notificacionesDelMotor,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function para(SecUser $usuario, int $idRolActivo): array
    {
        $roles = $this->listarRolesDisponibles->ejecutar($usuario);
        $rolActivo = $roles->firstWhere('id', $idRolActivo);

        $preferencia = SecUserPreferencia::query()->where('user_id', $usuario->id)->first();
        $tema = ($preferencia->tema ?? TemaPreferencia::Claro)->atributoBootstrap();

        return [
            'menu' => $this->obtenerMenu->ejecutar($usuario, $idRolActivo),
            'roles' => $roles,
            'rolActivoId' => $idRolActivo,
            'activeRoleLabel' => $rolActivo !== null ? PresentadorRol::nombreLegible($rolActivo) : null,
            'userName' => $usuario->name,
            'tema' => $tema,
            // Tarea 63: zona horaria IANA que informó el navegador (en el
            // login o, si la sesión venía sin ella, apenas carga el panel).
            // Nunca se inventa un default acá: `null` es "todavía no la
            // fijó", y el badge del pie nace vacío hasta que el JS la
            // detecta y la persiste (molecules/timezone-badge).
            'zonaHoraria' => $preferencia->zona_horaria ?? null,
            'notifications' => $this->notificaciones($usuario, $idRolActivo),
            'menuBadges' => $this->menuBadges($usuario),
            'version' => config('app.version'),
        ];
    }

    /**
     * Contadores reales de pendientes de los ítems del menú (badge ámbar del
     * nivel 3), indexados por la clave `label` de `sec_menu` (TE-14, tarea
     * 60 — contadores reales, no maqueta). `numero` es lo
     * único que pinta el badge en el sidebar (compacto); `texto` es la frase
     * completa del tooltip.
     *
     * Dos ítems de la maqueta original quedan sin badge porque ningún
     * módulo tiene un dato real detrás (ver `docs/gestion/plan_sprints.md`
     * Sprint 12 §255): `operacion.items.programacion` (no existe el
     * concepto de sesión programada) y `recursos.items.drones` (`ope_drones`
     * no registra estado de taller). `comercial.items.reportes_cliente`
     * (el tercero de esa lista) se retiró del catálogo en la tarea 62 (fuga
     * 3, `SecMenuSeeder`) — nunca llegó a tener pantalla propia.
     * `panel-layout.blade.php` ya tolera la ausencia de una clave
     * (`$menuBadges[$label] ?? null`).
     *
     * Refactor de menú (17/9/2026): "Pausas" perdió su ítem propio
     * (`SecMenuSeeder`, absorbido por "Seguimiento de vuelos") y con él su
     * badge — `LecturaContadoresPanel::pausasDelMes()` sigue existiendo (no
     * se sabe si otra pantalla lo va a necesitar) pero ya no se llama desde
     * acá. El badge de "Sesiones" se re-etiqueta a
     * `menu.operacion.items.seguimiento_vuelos`, la clave nueva de ese ítem.
     *
     * @return array<string, array{numero: string, texto: string}>
     */
    private function menuBadges(SecUser $usuario): array
    {
        $badges = [];

        $ordenesVigentes = $this->contadoresOperaciones->ordenesVigentes();
        if ($ordenesVigentes > 0) {
            $badges['menu.operacion.items.ordenes'] = [
                'numero' => (string) $ordenesVigentes,
                'texto' => __('seguridad.respuestas.badge_ordenes_vigentes', ['cantidad' => $ordenesVigentes]),
            ];
        }

        // Contratos «En ejecución» (`vigente`): los que hoy tienen la operación en marcha.
        $contratosEnEjecucion = $this->contadoresComercial->contratosEnEjecucion();
        if ($contratosEnEjecucion > 0) {
            $badges['menu.comercial.items.contratos'] = [
                'numero' => (string) $contratosEnEjecucion,
                'texto' => __('seguridad.respuestas.badge_contratos_en_ejecucion', ['cantidad' => $contratosEnEjecucion]),
            ];
        }

        $sesionesPendientes = $this->contadoresOperaciones->sesionesPendientesValidacion();
        if ($sesionesPendientes > 0) {
            $badges['menu.operacion.items.seguimiento_vuelos'] = [
                'numero' => (string) $sesionesPendientes,
                'texto' => __('seguridad.respuestas.badge_sesiones_pendientes', ['cantidad' => $sesionesPendientes]),
            ];
        }

        $stockBajoMinimo = $this->contadoresInventario->stockBajoMinimo();
        if ($stockBajoMinimo > 0) {
            $badges['menu.mantenimiento.items.stock'] = [
                'numero' => (string) $stockBajoMinimo,
                'texto' => __('seguridad.respuestas.badge_stock_bajo_minimo', ['cantidad' => $stockBajoMinimo]),
            ];
        }

        if ($usuario->persona_id !== null) {
            $devengado = $this->contadoresFinanzas->devengadoDelMes($usuario->persona_id);
            if ((float) $devengado > 0) {
                $devengadoFormateado = number_format((float) $devengado, 0, ',', '.');
                $badges['menu.financiero.items.devengos'] = [
                    'numero' => $devengadoFormateado,
                    'texto' => __('seguridad.dashboard.liquidacion_total', ['monto' => $devengadoFormateado]),
                ];
            }
        }

        return $badges;
    }

    /**
     * Campana del header: mezcla DOS fuentes que conviven (tarea 141, ADR 0025
     * punto 7), en vez de absorber una en la otra.
     *
     * - Los avisos de flujo de negocio del módulo `Notificaciones`, repartidos
     *   por cuenta: contrato creado, orden de trabajo creada, trabajo cerrado.
     *   Solo los de quien mira (su id sale de la sesión, nunca de la petición).
     *   Cada uno lleva a `panel.notificaciones.abrir`, que lo marca leído y
     *   resuelve el destino contra el rol activo.
     * - Las alertas por excepción reales (HU-19), como antes: gateadas por
     *   `operaciones.alerta.ver` contra el ROL ACTIVO (un rol que no puede
     *   entrar a `/panel/alertas` tampoco las lee por la campana), y ahora con
     *   enlace a esa pantalla — hasta acá eran texto plano.
     *
     * La lista prioriza lo no leído: entran primero todos los avisos sin leer
     * (hasta el tope) y se completa con los leídos más recientes, del más
     * nuevo al más viejo. Así el badge de la campana —que cuenta lo no leído de
     * la lista que recibe— sigue siendo exacto hasta «9+».
     *
     * @return list<array{id: int|null, icon: string, title: string, time: string, unread: bool, href: string}>
     */
    private function notificaciones(SecUser $usuario, int $idRolActivo): array
    {
        $candidatas = array_map(fn (NotificacionPanel $aviso): array => [
            'id' => $aviso->id,
            'icon' => $aviso->icono,
            'title' => $aviso->titulo,
            'momento' => Carbon::parse($aviso->creadaEn),
            'unread' => ! $aviso->leida,
            'href' => route('panel.notificaciones.abrir', $aviso->id),
        ], $this->notificacionesDelMotor->recientesDe($usuario->id, self::MAXIMO_EN_CAMPANA));

        if ($usuario->tienePermisoEnRol('operaciones.alerta.ver', $idRolActivo)) {
            $hrefAlertas = route('panel.alertas.index');

            foreach ($this->panelOperaciones->alertasRecientes(self::ALERTAS_NOTIFICACION) as $alerta) {
                $candidatas[] = [
                    'id' => null,
                    'icon' => 'warning',
                    'title' => $alerta->mensaje,
                    'momento' => Carbon::parse($alerta->creadaEn),
                    'unread' => $alerta->pendiente,
                    'href' => $hrefAlertas,
                ];
            }
        }

        $masNuevoPrimero = static fn (array $a, array $b): int => $b['momento'] <=> $a['momento'];

        usort($candidatas, $masNuevoPrimero);

        $sinLeer = array_values(array_filter($candidatas, static fn (array $item): bool => $item['unread']));
        $leidas = array_values(array_filter($candidatas, static fn (array $item): bool => ! $item['unread']));

        $elegidas = [
            ...array_slice($sinLeer, 0, self::MAXIMO_EN_CAMPANA),
            ...array_slice($leidas, 0, max(0, self::MAXIMO_EN_CAMPANA - count($sinLeer))),
        ];

        usort($elegidas, $masNuevoPrimero);

        return array_map(static fn (array $item): array => [
            'id' => $item['id'],
            'icon' => $item['icon'],
            'title' => $item['title'],
            'time' => $item['momento']->diffForHumans(),
            'unread' => $item['unread'],
            'href' => $item['href'],
        ], $elegidas);
    }
}
