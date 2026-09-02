{{--
    Contenido del PDF del reporte técnico por lote (espec §9, "Técnico — por
    lote"; HU-18, tarea 25): renderizado una sola vez, al firmar el acta
    (`GenerarReporteTecnico`) — nunca regenerado (ver runs/25.md). `$datos` lo
    arma `ArmarContenidoReporteTecnico`, testeado aparte porque un PDF
    comprimido no se puede inspeccionar por texto en un test (ver su
    docblock).

    Sin componentes Atomic Design del panel (mismo motivo que
    `pdf/acta.blade.php`: dompdf no procesa el CSS compilado por Vite, y un
    PDF descargado no reacciona a `[data-bs-theme]` — la invariante 11 de
    CLAUDE.md no tiene nada que proteger acá).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte técnico — Trabajo #{{ $trabajo->id }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: black; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        h2 { font-size: 14px; margin-top: 20px; margin-bottom: 4px; border-bottom: 1px solid gray; }
        p.subtitulo { color: dimgray; margin-top: 0; }
        p.nota { color: dimgray; font-style: italic; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid gray; padding: 6px 8px; text-align: left; }
        th { background-color: whitesmoke; width: 40%; }
        img.campo { max-width: 100%; margin-top: 8px; }
    </style>
</head>
<body>
    <h1>Reporte técnico</h1>
    <p class="subtitulo">Lote #{{ $trabajo->lote_id }} — Orden #{{ $trabajo->orden_id }} (aplicación {{ $trabajo->nro_aplicacion }})</p>

    <h2>Imagen del campo</h2>
    @if ($datos['imagen_campo_url'] !== null)
        <img class="campo" src="{{ $datos['imagen_campo_url'] }}" alt="Imagen del campo">
    @else
        <p>Sin imagen del campo registrada.</p>
    @endif

    <h2>Horas de la aplicación</h2>
    <table>
        <tr>
            <th>Inicio</th>
            <td>{{ optional($datos['hora_inicio'])->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
        <tr>
            <th>Fin</th>
            <td>{{ optional($datos['hora_fin'])->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
    </table>

    <h2>Acta de conformidad</h2>
    @if ($datos['acta'] !== null)
        <table>
            <tr>
                <th>Acta</th>
                <td>#{{ $datos['acta']['id'] }}</td>
            </tr>
            <tr>
                <th>Firmante</th>
                <td>{{ $datos['acta']['firmante'] ?? '—' }}</td>
            </tr>
            <tr>
                <th>Fecha de firma</th>
                <td>{{ optional($datos['acta']['fecha_firma'])->format('d/m/Y H:i') ?? '—' }}</td>
            </tr>
        </table>
    @else
        <p>Sin acta registrada.</p>
    @endif

    <h2>Resumen</h2>
    <table>
        <tr>
            <th>Hectáreas declaradas</th>
            <td>{{ $datos['resumen']['hectareas_declaradas'] }}</td>
        </tr>
        <tr>
            <th>Litros de caldo por hectárea</th>
            <td>{{ $datos['resumen']['litros_por_hectarea'] ?? '—' }}</td>
        </tr>
        <tr>
            <th>Cobertura</th>
            <td>{{ $datos['resumen']['cobertura'] ?? 'en curso' }}</td>
        </tr>
    </table>

    @if (count($datos['condiciones']) > 0)
        <h2>Condiciones de vuelo</h2>
        <table>
            <tr>
                <th>Sesión</th>
                <th>Viento (km/h)</th>
                <th>Temperatura (°C)</th>
                <th>Humedad (%)</th>
                <th>Resultado</th>
            </tr>
            @foreach ($datos['condiciones'] as $condicion)
                <tr>
                    <td>#{{ $condicion['sesion_id'] }}</td>
                    <td>{{ $condicion['viento_kmh'] }}</td>
                    <td>{{ $condicion['temperatura_c'] }}</td>
                    <td>{{ $condicion['humedad_pct'] }}</td>
                    <td>{{ $condicion['resultado'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($datos['superficie_no_aplicada'] !== null)
        <h2>Superficie no aplicada</h2>
        <table>
            <tr>
                <th>Hectáreas sin aplicar</th>
                <td>{{ $datos['superficie_no_aplicada']['hectareas'] }}</td>
            </tr>
            <tr>
                <th>Motivo</th>
                <td>{{ $datos['superficie_no_aplicada']['motivo'] ?? '—' }}</td>
            </tr>
        </table>
    @endif

    <h2>Incidencias</h2>
    @if (count($datos['incidencias']) > 0)
        <table>
            <tr>
                <th>Tipo</th>
                <th>Evidencia</th>
            </tr>
            @foreach ($datos['incidencias'] as $incidencia)
                <tr>
                    <td>{{ $incidencia['tipo'] }}</td>
                    <td>{{ $incidencia['evidencia_url'] }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <p>Sin incidencias registradas.</p>
    @endif

    @if (count($datos['sesiones_detalle']) > 0)
        <h2>Detalle de sesiones (relevo de piloto o cambio de dron)</h2>
        <table>
            <tr>
                <th>Sesión</th>
                <th>Piloto</th>
                <th>Dron</th>
                <th>Hectáreas</th>
                <th>Motivo de cierre</th>
            </tr>
            @foreach ($datos['sesiones_detalle'] as $sesion)
                <tr>
                    <td>#{{ $sesion['sesion_id'] }}</td>
                    <td>#{{ $sesion['piloto_id'] }}</td>
                    <td>{{ $sesion['dron_id'] !== null ? '#'.$sesion['dron_id'] : '—' }}</td>
                    <td>{{ $sesion['hectareas_declaradas'] }}</td>
                    <td>{{ $sesion['motivo_cierre'] ?? '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>Mezcla y dosis</h2>
    <p class="nota">{{ $datos['nota_mezcla'] }}</p>
</body>
</html>
