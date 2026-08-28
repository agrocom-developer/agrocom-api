{{--
    Atom: icon
    Envoltorio del ícono Material Symbols (ligadura de texto → glifo),
    variante Rounded — la de las maquetas aprobadas del panel (quinta
    vuelta). La fuente la carga resources/css/app.css.

    Props:
    - name (string, requerido): nombre del ícono de Material Symbols, p. ej. "visibility".
    - size (sm|md|lg, default "md").
    - label (string|null): si el ícono es informativo por sí mismo (no decorativo
      junto a un texto), pasar la etiqueta accesible traducida por el llamador
      (nunca hardcodeada acá — este átomo no decide textos, ADR 0013).
--}}
@props([
    'name',
    'size' => 'md',
    'label' => null,
])

<span
    {{ $attributes->class(['material-symbols-rounded', 'ag-icon', "ag-icon--{$size}"]) }}
    @if ($label)
        role="img"
        aria-label="{{ $label }}"
    @else
        aria-hidden="true"
    @endif
>{{ $name }}</span>
