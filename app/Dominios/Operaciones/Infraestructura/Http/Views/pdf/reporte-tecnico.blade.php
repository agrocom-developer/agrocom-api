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
    <title>{{ __('operaciones.pdf.reporte_tecnico.titulo_documento', ['id' => $trabajo->id]) }}</title>
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
    <h1>{{ __('operaciones.pdf.reporte_tecnico.titulo') }}</h1>
    <p class="subtitulo">{{ __('operaciones.pdf.comun.subtitulo_lote_orden', ['lote' => $trabajo->lote_id, 'orden' => $trabajo->orden_id, 'aplicacion' => $trabajo->nro_aplicacion]) }}</p>
    <p class="subtitulo">{{ __('operaciones.pdf.reporte_tecnico.emitido_el', ['fecha' => $reporte->generado_en->format('d/m/Y H:i')]) }}</p>

    <h2>{{ __('operaciones.pdf.reporte_tecnico.imagen_campo_titulo') }}</h2>
    {{-- `archivo_url` es una clave del bucket, no una URL: dompdf no puede
         resolverla. Se incrustan los bytes. Ver EvidenciaIncrustada. --}}
    @php($imagenCampo = \App\Dominios\Operaciones\Infraestructura\Http\Presentacion\EvidenciaIncrustada::dataUri($datos['imagen_campo_url']))
    @if ($imagenCampo !== null)
        <img class="campo" src="{{ $imagenCampo }}" alt="{{ __('operaciones.pdf.reporte_tecnico.imagen_campo_titulo') }}">
    @else
        <p>{{ __('operaciones.pdf.reporte_tecnico.imagen_campo_vacio') }}</p>
    @endif

    <h2>{{ __('operaciones.pdf.reporte_tecnico.horas_aplicacion_titulo') }}</h2>
    <table>
        <tr>
            <th>{{ __('operaciones.trabajos.col_inicio') }}</th>
            <td>{{ optional($datos['hora_inicio'])->format('d/m/Y H:i') ?? __('operaciones.pdf.comun.sin_dato') }}</td>
        </tr>
        <tr>
            <th>{{ __('operaciones.trabajos.col_fin') }}</th>
            <td>{{ optional($datos['hora_fin'])->format('d/m/Y H:i') ?? __('operaciones.pdf.comun.sin_dato') }}</td>
        </tr>
    </table>

    <h2>{{ __('operaciones.pdf.reporte_tecnico.acta_titulo') }}</h2>
    @if ($datos['acta'] !== null)
        <table>
            <tr>
                <th>{{ __('operaciones.pdf.reporte_tecnico.acta_columna') }}</th>
                <td>#{{ $datos['acta']['id'] }}</td>
            </tr>
            <tr>
                <th>{{ __('operaciones.pdf.reporte_tecnico.firmante') }}</th>
                <td>{{ $datos['acta']['firmante'] ?? __('operaciones.pdf.comun.sin_dato') }}</td>
            </tr>
            <tr>
                <th>{{ __('operaciones.pdf.reporte_tecnico.fecha_firma') }}</th>
                <td>{{ optional($datos['acta']['fecha_firma'])->format('d/m/Y H:i') ?? __('operaciones.pdf.comun.sin_dato') }}</td>
            </tr>
        </table>
    @else
        <p>{{ __('operaciones.pdf.reporte_tecnico.acta_vacio') }}</p>
    @endif

    <h2>{{ __('operaciones.pdf.reporte_tecnico.resumen_titulo') }}</h2>
    <table>
        <tr>
            <th>{{ __('operaciones.pdf.reporte_tecnico.hectareas_declaradas') }}</th>
            <td>{{ $datos['resumen']['hectareas_declaradas'] }}</td>
        </tr>
        <tr>
            <th>{{ __('operaciones.pdf.reporte_tecnico.litros_por_hectarea') }}</th>
            <td>{{ $datos['resumen']['litros_por_hectarea'] ?? __('operaciones.pdf.comun.sin_dato') }}</td>
        </tr>
        <tr>
            <th>{{ __('operaciones.pdf.reporte_tecnico.cobertura') }}</th>
            <td>{{ $datos['resumen']['cobertura'] ?? __('operaciones.pdf.reporte_tecnico.cobertura_en_curso') }}</td>
        </tr>
    </table>

    @if (count($datos['condiciones']) > 0)
        <h2>{{ __('operaciones.pdf.reporte_tecnico.condiciones_titulo') }}</h2>
        <table>
            <tr>
                <th>{{ __('operaciones.sesiones_validacion.col_sesion') }}</th>
                <th>{{ __('operaciones.pdf.reporte_tecnico.viento') }}</th>
                <th>{{ __('operaciones.pdf.reporte_tecnico.temperatura') }}</th>
                <th>{{ __('operaciones.pdf.reporte_tecnico.humedad') }}</th>
                <th>{{ __('operaciones.pdf.reporte_tecnico.resultado') }}</th>
            </tr>
            @foreach ($datos['condiciones'] as $condicion)
                <tr>
                    <td>#{{ $condicion['sesion_id'] }}</td>
                    <td>{{ $condicion['viento_kmh'] }}</td>
                    <td>{{ $condicion['temperatura_c'] }}</td>
                    <td>{{ $condicion['humedad_pct'] }}</td>
                    <td>{{ __("operaciones.pdf.reporte_tecnico.resultado_valor.{$condicion['resultado']}") }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($datos['superficie_no_aplicada'] !== null)
        <h2>{{ __('operaciones.pdf.reporte_tecnico.superficie_no_aplicada_titulo') }}</h2>
        <table>
            <tr>
                <th>{{ __('operaciones.pdf.reporte_tecnico.hectareas_sin_aplicar') }}</th>
                <td>{{ $datos['superficie_no_aplicada']['hectareas'] }}</td>
            </tr>
            <tr>
                <th>{{ __('operaciones.pdf.reporte_tecnico.motivo') }}</th>
                <td>{{ $datos['superficie_no_aplicada']['motivo'] ?? __('operaciones.pdf.comun.sin_dato') }}</td>
            </tr>
        </table>
    @endif

    <h2>{{ __('operaciones.pdf.reporte_tecnico.capturas_titulo') }}</h2>
    {{-- Espec §4.3: cada sesión se cierra con su captura de RC. Es la
         evidencia de rendimiento del vuelo —hectáreas, tiempo, litros— que
         sostiene el número que se factura, así que va en el reporte que
         recibe el cliente. --}}
    @if (count($datos['capturas_rc']) > 0)
        @foreach ($datos['capturas_rc'] as $captura)
            @php($imagenCaptura = \App\Dominios\Operaciones\Infraestructura\Http\Presentacion\EvidenciaIncrustada::dataUri($captura['evidencia_url']))
            <div class="captura">
                @if ($imagenCaptura !== null)
                    <img src="{{ $imagenCaptura }}" alt="{{ __('operaciones.pdf.reporte_tecnico.captura_alt', ['secuencia' => $captura['secuencia']]) }}">
                @endif
                <span>{{ __('operaciones.pdf.reporte_tecnico.captura_leyenda', ['secuencia' => $captura['secuencia'], 'hectareas' => $captura['hectareas_declaradas']]) }}</span>
            </div>
        @endforeach
    @else
        <p>{{ __('operaciones.pdf.reporte_tecnico.capturas_vacio') }}</p>
    @endif

    <h2>{{ __('operaciones.pdf.reporte_tecnico.incidencias_titulo') }}</h2>
    @if (count($datos['incidencias']) > 0)
        <table>
            <tr>
                <th>{{ __('operaciones.alertas.col_tipo') }}</th>
                <th>{{ __('operaciones.pdf.reporte_tecnico.evidencia') }}</th>
            </tr>
            @foreach ($datos['incidencias'] as $incidencia)
                <tr>
                    <td>{{ __("operaciones.trabajos.incidencia_tipo.{$incidencia['tipo']}") }}</td>
                    <td>{{ $incidencia['evidencia_url'] }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <p>{{ __('operaciones.pdf.reporte_tecnico.incidencias_vacio') }}</p>
    @endif

    @if (count($datos['sesiones_detalle']) > 0)
        <h2>{{ __('operaciones.pdf.reporte_tecnico.sesiones_detalle_titulo') }}</h2>
        <table>
            <tr>
                <th>{{ __('operaciones.sesiones_validacion.col_sesion') }}</th>
                <th>{{ __('operaciones.trabajos.col_piloto') }}</th>
                <th>{{ __('operaciones.pdf.reporte_tecnico.dron') }}</th>
                <th>{{ __('operaciones.sesiones_validacion.col_hectareas') }}</th>
                <th>{{ __('operaciones.sesiones_validacion.col_motivo_cierre') }}</th>
            </tr>
            @foreach ($datos['sesiones_detalle'] as $sesion)
                <tr>
                    <td>#{{ $sesion['sesion_id'] }}</td>
                    <td>#{{ $sesion['piloto_id'] }}</td>
                    <td>{{ $sesion['dron_id'] !== null ? '#'.$sesion['dron_id'] : __('operaciones.pdf.comun.sin_dato') }}</td>
                    <td>{{ $sesion['hectareas_declaradas'] }}</td>
                    <td>{{ $sesion['motivo_cierre'] !== null ? __("operaciones.trabajos.motivo_cierre.{$sesion['motivo_cierre']}") : __('operaciones.pdf.comun.sin_dato') }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>{{ __('operaciones.pdf.reporte_tecnico.productos_titulo') }}</h2>
    {{-- Espec §7 (HU-78, tarea 94, revierte CR-01): lo que el piloto
         transcribió al crear la aplicación — nunca dosis, orden de
         incorporación ni compatibilidad entre productos (§7.1 sigue
         vigente). --}}
    @if (count($datos['productos_mezcla']) > 0)
        <table>
            <tr>
                <th>{{ __('operaciones.pdf.reporte_tecnico.producto') }}</th>
                <th>{{ __('operaciones.pdf.reporte_tecnico.cantidad') }}</th>
                <th>{{ __('operaciones.pdf.reporte_tecnico.unidad') }}</th>
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
        <p class="nota">{{ __('operaciones.pdf.reporte_tecnico.productos_vacio') }}</p>
    @endif

    <h2>{{ __('operaciones.pdf.reporte_tecnico.equipos_titulo') }}</h2>
    {{-- "Reporte de Equipos" (ronda del dueño, 13/9/2026; HU-80): ciclos de
         batería reales (Mantenimiento), horas de vuelo declaradas del dron y
         las tres fotos de chequeo. --}}
    @if ($datos['equipo'] !== null)
        @if (count($datos['equipo']['ciclos_bateria']) > 0)
            <table>
                <tr>
                    <th>{{ __('operaciones.pdf.reporte_tecnico.bateria') }}</th>
                    <th>{{ __('operaciones.pdf.reporte_tecnico.ciclos_acumulados') }}</th>
                </tr>
                @foreach ($datos['equipo']['ciclos_bateria'] as $bateria)
                    <tr>
                        <td>{{ $bateria['identificador'] }}</td>
                        <td>{{ $bateria['ciclos_acumulados'] ?? __('operaciones.pdf.reporte_tecnico.ciclos_sin_dato') }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
        <table>
            <tr>
                <th>{{ __('operaciones.pdf.reporte_tecnico.horas_vuelo_dron') }}</th>
                <td>{{ $datos['equipo']['horas_vuelo_dron'] ?? __('operaciones.pdf.comun.sin_dato') }}</td>
            </tr>
        </table>
        @foreach ([
            'foto_control_url' => __('operaciones.pdf.reporte_tecnico.equipo_foto_control'),
            'foto_ciclo_bateria_balanceo_url' => __('operaciones.pdf.reporte_tecnico.equipo_foto_ciclo_bateria'),
            'foto_dron_limpio_url' => __('operaciones.pdf.reporte_tecnico.equipo_foto_dron_limpio'),
        ] as $clave => $etiqueta)
            @php($imagenEquipo = \App\Dominios\Operaciones\Infraestructura\Http\Presentacion\EvidenciaIncrustada::dataUri($datos['equipo'][$clave]))
            <div class="captura">
                @if ($imagenEquipo !== null)
                    <img src="{{ $imagenEquipo }}" alt="{{ __('operaciones.pdf.reporte_tecnico.foto_de', ['etiqueta' => $etiqueta]) }}">
                @endif
                <span>{{ $etiqueta }}</span>
            </div>
        @endforeach
    @else
        <p>{{ __('operaciones.pdf.reporte_tecnico.equipos_vacio') }}</p>
    @endif
</body>
</html>
