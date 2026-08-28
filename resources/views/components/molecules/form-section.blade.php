{{--
    Molecule: form-section
    Agrupador de un bloque de formulario: `<fieldset>` sin chrome nativo +
    `<legend>` estilizada como título de sección + grid de una columna para
    el contenido. Compone solo estructura/tipografía (ningún átomo propio) —
    nace para no repetir el mismo `<fieldset style="...">` suelto en cada
    página con un formulario largo (primer caso real:
    pages/organizacion/index.blade.php, 4 secciones idénticas en estructura).

    Props:
    - title (requerido): texto de la leyenda, ya traducido por el llamador.

    Slot (default): contenido de la sección (inputs, grupos de radio, etc.),
    cada hijo directo ocupa una fila del grid interno.
--}}
@props([
    'title',
])

<fieldset {{ $attributes->class(['ag-form-section']) }}>
    <legend class="ag-form-section__title">{{ $title }}</legend>

    <div class="ag-form-section__body">
        {{ $slot }}
    </div>
</fieldset>
