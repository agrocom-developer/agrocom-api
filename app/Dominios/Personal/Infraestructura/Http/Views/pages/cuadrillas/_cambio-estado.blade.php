{{--
    Partial: formularios + modales del cambio de estado de UNA cuadrilla, para
    los pasos de `molecules/step-arrow` de la ficha de edición (tarea
    "cuadrillas-estadias", 19/9/2026) — mismo patrón que
    `campania::pages.campanias._cambio-estado` (§6.3.4 de
    docs/diseno/guia_pantalla_panel.md). El paso solo abre el modal; el modal
    envía el `<form>` de acá a `panel.cuadrillas.cambiar-estado`, que pasa por
    `CambiarEstadoEquipoTrabajo` → la máquina de estados (invariante 7).

    Va FUERA del `<form>` de `_formulario.blade.php` a propósito — un `<form>`
    no puede anidarse en otro — y los `<form>` van con `hidden` para no
    sumar un hueco en el contenedor.

    Espera:
    - $equipo (EquipoTrabajo).
    - $pasosEstado (list<array{...}>): los de `PasosDeEstado::armar()`. Solo
      los pasos `next` traen su formulario y su modal.
    - $tonoPorEstado (array<string, string>): estado → tono del badge y del
      modal (el mismo mapa del listado).
--}}
@foreach ($pasosEstado as $paso)
    @continue($paso['status'] !== 'next')

    @php
        $formIdEstado = "cuadrilla-estado-form-{$paso['key']}";
        $estadoActual = $equipo->estado->value;
    @endphp

    <form id="{{ $formIdEstado }}" method="POST" action="{{ route('panel.cuadrillas.cambiar-estado', $equipo) }}" hidden>
        @csrf
        <input type="hidden" name="estado" value="{{ $paso['key'] }}">
    </form>

    <x-molecules.confirm-modal
        :id="$paso['modal']"
        :form-id="$formIdEstado"
        :title="__('personal.equipos_trabajo.confirmar_estado_titulo.'.$paso['key'])"
        :message="__('personal.equipos_trabajo.confirmar_estado.'.$paso['key'])"
        :confirm-label="__('personal.equipos_trabajo.accion_estado.'.$paso['key'])"
        :tone="$tonoPorEstado[$paso['key']] ?? 'neutral'"
        :modal-icon="$paso['key'] === 'activo' ? 'play_circle' : 'pause_circle'"
    >
        <x-molecules.state-transition
            :from-label="__('personal.equipos_trabajo.estado.'.$estadoActual)"
            :from-tone="$tonoPorEstado[$estadoActual] ?? 'neutral'"
            :to-label="__('personal.equipos_trabajo.estado.'.$paso['key'])"
            :to-tone="$tonoPorEstado[$paso['key']] ?? 'neutral'"
            :label="__('personal.equipos_trabajo.estado_cambio_de_a', [
                'desde' => __('personal.equipos_trabajo.estado.'.$estadoActual),
                'hacia' => __('personal.equipos_trabajo.estado.'.$paso['key']),
            ])"
        />
    </x-molecules.confirm-modal>
@endforeach
