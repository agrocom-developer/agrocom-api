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
     * Los 4 KPI del período (maqueta 4a; en móvil el primero es el
     * protagonista y el cuarto se pliega a su bajada — maqueta 5b).
     * `estado` (sexta vuelta parte 2, lenguaje visual de
     * docs/ganadosoft-dashboard.html) gobierna el contenedor del ícono y,
     * solo para `warning`/`danger`, la barra izquierda de atención —
     * independiente de `pieTono`, que solo colorea la línea de pie.
     *
     * @return list<array{clave: string, label: string, icono: string, valor: string, sufijo: ?string, pie: string, pieIcono: ?string, pieTono: string, estado: ?string}>
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
                'estado' => 'success',
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
                'estado' => 'warning',
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
                'estado' => null,
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
                'estado' => 'success',
            ],
        ];
    }

    /**
     * Distribución de sesiones del período por estado (Fase 4 — gráfica
     * mock, barra apilada CSS puro, sin librería; auditoría visual externa
     * obs. #5: reemplazó al donut original, mismo shape). Consistente con el KPI
     * "Sesiones validadas 42/48" de arriba: 42 validadas + 6 restantes
     * repartidas entre los otros tres estados visibles en la programación
     * de hoy. `estado` es la CLAVE de `operaciones.sesion.estado.*` (se
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
     * `lang/es/operaciones.php`). `rcEstado` y `detalle` (Fase 6 — drill-down
     * del tab Sesiones) son consumidos SOLO por `_tabla-sesiones` cuando
     * `$conRc` es true; la tabla condensada del tab Resumen los ignora.
     *
     * @return list<array{hora: string, lote: string, piloto: string, dron: string, ha: string, estado: string, variante: string, rcEstado: string, detalle: array{orden: string, mezcla: string, preparadoPor: string, condiciones: string, pausas: list<array{causa: string, duracion: string}>}}>
     */
    public function sesiones(): array
    {
        return [
            [
                'hora' => '05:45', 'lote' => 'Lote 12 — San Marcos', 'piloto' => 'R. Vaca', 'dron' => 'T50 · AG-04', 'ha' => '86 ha', 'estado' => 'validada', 'variante' => 'success',
                'rcEstado' => 'capturado',
                'detalle' => [
                    'orden' => 'OT-1042', 'mezcla' => 'Fungicida XR + adherente · 12 L/ha', 'preparadoPor' => 'L. Cardozo',
                    'condiciones' => 'Viento 6 km/h · Humedad 68% · Temp. 24°C',
                    'pausas' => [],
                ],
            ],
            [
                'hora' => '07:10', 'lote' => 'Lote 12 — San Marcos', 'piloto' => 'R. Vaca', 'dron' => 'T50 · AG-04', 'ha' => '74 ha', 'estado' => 'sin_evidencia', 'variante' => 'warning',
                'rcEstado' => 'sin_evidencia',
                'detalle' => [
                    'orden' => 'OT-1042', 'mezcla' => 'Fungicida XR + adherente · 12 L/ha', 'preparadoPor' => 'L. Cardozo',
                    'condiciones' => 'Viento 9 km/h · Humedad 65% · Temp. 26°C',
                    'pausas' => [['causa' => 'Cliente sin agua o químico', 'duracion' => '0 h 20']],
                ],
            ],
            [
                'hora' => '08:30', 'lote' => 'Lote 3 — El Carmen', 'piloto' => 'M. Ordóñez', 'dron' => 'T70 · AG-07', 'ha' => '112 ha', 'estado' => 'en_vuelo', 'variante' => 'info',
                'rcEstado' => 'no_aplica',
                'detalle' => [
                    'orden' => 'OT-1043', 'mezcla' => 'Herbicida selectivo · 8 L/ha', 'preparadoPor' => 'L. Cardozo',
                    'condiciones' => 'Viento 7 km/h · Humedad 60% · Temp. 27°C',
                    'pausas' => [],
                ],
            ],
            [
                'hora' => '09:50', 'lote' => 'Lote 8 — El Carmen', 'piloto' => 'J. Peña', 'dron' => 'T30 · AG-02', 'ha' => '48 ha', 'estado' => 'programada', 'variante' => 'neutral',
                'rcEstado' => 'no_aplica',
                'detalle' => [
                    'orden' => 'OT-1044', 'mezcla' => 'Insecticida biológico · 6 L/ha', 'preparadoPor' => 'V. Suárez',
                    'condiciones' => 'Pendiente — sesión aún no iniciada',
                    'pausas' => [],
                ],
            ],
            [
                'hora' => '11:15', 'lote' => 'Lote 1 — Santa Rosa', 'piloto' => 'M. Ordóñez', 'dron' => 'T100 · AG-09', 'ha' => '130 ha', 'estado' => 'programada', 'variante' => 'neutral',
                'rcEstado' => 'no_aplica',
                'detalle' => [
                    'orden' => 'OT-1045', 'mezcla' => 'Fungicida XR + adherente · 12 L/ha', 'preparadoPor' => 'V. Suárez',
                    'condiciones' => 'Pendiente — sesión aún no iniciada',
                    'pausas' => [],
                ],
            ],
        ];
    }

    /**
     * Eventos individuales de pausa (Fase 6 — drill-down del tab Pausas,
     * bajo el agregado por causa de {@see pausas()}). Mismo período.
     *
     * @return list<array{hora: string, lote: string, causa: string, duracion: string, tono: string}>
     */
    public function pausasEventos(): array
    {
        return [
            ['hora' => '07:10', 'lote' => 'Lote 12 — San Marcos', 'causa' => 'Cliente sin agua o químico', 'duracion' => '0 h 20', 'tono' => 'accent'],
            ['hora' => '09:15', 'lote' => 'Lote 5 — La Loma', 'causa' => 'Clima fuera de rango', 'duracion' => '0 h 35', 'tono' => 'info'],
            ['hora' => '10:40', 'lote' => 'Lote 3 — El Carmen', 'causa' => 'Falla de equipo', 'duracion' => '1 h 10', 'tono' => 'danger'],
            ['hora' => '13:05', 'lote' => 'Lote 8 — El Carmen', 'causa' => 'Logística y traslados', 'duracion' => '0 h 50', 'tono' => 'neutral'],
            ['hora' => '14:20', 'lote' => 'Lote 1 — Santa Rosa', 'causa' => 'Sin causa asignada', 'duracion' => '1 h 30', 'tono' => 'unassigned'],
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
