{{--
    Atom: datetime
    Selector de fecha Y HORA. A diferencia de `atoms/date` (calendario propio
    hecho a mano, sin hora — WAI-ARIA Date Picker Dialog), acá se optó por
    flatpickr: un selector de hora accesible construido a mano (grid de
    hora/minuto con roving tabindex, igual que el de días) es mucho más
    trabajo, y este campo solo tiene dos usos hoy (`pausas` — inicio/fin).
    Decisión explícita del usuario: instalar una librería de terceros en vez
    de replicar el patrón de `atoms/date`.

    Reemplaza a `<x-atoms.input type="datetime-local">` (el nativo del
    navegador, sept/2026): cada SO lo dibuja distinto, no respeta los tokens
    del panel ni el tema oscuro — mismo problema que ya se había resuelto
    para `type="date"` en la tarea 76.

    Progressive enhancement DISTINTO al resto del catálogo: el control real
    es un `<input type="text">` (vía `atoms/input`), no un
    `<input type="datetime-local">` nativo oculto. Se evita a propósito: ese
    tipo exige el formato exacto `YYYY-MM-DDTHH:mm` incluso al asignarle
    `.value` desde JS (si no matchea, el navegador limpia el campo), lo que
    complica que flatpickr reescriba el valor con su propio formato. Sin JS,
    el campo es texto plano editable a mano — sigue siendo funcional (el
    usuario escribe "2026-09-11 14:30"), y el backend lo acepta igual:
    `RegistrarPausaRequest` valida `'inicio' => ['required', 'date']`, sin
    formato estricto (Carbon::parse en `RegistrarPausa` hace el resto).

    resources/js/atoms/datetime.js inicializa flatpickr con locale español,
    formato 24h (mismo criterio que la columna HORA del dashboard, `H:i` —
    nunca a.m./p.m.) y `dateFormat: 'Y-m-d H:i'`, escrito directo sobre este
    mismo input (sin altInput/input oculto: al no ser `type="date"` no hay
    restricción de formato nativo que sortear).

    Carga diferida (mismo criterio que dashboard-charts/dashboard-map en
    app.js): flatpickr solo se importa si `[data-ag-datetime]` existe en el
    DOM, para no bajarlo en pantallas que no lo usan.

    Props: iguales a `atoms/input` (este átomo solo fija `icon` y el
    `data-attribute` que dispara la mejora progresiva).
--}}
@props([
    'name',
    'id' => null,
    'label' => null,
    'value' => null,
    'help' => null,
    'error' => null,
    'required' => false,
])

<x-atoms.input
    :name="$name"
    :id="$id"
    :label="$label"
    :value="$value"
    :help="$help"
    :error="$error"
    :required="$required"
    icon="calendar_month"
    placeholder="dd/mm/aaaa hh:mm"
    autocomplete="off"
    data-ag-datetime
    {{ $attributes }}
/>
