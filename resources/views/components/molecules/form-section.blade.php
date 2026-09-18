{{--
    Molecule: form-section (evolución, tarea 31 — arquetipo formulario)
    Antes: `<fieldset>` desnudo sin chrome ni grid. Ahora: tarjeta de
    formulario — superficie `--ag-color-surface-card`, borde
    `--ag-color-border-card`, radio `--ag-radius-lg`, con `molecules/section-head`
    como header (barra + rótulo mono uppercase + contador de campos) separado
    del cuerpo por un borde, y grid de DOS columnas para el contenido
    (`repeat(auto-fit, minmax(14rem, 1fr))`) en vez de la única columna
    angosta de antes. Sigue sin componer átomos propios más allá de
    `section-head` — ninguna lógica, solo estructura/tipografía.

    Props:
    - title (requerido): rótulo de la sección, ya traducido por el llamador.
    - count (nullable string|int): contador de campos a la derecha del
      rótulo (p. ej. "3 campos"), ya formateado — se reenvía a `section-head`.
    - accent (nullable, default null): se reenvía tal cual a `section-head`
      — ver su docblock para los valores válidos. `null` mantiene el verde
      de siempre.

    Slot (default): contenido de la sección. Cada hijo directo ocupa una
    celda del grid interno; un campo que necesita el ancho completo (una
    dirección, un textarea, un `file-field`) agrega la clase de utilidad
    `ag-form-section__field--full` (`grid-column: 1 / -1`).
--}}
@props([
    'title',
    'count' => null,
    'accent' => null,
])

<div {{ $attributes->class(['ag-form-section']) }}>
    <x-molecules.section-head :title="$title" :count="$count" :accent="$accent" class="ag-form-section__head" />

    <div class="ag-form-section__body">
        {{ $slot }}
    </div>
</div>
