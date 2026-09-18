{{--
    Molecule: timeline (17/9/2026 — sección "Actividad" de la vista detalle
    de Operaciones, primera pantalla del arquetipo Detalle)
    Lista vertical de eventos con fecha: punto de color + título + una línea
    de meta (fecha·autor, ya formateada). Sin lógica propia: el llamador ya
    resolvió fechas/autores reales — este componente nunca inventa un evento
    que el llamador no pudo reconstruir desde columnas reales (ver docblock
    de `OrdenesController::actividadOrden()`, el primer consumidor).
    Pensada para reusarse en cualquier otra pantalla de detalle del módulo
    Operaciones (Trabajo, Sesión, Acta…) — no es exclusiva de Órdenes.

    Props:
    - items (requerido): list<['title' => string, 'meta' => string, 'tone' => 'neutral'|'success'|'warning'|'danger'|'info']>.
      `title`/`meta` ya traducidos/formateados. `tone` colorea el punto —
      mismo set que `atoms/badge`, sin `tone` cae en "neutral".
--}}
@props([
    'items' => [],
])

<ol {{ $attributes->class(['ag-timeline']) }}>
    @foreach ($items as $item)
        <li class="ag-timeline__item">
            <span class="ag-timeline__dot ag-timeline__dot--{{ $item['tone'] ?? 'neutral' }}" aria-hidden="true"></span>
            <div class="ag-timeline__body">
                <p class="ag-timeline__title">{{ $item['title'] }}</p>
                <p class="ag-timeline__meta">{{ $item['meta'] }}</p>
            </div>
        </li>
    @endforeach
</ol>
