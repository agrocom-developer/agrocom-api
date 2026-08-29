<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Demo;

/**
 * ============================== MOCK ==============================
 * Datos de DEMO del panel (quinta vuelta — maquetas aprobadas 4a/5a/5b/5c
 * de `docs/Login Agro Drones.dc.html`): las cifras, sesiones, pausas y stock
 * que las maquetas muestran, centralizados acá para que la demo se vea
 * idéntica a lo aprobado SIN hardcodear datos en las vistas (consigna §6 de
 * la tarea). Nada de esto es lógica de negocio: cuando existan los módulos
 * reales (Operaciones, Insumos, …), cada bloque se reemplaza por su caso de
 * uso real y esta clase desaparece.
 *
 * Lo que SÍ es copy fijo de pantalla (títulos, rótulos de columnas, textos
 * de las franjas) vive en `lang/` — acá solo viajan datos (nombres de lote,
 * horas, cifras) y claves/variantes que la vista resuelve.
 * ==================================================================
 */
final class DatosDemoPanel
{
    /**
     * Chrome del panel: chips del header y pie.
     *
     * @return array{campana: string, periodo: string, version: string}
     */
    public function chrome(): array
    {
        return [
            'campana' => 'Campaña 2026-B',
            'periodo' => 'Agosto 2026',
            'version' => 'V1.0',
        ];
    }

    /**
     * Contadores de pendientes de los ítems del menú (badge ámbar del nivel
     * 3), indexados por la clave `label` de `sec_menu`. `numero` es lo único
     * que pinta el badge en el sidebar (compacto); `texto` es la frase
     * completa que se lee en el tooltip — mismo criterio que tendrá el dato
     * real cuando exista el caso de uso (un contador + su descripción, no un
     * string ya formateado para la UI).
     *
     * @return array<string, array{numero: string, texto: string}>
     */
    public function badgesMenu(): array
    {
        return [
            'menu.operacion.items.programacion' => ['numero' => '3', 'texto' => 'Hoy · 3'],
            'menu.operacion.items.ordenes' => ['numero' => '12', 'texto' => '12 vigentes'],
            'menu.operacion.items.sesiones' => ['numero' => '6', 'texto' => '6 sin validar'],
            'menu.operacion.items.pausas' => ['numero' => '4', 'texto' => '4 sin causa'],
            'menu.comercial.items.reportes_cliente' => ['numero' => '2', 'texto' => '2 por enviar'],
            'menu.recursos.items.drones' => ['numero' => '1', 'texto' => '1 en taller'],
            'menu.mantenimiento.items.stock' => ['numero' => '2', 'texto' => '2 bajo mínimo'],
            'menu.financiero.items.devengos' => ['numero' => '18.490', 'texto' => 'Bs 18.490'],
        ];
    }

    /**
     * Notificaciones de la campana (título/hora son datos de demo, no copy).
     *
     * @return list<array{icon: string, title: string, time: string, unread: bool}>
     */
    public function notificaciones(): array
    {
        return [
            ['icon' => 'task_alt', 'title' => 'Sesión del Lote 12 validada', 'time' => 'hace 5 minutos', 'unread' => true],
            ['icon' => 'photo_camera', 'title' => '2 sesiones sin captura del RC', 'time' => 'hace 40 minutos', 'unread' => true],
            ['icon' => 'inventory_2', 'title' => 'Boquilla XR-110 bajo mínimo en Base Warnes', 'time' => 'hace 2 horas', 'unread' => true],
        ];
    }

    /**
     * Bajada del título del dashboard (la fecha es parte de la demo).
     */
    public function fechaBajada(): string
    {
        return 'Viernes 28 de agosto';
    }

    /**
     * Franja de ventana volable (maqueta 4a).
     *
     * @return array{horario: string, detalle: string}
     */
    public function ventanaVolable(): array
    {
        return [
            'horario' => '05:40 – 10:20',
            'detalle' => 'Viento 8 km/h, humedad 71%. Tres sesiones programadas; una requiere autorización firmada por velocidad fuera de rango.',
        ];
    }

    /**
     * Distribución de sesiones del período por estado (barra apilada CSS
     * puro, sin librería; auditoría visual externa obs. #5: reemplazó al
     * donut original, mismo shape). 42 validadas + 6 restantes repartidas
     * entre los otros tres estados visibles en la programación de hoy.
     * `estado` es la CLAVE de `operaciones.sesion.estado.*` (se
     * resuelve en la vista, mismo criterio que `_tabla-sesiones.blade.php`
     * — nunca el texto ya traducido acá, ADR 0013). `tono` reutiliza el
     * mismo vocabulario que `variante` en {@see sesiones()}
     * (success|warning|info|neutral).
     *
     * @return array{total: int, segmentos: list<array{estado: string, valor: int, pct: float, tono: string}>}
     */
    public function distribucionSesiones(): array
    {
        return [
            'total' => 48,
            'segmentos' => [
                ['estado' => 'validada', 'valor' => 42, 'pct' => 87.5, 'tono' => 'success'],
                ['estado' => 'programada', 'valor' => 3, 'pct' => 6.25, 'tono' => 'neutral'],
                ['estado' => 'sin_evidencia', 'valor' => 2, 'pct' => 4.17, 'tono' => 'warning'],
                ['estado' => 'en_vuelo', 'valor' => 1, 'pct' => 2.08, 'tono' => 'info'],
            ],
        ];
    }

    /**
     * Hectáreas aplicadas por día del período (Fase 4 — gráfica de área).
     * 10 puntos a lo largo de agosto, suma 1.284 ha — mismo total que
     * mostraba el KPI "Hectáreas aplicadas" antes de retirarse de esta
     * página (sigue viviendo en `stat-card`, para páginas futuras).
     *
     * @return array{fechas: list<string>, valores: list<int>}
     */
    public function hectareasPorDia(): array
    {
        return [
            'fechas' => ['01/08', '04/08', '07/08', '10/08', '13/08', '16/08', '19/08', '22/08', '25/08', '28/08'],
            'valores' => [98, 145, 110, 160, 135, 90, 150, 125, 140, 131],
        ];
    }

    /**
     * Avance de la meta de hectáreas del mes (Fase 4 — gráfica radialBar).
     *
     * @return array{valor: float, meta: float, pct: float}
     */
    public function avanceMeta(): array
    {
        return [
            'valor' => 1284,
            'meta' => 1500,
            'pct' => 85.6,
        ];
    }

    /**
     * Detalle de clientes (Fase 5 — actividad reciente + estado de contrato
     * combinados en una sola sección de Resumen). `contrato.estado` usa los
     * valores REALES de `App\Dominios\Comercial\Dominio\EstadoContrato`
     * como string literal documentado — Seguridad no importa el enum de
     * Comercial (cruzar así violaría el aislamiento entre módulos de la
     * arquitectura modular, ADR 0003); si `EstadoContrato` cambia sus
     * valores, este mock queda desincronizado hasta que el caso de uso real
     * lo reemplace.
     *
     * @return list<array{
     *     cliente: string, nit: string,
     *     contrato: array{estado: string, hectareasContratadas: string, hectareasEjecutadas: string, pctEjecutado: float, fechaFin: string, diasParaVencer: int},
     *     actividad: array{sesionesPeriodo: int, hectareasPeriodo: string, ultimaSesion: string}
     * }>
     */
    public function detalleClientes(): array
    {
        return [
            [
                'cliente' => 'Agropecuaria San Marcos S.R.L.',
                'nit' => '1023456789',
                'contrato' => ['estado' => 'vigente', 'hectareasContratadas' => '450 ha', 'hectareasEjecutadas' => '312,5 ha', 'pctEjecutado' => 69.4, 'fechaFin' => '30/11/2026', 'diasParaVencer' => 94],
                'actividad' => ['sesionesPeriodo' => 8, 'hectareasPeriodo' => '160 ha', 'ultimaSesion' => 'Hoy, 07:10'],
            ],
            [
                'cliente' => 'El Carmen Agroindustrial S.A.',
                'nit' => '1078451234',
                'contrato' => ['estado' => 'vigente', 'hectareasContratadas' => '620 ha', 'hectareasEjecutadas' => '598 ha', 'pctEjecutado' => 96.5, 'fechaFin' => '15/09/2026', 'diasParaVencer' => 18],
                'actividad' => ['sesionesPeriodo' => 6, 'hectareasPeriodo' => '112 ha', 'ultimaSesion' => 'Hoy, 08:30'],
            ],
            [
                'cliente' => 'Grupo Santa Rosa',
                'nit' => '1055987654',
                'contrato' => ['estado' => 'vigente', 'hectareasContratadas' => '300 ha', 'hectareasEjecutadas' => '130 ha', 'pctEjecutado' => 43.3, 'fechaFin' => '28/02/2027', 'diasParaVencer' => 184],
                'actividad' => ['sesionesPeriodo' => 3, 'hectareasPeriodo' => '130 ha', 'ultimaSesion' => 'Hoy, 11:15'],
            ],
            [
                'cliente' => 'La Loma Cultivos',
                'nit' => '1099112233',
                'contrato' => ['estado' => 'finalizado', 'hectareasContratadas' => '200 ha', 'hectareasEjecutadas' => '200 ha', 'pctEjecutado' => 100.0, 'fechaFin' => '20/08/2026', 'diasParaVencer' => -8],
                'actividad' => ['sesionesPeriodo' => 0, 'hectareasPeriodo' => '0 ha', 'ultimaSesion' => '22/08, 09:15'],
            ],
            [
                'cliente' => 'Hacienda Warnes Sur',
                'nit' => '1044556677',
                'contrato' => ['estado' => 'borrador', 'hectareasContratadas' => '500 ha', 'hectareasEjecutadas' => '0 ha', 'pctEjecutado' => 0.0, 'fechaFin' => '31/01/2027', 'diasParaVencer' => 155],
                'actividad' => ['sesionesPeriodo' => 0, 'hectareasPeriodo' => '0 ha', 'ultimaSesion' => 'Sin sesiones'],
            ],
        ];
    }

    /**
     * Programación de hoy (maqueta 4a — 5 sesiones con el vocabulario de
     * estados de la maqueta; el label de cada estado sale de
     * `lang/es/operaciones.php`).
     *
     * @return list<array{hora: string, lote: string, piloto: string, dron: string, ha: string, estado: string, variante: string}>
     */
    public function sesiones(): array
    {
        return [
            ['hora' => '05:45', 'lote' => 'Lote 12 — San Marcos', 'piloto' => 'R. Vaca', 'dron' => 'T50 · AG-04', 'ha' => '86 ha', 'estado' => 'validada', 'variante' => 'success'],
            ['hora' => '07:10', 'lote' => 'Lote 12 — San Marcos', 'piloto' => 'R. Vaca', 'dron' => 'T50 · AG-04', 'ha' => '74 ha', 'estado' => 'sin_evidencia', 'variante' => 'warning'],
            ['hora' => '08:30', 'lote' => 'Lote 3 — El Carmen', 'piloto' => 'M. Ordóñez', 'dron' => 'T70 · AG-07', 'ha' => '112 ha', 'estado' => 'en_vuelo', 'variante' => 'info'],
            ['hora' => '09:50', 'lote' => 'Lote 8 — El Carmen', 'piloto' => 'J. Peña', 'dron' => 'T30 · AG-02', 'ha' => '48 ha', 'estado' => 'programada', 'variante' => 'neutral'],
            ['hora' => '11:15', 'lote' => 'Lote 1 — Santa Rosa', 'piloto' => 'M. Ordóñez', 'dron' => 'T100 · AG-09', 'ha' => '130 ha', 'estado' => 'programada', 'variante' => 'neutral'],
        ];
    }

    /**
     * Pausas por causa (maqueta 4a — 5 causas; `pct` es el ancho de barra de
     * la maqueta, `tono` la variante de color de la barra).
     *
     * @return array{total: string, causas: list<array{causa: string, horas: string, pct: int, tono: string}>}
     */
    public function pausas(): array
    {
        return [
            'total' => '10 h 30 · MES',
            'causas' => [
                ['causa' => 'Cliente sin agua o químico', 'horas' => '4 h 20', 'pct' => 78, 'tono' => 'accent'],
                ['causa' => 'Clima fuera de rango', 'horas' => '2 h 40', 'pct' => 48, 'tono' => 'info'],
                ['causa' => 'Falla de equipo', 'horas' => '1 h 10', 'pct' => 21, 'tono' => 'danger'],
                ['causa' => 'Logística y traslados', 'horas' => '0 h 50', 'pct' => 15, 'tono' => 'neutral'],
                ['causa' => 'Sin causa asignada', 'horas' => '1 h 30', 'pct' => 27, 'tono' => 'unassigned'],
            ],
        ];
    }

    /**
     * Stock bajo mínimo (maqueta 4a — 3 ítems; `tono` colorea el nivel).
     *
     * @return list<array{item: string, nivel: string, tono: string}>
     */
    public function stockBajoMinimo(): array
    {
        return [
            ['item' => 'Boquilla XR-110 · Base Warnes', 'nivel' => '4 / 20', 'tono' => 'danger'],
            ['item' => 'Batería T50 · Base Warnes', 'nivel' => '2 / 8', 'tono' => 'danger'],
            ['item' => 'Filtro de tanque · Base Montero', 'nivel' => '9 / 15', 'tono' => 'accent'],
        ];
    }

    /**
     * Alerta de sesiones sin captura del RC (pie del Resumen).
     *
     * @return array{cantidad: int}
     */
    public function alertaRc(): array
    {
        return ['cantidad' => 2];
    }
}
