{{--
    Contenido del PDF del recibo individual de planilla (HU-30, tarea 44):
    renderizado una sola vez, al APROBAR la planilla (`AprobarPlanilla`),
    nunca al generarla en `Borrador` — mismo criterio que `pdf/acta.blade.php`
    de Operaciones (nunca regenerado, dompdf no procesa el CSS del panel, así
    que este HTML es autónomo, sin componentes Atomic Design ni tokens
    `var(--ag-…)`).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo de planilla — Período {{ $planilla->periodo }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: black; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        p.subtitulo { color: dimgray; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid gray; padding: 6px 8px; text-align: left; }
        th { background-color: whitesmoke; width: 40%; }
        td.cifra { text-align: right; }
        tr.neto th, tr.neto td { font-weight: bold; }
    </style>
</head>
<body>
    <h1>Recibo de planilla</h1>
    <p class="subtitulo">Período {{ $planilla->periodo }} — Persona #{{ $detalle->persona_id }}</p>

    <table>
        <tr>
            <th>Devengado</th>
            <td class="cifra">Bs {{ $detalle->devengado }}</td>
        </tr>
        <tr>
            <th>Anticipos</th>
            <td class="cifra">Bs {{ $detalle->anticipos }}</td>
        </tr>
        <tr class="neto">
            <th>Neto a pagar</th>
            <td class="cifra">Bs {{ $detalle->neto }}</td>
        </tr>
        <tr>
            <th>Fecha de aprobación</th>
            <td>{{ optional($planilla->aprobada_en)->format('d/m/Y H:i') }}</td>
        </tr>
    </table>
</body>
</html>
