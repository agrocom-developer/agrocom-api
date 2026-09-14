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
        .captura { width: 48%; display: inline-block; vertical-align: top; margin: 8px 1% 0 0; }
        .captura img { width: 100%; border: 1px solid gray; }
        .captura span { display: block; color: dimgray; font-size: 11px; margin-top: 2px; }
    </style>
</head>
<body>
    <h1>Reporte técnico</h1>
    <p class="subtitulo">Lote #{{ $trabajo->lote_id }} — Orden #{{ $trabajo->orden_id }} (aplicación {{ $trabajo->nro_aplicacion }})</p>

    <h2>Imagen del campo</h2>
    {{-- `archivo_url` es una clave del bucket, no una URL: dompdf no puede
         resolverla. Se incrustan los bytes. Ver EvidenciaIncrustada. --}}
    @php($imagenCampo = \App\Dominios\Operaciones\Infraestructura\Http\Presentacion\EvidenciaIncrustada::dataUri($datos['imagen_campo_url']))
    @if ($imagenCampo !== null)
        <img class="campo" src="{{ $imagenCampo }}" alt="Imagen del campo">
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

    <h2>Capturas del control remoto</h2>
    {{-- Espec §4.3: cada sesión se cierra con su captura de RC. Es la
         evidencia de rendimiento del vuelo —hectáreas, tiempo, litros— que
         sostiene el número que se factura, así que va en el reporte que
         recibe el cliente. --}}
    @if (count($datos['capturas_rc']) > 0)
        @foreach ($datos['capturas_rc'] as $captura)
            @php($imagenCaptura = \App\Dominios\Operaciones\Infraestructura\Http\Presentacion\EvidenciaIncrustada::dataUri($captura['evidencia_url']))
            <div class="captura">
                @if ($imagenCaptura !== null)
                    <img src="{{ $imagenCaptura }}" alt="Captura del control remoto de la sesión {{ $captura['secuencia'] }}">
                @endif
                <span>Sesión {{ $captura['secuencia'] }} — {{ $captura['hectareas_declaradas'] }} ha</span>
            </div>
        @endforeach
    @else
        <p>Ninguna sesión de este trabajo registró su captura del control remoto.</p>
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

    <h2>Productos cargados en el caldo</h2>
    {{-- Espec §7 (HU-78, tarea 94, revierte CR-01): lo que el piloto
         transcribió al crear la aplicación — nunca dosis, orden de
         incorporación ni compatibilidad entre productos (§7.1 sigue
         vigente). --}}
    @if (count($datos['productos_mezcla']) > 0)
        <table>
            <tr>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Unidad</th>
            </tr>
            @foreach ($datos['productos_mezcla'] as $producto)
                <tr>
                    <td>{{ $producto['producto'] }}</td>
                    <td>{{ $producto['cantidad'] }}</td>
                    <td>{{ $producto['unidad'] }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <p class="nota">Sin productos registrados para este trabajo.</p>
    @endif
</body>
</html>
