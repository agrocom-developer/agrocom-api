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
     * 2), indexados por la clave `label` de `sec_menu`.
     *
     * @return array<string, string>
     */
    public function badgesMenu(): array
    {
        return [
            'menu.operacion.items.programacion' => 'Hoy · 3',
            'menu.operacion.items.ordenes' => '12 vigentes',
            'menu.operacion.items.sesiones' => '6 sin validar',
            'menu.operacion.items.pausas' => '4 sin causa',
            'menu.comercial.items.reportes_cliente' => '2 por enviar',
            'menu.recursos.items.drones' => '1 en taller',
            'menu.mantenimiento.items.stock' => '2 bajo mínimo',
            'menu.financiero.items.devengos' => 'Bs 18.490',
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
     * Los 4 KPI del período (maqueta 4a; en móvil el primero es el
     * protagonista y el cuarto se pliega a su bajada — maqueta 5b).
     *
     * @return list<array{clave: string, label: string, icono: string, valor: string, sufijo: ?string, pie: string, pieIcono: ?string, pieTono: string}>
     */
    public function kpis(): array
    {
        return [
            [
                'clave' => 'hectareas',
                'label' => 'Hectáreas aplicadas',
                'icono' => 'landscape',
                'valor' => '1.284',
                'sufijo' => 'ha',
                'pie' => '+12% vs. julio',
                'pieIcono' => 'trending_up',
                'pieTono' => 'success',
            ],
            [
                'clave' => 'validadas',
                'label' => 'Sesiones validadas',
                'labelCorto' => 'Validadas',
                'icono' => 'task_alt',
                'valor' => '42',
                'sufijo' => '/ 48',
                'pie' => '6 pendientes de validar',
                'pieIcono' => 'pending',
                'pieTono' => 'warning',
            ],
            [
                'clave' => 'devengo',
                'label' => 'Devengo del período',
                'labelCorto' => 'Devengo',
                'icono' => 'payments',
                'valor' => 'Bs 18.490',
                'sufijo' => null,
                'pie' => '7 personas · corte 31/08',
                'pieIcono' => 'group',
                'pieTono' => 'muted',
            ],
            [
                'clave' => 'costo_ha',
                'label' => 'Costo por hectárea',
                'icono' => 'speed',
                'valor' => 'Bs 24,30',
                'sufijo' => null,
                'pie' => '-3% con la flota T70',
                'pieIcono' => 'trending_down',
                'pieTono' => 'success',
            ],
        ];
    }

    /**
     * KPI protagonista + secundarios del móvil (maqueta 5b): mismos datos,
     * recompuestos como los muestra esa maqueta.
     *
     * @return array{label: string, valor: string, pie: string}
     */
    public function kpiProtagonistaMovil(): array
    {
        return [
            'label' => 'Hectáreas aplicadas · agosto',
            'valor' => '1.284 ha',
            'pie' => '+12% vs. julio · Bs 24,30 por ha',
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

    /**
     * Franja de la pestaña Pausas: horas sin causa asignada.
     */
    public function pausasSinCausa(): string
    {
        return '1 h 30';
    }
}
