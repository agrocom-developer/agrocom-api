{{--
    Contenido del PDF del acta de conformidad (HU-17, tarea 24): renderizado
    una sola vez, al generar el acta (`GenerarActaTrabajo`) — nunca
    regenerado, ni siquiera después de la firma (ver runs/24.md). Por eso NO
    incluye firmante/fecha_firma: al momento de generarse, el acta todavía
    está `pendiente`.

    Solo lo mínimo de un acta de conformidad — lote/trabajo, hectáreas
    conformadas, fecha. Nada del reporte técnico completo (mezcla,
    condiciones, litros/ha): eso es HU-18 (tarea 25), tarea aparte. Sin
    componentes Atomic Design del panel: dompdf no procesa el CSS compilado
    por Vite, así que este HTML es autónomo — y por eso mismo tampoco puede
    resolver `var(--ag-…)` (los tokens del panel viven en hojas que este
    documento nunca carga). Un PDF descargado no reacciona a
    `[data-bs-theme]`: no hay theming que aplicar sobre un archivo estático
    (ADR 0002), así que la invariante 11 de CLAUDE.md no tiene nada que
    proteger acá — igual se evita el hex/rgb literal que vigila
    `TokensColorTest` usando palabras clave CSS con nombre.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Acta de conformidad — Trabajo #{{ $trabajo->id }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: black; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        p.subtitulo { color: dimgray; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid gray; padding: 6px 8px; text-align: left; }
        th { background-color: whitesmoke; width: 40%; }
    </style>
</head>
<body>
    <h1>Acta de conformidad</h1>
    <p class="subtitulo">Lote #{{ $trabajo->lote_id }} — Orden #{{ $trabajo->orden_id }} (aplicación {{ $trabajo->nro_aplicacion }})</p>

    <table>
        <tr>
            <th>Trabajo</th>
            <td>#{{ $trabajo->id }}</td>
        </tr>
        <tr>
            <th>Hectáreas conformadas</th>
            <td>{{ $acta->hectareas_conformadas }}</td>
        </tr>
        <tr>
            <th>Fecha de generación</th>
            <td>{{ optional($acta->created_at)->format('d/m/Y H:i') }}</td>
        </tr>
    </table>
</body>
</html>
