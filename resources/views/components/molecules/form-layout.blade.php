{{--
    Molecule: form-layout (main + aside pegajoso del arquetipo Formulario,
    §6.3 regla 4 / §6.3.1 de docs/diseno/guia_pantalla_panel.md)

    Envuelve el cuerpo del formulario en dos columnas: `main` (slot por
    defecto — las tarjetas `molecules/form-section` y la
    `organisms/form-actions-bar`) y, opcional, un `aside` pegajoso de solo
    lectura (metadatos de la propia entidad o resumen relacionado de otras
    entidades, ver §6.3.1). Sin `aside` (alta, sin nada relacionado todavía),
    `main` ocupa el 100% del ancho solo — no hace falta ningún prop para
    avisarlo, `.ag-form-layout__main:only-child` lo resuelve por CSS. Sin
    lógica ni JS propio: solo estructura y el breakpoint (<1200px) donde el
    aside deja de ser una columna aparte y pasa a apilarse debajo.

    Nace de clientes/propiedades/campanias/lotes/contratos.css, que
    declaraban el mismo layout con distinto nombre de clase
    (`.ag-clientes-form__layout/__main/__aside`, etc.) letra por letra — ver
    docs/diseno/guia_pantalla_panel.md §6.3.1.

    Slots:
    - (default): contenido principal — form-section(s) + form-actions-bar.
    - aside (opcional): columna lateral pegajosa — summary-card/empty-state.
      Se puede envolver en un `@if` en el sitio de la llamada (alta vs.
      edición), mismo patrón que ya usa el slot `chip` de
      `campanias/_formulario.blade.php` con `organisms/page-header`.
--}}
@props([])

<div {{ $attributes->class(['ag-form-layout']) }}>
    <div class="ag-form-layout__main">
        {{ $slot }}
    </div>

    @isset($aside)
        <aside class="ag-form-layout__aside">
            {{ $aside }}
        </aside>
    @endisset
</div>
