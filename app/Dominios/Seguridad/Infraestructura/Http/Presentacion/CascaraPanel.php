<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Presentacion;

use App\Dominios\Notificaciones\Contratos\LecturaNotificaciones;
use App\Dominios\Notificaciones\Contratos\NotificacionPanel;
use App\Dominios\Seguridad\Aplicacion\ListarRolesDisponibles;
use App\Dominios\Seguridad\Aplicacion\ObtenerMenuPorRolActivo;
use App\Dominios\Seguridad\Dominio\TemaPreferencia;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserPreferencia;
use App\Dominios\Solicitudes\Contratos\LecturaContextoSolicitudes;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Datos de cáscara que `templates/panel-layout` + `templates/panel-shell`
 * esperan, resueltos una sola vez para cualquier página del panel (dashboard,
 * usuarios, organización): árbol de menú del ROL ACTIVO (nunca la unión —
 * CLAUDE.md invariante 10), roles disponibles, rol activo legible, tema
 * persistido, el chrome del header y los badges del menú (TE-14, tarea 60 —
 * contadores reales de cada módulo dueño, ver {@see menuBadges()}).
 *
 * Las notificaciones de la campana son los avisos de flujo de negocio del
 * módulo `Notificaciones` y la versión del pie sale de `config('app.version')`.
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
    /** Tope de avisos que muestra la campana. */
    private const MAXIMO_EN_CAMPANA = 3;

    public function __construct(
        private readonly ObtenerMenuPorRolActivo $obtenerMenu,
        private readonly ListarRolesDisponibles $listarRolesDisponibles,
        private readonly LecturaContextoSolicitudes $solicitudes,
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
     * Contadores reales de pendientes de los ítems del menú (badge del nivel
     * 3), indexados por la clave `label` de `sec_menu`. `numero` es lo único
     * que pinta el badge en el sidebar (compacto); `texto` es la frase completa
     * del tooltip. `panel-layout.blade.php` tolera la ausencia de una clave
     * (`$menuBadges[$label] ?? null`).
     *
     * @return array<string, array{numero: string, texto: string}>
     */
    private function menuBadges(SecUser $usuario): array
    {
        $badges = [];

        // Solicitudes que esperan proveedor: abiertas, con ofertas o expiradas.
        $conteo = $this->solicitudes->conteoPorEstado();
        $esperandoProveedor = ($conteo['abierta'] ?? 0) + ($conteo['con_ofertas'] ?? 0) + ($conteo['expirada'] ?? 0);
        if ($esperandoProveedor > 0) {
            $badges['menu.solicitudes.items.solicitudes'] = [
                'numero' => (string) $esperandoProveedor,
                'texto' => __('seguridad.respuestas.badge_solicitudes_esperando', ['cantidad' => $esperandoProveedor]),
            ];
        }

        return $badges;
    }

    /**
     * Campana del header: los avisos de flujo de negocio del módulo
     * `Notificaciones`, repartidos por cuenta (ofertas, adjudicaciones,
     * disputas, aprobaciones). Solo los de quien mira (su id sale de la
     * sesión, nunca de la petición). Cada uno lleva a
     * `panel.notificaciones.abrir`, que lo marca leído y resuelve el destino
     * contra el rol activo.
     *
     * Cuáles entran y en qué orden lo decide {@see CampanaDeAvisos}: primero
     * todo lo no leído, después los leídos más recientes. Así el badge de la
     * campana —que cuenta lo no leído de la lista que recibe— sigue siendo
     * exacto hasta «9+».
     *
     * @return list<array{id: int, icon: string, title: string, time: string, unread: bool, href: string}>
     */
    private function notificaciones(SecUser $usuario, int $idRolActivo): array
    {
        try {
            $delMotor = $this->notificacionesDelMotor->recientesDe($usuario->id, self::MAXIMO_EN_CAMPANA);
        } catch (Throwable $excepcion) {
            // La campana es un widget secundario: si falla la lectura de avisos (p. ej. un
            // entorno al que le falta la migración), se reporta y el panel sigue — un
            // aviso roto no tira todas las páginas.
            report($excepcion);
            $delMotor = [];
        }

        $candidatas = array_map(fn (NotificacionPanel $aviso): array => [
            'id' => $aviso->id,
            'icon' => $aviso->icono,
            'title' => $aviso->titulo,
            'momento' => Carbon::parse($aviso->creadaEn),
            'unread' => ! $aviso->leida,
            'href' => route('panel.notificaciones.abrir', $aviso->id),
        ], $delMotor);

        return CampanaDeAvisos::elegir($candidatas, self::MAXIMO_EN_CAMPANA);
    }
}
