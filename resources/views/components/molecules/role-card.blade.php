{{--
    Molecule: role-card (quinta vuelta — maqueta 5c)
    Tarjeta seleccionable de la pantalla de selección de rol: ícono propio,
    nombre LEGIBLE ("Dueño", nunca el slug), badge "ÚLTIMO USADO" (mono
    ámbar) cuando corresponde, descripción y chips con los permisos que
    habilita. Seleccionada: borde verde 1.5px, fondo verde tenue, sombra
    suave y check circular relleno a la derecha. Radio 12.

    Semántica de radiogroup (consigna 5c): cada tarjeta es role="radio" con
    aria-checked; el contenedor role="radiogroup" y el manejo de flechas/
    Enter viven en la página (resources/js/organisms/role-selection.js) —
    esta molécula solo expone `data-ag-role-id` y los atributos ARIA.

    Sin lógica de negocio: los datos llegan ya presentados
    (PresentadorRol::presentar()) — nombre/descripcion/permisos/icono.

    Props:
    - id (requerido): id de `sec_role`.
    - nombre (requerido): nombre legible, ya resuelto.
    - descripcion (nullable).
    - permisos (list<string>, default []): chips, ya resueltos.
    - icon (default "badge"): ícono Material Symbols.
    - selected (bool, default false).
    - ultimoUsado (bool, default false): badge "ÚLTIMO USADO".
--}}
@props([
    'id',
    'nombre',
    'descripcion' => null,
    'permisos' => [],
    'icon' => 'badge',
    'selected' => false,
    'ultimoUsado' => false,
])

<button
    type="button"
    role="radio"
    aria-checked="{{ $selected ? 'true' : 'false' }}"
    data-ag-role-id="{{ $id }}"
    data-ag-role-nombre="{{ $nombre }}"
    tabindex="{{ $selected ? '0' : '-1' }}"
    {{ $attributes->class(['ag-role-card', $selected ? 'is-selected' : '']) }}
>
    <span class="ag-role-card__icon" aria-hidden="true">
        <x-atoms.icon :name="$icon" />
    </span>

    <span class="ag-role-card__body">
        <span class="ag-role-card__head">
            <span class="ag-role-card__name">{{ $nombre }}</span>
            @if ($ultimoUsado)
                <span class="ag-role-card__last">{{ __('seguridad.rol.ultimo_usado') }}</span>
            @endif
        </span>

        @if ($descripcion)
            <span class="ag-role-card__desc">{{ $descripcion }}</span>
        @endif

        @if (count($permisos) > 0)
            <span class="ag-role-card__perms">
                @foreach ($permisos as $permiso)
                    <span class="ag-role-card__perm">{{ $permiso }}</span>
                @endforeach
            </span>
        @endif
    </span>

    <span class="ag-role-card__check" aria-hidden="true">
        <x-atoms.icon name="check" size="sm" />
    </span>
</button>
