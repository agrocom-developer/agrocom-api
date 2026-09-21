{{--
    Partial: formularios + diálogos de las tablas de detalle de UNA cuadrilla
    (integrantes, equipamiento y accesorios), para la ficha de edición.

    Va FUERA del `<form>` de `_formulario.blade.php` a propósito — un `<form>`
    no puede anidarse en otro — y cada `<form>` va con `hidden` para no sumar
    un hueco. Los botones de las tablas solo abren el diálogo; el diálogo envía
    el `<form>` de acá. Los campos van en el slot del `confirm-modal`,
    asociados a su formulario con el atributo `form` (mismo patrón que el
    motivo de pausa de `operaciones::pages.ordenes._orden-modales`).

    Todas las acciones mandan `origen=edit` para volver a esta ficha.

    «Agregar equipamiento» elige primero el TIPO y después el recurso: la lista
    del segundo select la repuebla `cuadrillas-form.js` desde el JSON embebido
    (`$opcionesEquipamiento`, tipo → id → etiqueta), sin pedirle nada al
    servidor. Por eso ese select lleva `searchable` a mano (sus opciones las
    llena JS, ver docblock de `atoms/select`).

    Espera: $equipo, $integrantes, $equipamiento, $accesorios (paginadores),
    $personasDisponibles, $roles, $opcionesEquipamiento, $accesoriosDisponibles,
    $puedeCrearPersona.
--}}
@php
    $hoy = now()->toDateString();
    $opcionesRol = collect($roles)->mapWithKeys(fn ($rol) => [$rol->value => __('personal.rol_equipo.'.$rol->value)])->all();
    $opcionesTipo = collect(array_keys($opcionesEquipamiento))->mapWithKeys(fn ($tipo) => [$tipo => __('personal.recurso_tipo.'.$tipo)])->all();
    $retorno = ['volver_a' => url()->full(), 'volver_texto' => __('personal.equipos_trabajo.titulo_editar')];
@endphp

{{-- Agregar integrante --}}
<form id="cuadrilla-integrante-form" method="POST" action="{{ route('panel.cuadrillas.integrantes.store', $equipo) }}" hidden>
    @csrf
    <input type="hidden" name="origen" value="edit">
</form>

<x-molecules.confirm-modal
    id="cuadrilla-integrante-modal"
    form-id="cuadrilla-integrante-form"
    :title="__('personal.equipos_trabajo.detalle_agregar_integrante')"
    :message="__('personal.equipos_trabajo.detalle_agregar_integrante_mensaje')"
    :confirm-label="__('personal.equipos_trabajo.detalle_agregar')"
    tone="info"
    modal-icon="person_add"
>
    <x-atoms.select
        name="persona_id"
        id="cuadrilla-integrante-persona"
        form="cuadrilla-integrante-form"
        icon="person"
        :label="__('personal.equipos_trabajo.ficha_campo_persona')"
        :options="$personasDisponibles"
        :placeholder="__('personal.equipos_trabajo.ficha_campo_persona_placeholder')"
        required
        :action-icon="($puedeCrearPersona ?? false) ? 'person_add' : null"
        :action-href="($puedeCrearPersona ?? false) ? route('panel.personas.create', $retorno) : null"
        :action-label="__('personal.equipos_trabajo.accion_nuevo_personal')"
        :action-text="__('personal.equipos_trabajo.accion_nuevo_corto')"
    />
    <x-atoms.select
        name="rol_equipo"
        id="cuadrilla-integrante-rol"
        form="cuadrilla-integrante-form"
        :label="__('personal.equipos_trabajo.ficha_campo_rol')"
        :options="$opcionesRol"
        :placeholder="__('personal.equipos_trabajo.ficha_campo_rol_placeholder')"
        required
    />
    <x-atoms.date name="desde" id="cuadrilla-integrante-desde" form="cuadrilla-integrante-form" :label="__('personal.equipos_trabajo.campo_desde')" :value="$hoy" required />
    <x-atoms.date name="hasta" id="cuadrilla-integrante-hasta" form="cuadrilla-integrante-form" :label="__('personal.equipos_trabajo.campo_hasta')" :help="__('personal.equipos_trabajo.detalle_hasta_ayuda')" />
</x-molecules.confirm-modal>

{{-- Finalizar integrante: uno por fila vigente de la página --}}
@foreach ($integrantes as $integrante)
    @continue($integrante->hasta !== null)

    <form id="cuadrilla-integrante-finalizar-form-{{ $integrante->id }}" method="POST" action="{{ route('panel.cuadrillas.integrantes.destroy', [$equipo, $integrante]) }}" hidden>
        @csrf
        @method('DELETE')
        <input type="hidden" name="origen" value="edit">
    </form>

    <x-molecules.confirm-modal
        :id="'cuadrilla-integrante-finalizar-'.$integrante->id"
        :form-id="'cuadrilla-integrante-finalizar-form-'.$integrante->id"
        :title="__('personal.equipos_trabajo.detalle_finalizar_integrante_titulo')"
        :message="__('personal.equipos_trabajo.detalle_finalizar_integrante_mensaje', ['persona' => $integrante->persona?->nombre ?? '—'])"
        :confirm-label="__('personal.equipos_trabajo.ficha_finalizar')"
        tone="warning"
        modal-icon="event_busy"
    >
        <x-atoms.date name="hasta" :id="'cuadrilla-integrante-finalizar-hasta-'.$integrante->id" :form="'cuadrilla-integrante-finalizar-form-'.$integrante->id" :label="__('personal.equipos_trabajo.detalle_campo_hasta_cierre')" :value="$hoy" required />
    </x-molecules.confirm-modal>
@endforeach

{{-- Agregar equipamiento --}}
<form id="cuadrilla-equipamiento-form" method="POST" action="{{ route('panel.cuadrillas.recursos.store', $equipo) }}" hidden>
    @csrf
    <input type="hidden" name="origen" value="edit">
</form>

<script type="application/json" data-ag-opciones-equipamiento>{!! json_encode($opcionesEquipamiento) !!}</script>

<x-molecules.confirm-modal
    id="cuadrilla-equipamiento-modal"
    form-id="cuadrilla-equipamiento-form"
    :title="__('personal.equipos_trabajo.detalle_agregar_equipamiento')"
    :message="__('personal.equipos_trabajo.detalle_agregar_equipamiento_mensaje')"
    :confirm-label="__('personal.equipos_trabajo.detalle_agregar')"
    tone="info"
    modal-icon="add_circle"
>
    <x-atoms.select
        name="recurso_tipo"
        id="cuadrilla-equipamiento-tipo"
        form="cuadrilla-equipamiento-form"
        :label="__('personal.equipos_trabajo.ficha_campo_tipo')"
        :options="$opcionesTipo"
        :placeholder="__('personal.equipos_trabajo.ficha_campo_tipo_placeholder')"
        required
        data-ag-select-tipo-recurso
    />
    <x-atoms.select
        name="recurso_id"
        id="cuadrilla-equipamiento-recurso"
        form="cuadrilla-equipamiento-form"
        :label="__('personal.equipos_trabajo.detalle_col_recurso')"
        :options="[]"
        :placeholder="__('personal.equipos_trabajo.ficha_campo_recurso_placeholder')"
        :help="__('personal.equipos_trabajo.detalle_recurso_ayuda')"
        :searchable="true"
        required
        disabled
        data-ag-select-recurso-id
        data-placeholder="{{ __('personal.equipos_trabajo.ficha_campo_recurso_placeholder') }}"
    />
    <x-atoms.date name="desde" id="cuadrilla-equipamiento-desde" form="cuadrilla-equipamiento-form" :label="__('personal.equipos_trabajo.campo_desde')" :value="$hoy" required />
    <x-atoms.date name="hasta" id="cuadrilla-equipamiento-hasta" form="cuadrilla-equipamiento-form" :label="__('personal.equipos_trabajo.campo_hasta')" :help="__('personal.equipos_trabajo.detalle_hasta_ayuda')" />
</x-molecules.confirm-modal>

{{-- Finalizar equipamiento: uno por fila vigente de la página --}}
@foreach ($equipamiento as $recurso)
    @continue($recurso['hasta'] !== null)

    <form id="cuadrilla-recurso-finalizar-form-{{ $recurso['id'] }}" method="POST" action="{{ route('panel.cuadrillas.recursos.destroy', [$equipo, $recurso['id']]) }}" hidden>
        @csrf
        @method('DELETE')
        <input type="hidden" name="origen" value="edit">
    </form>

    <x-molecules.confirm-modal
        :id="'cuadrilla-recurso-finalizar-'.$recurso['id']"
        :form-id="'cuadrilla-recurso-finalizar-form-'.$recurso['id']"
        :title="__('personal.equipos_trabajo.detalle_finalizar_recurso_titulo')"
        :message="__('personal.equipos_trabajo.detalle_finalizar_recurso_mensaje', ['recurso' => $recurso['etiqueta']])"
        :confirm-label="__('personal.equipos_trabajo.ficha_finalizar')"
        tone="warning"
        modal-icon="event_busy"
    >
        <x-atoms.date name="hasta" :id="'cuadrilla-recurso-finalizar-hasta-'.$recurso['id']" :form="'cuadrilla-recurso-finalizar-form-'.$recurso['id']" :label="__('personal.equipos_trabajo.detalle_campo_hasta_cierre')" :value="$hoy" required />
    </x-molecules.confirm-modal>
@endforeach

{{-- Agregar accesorio: del catálogo, o uno nuevo que queda en el catálogo --}}
<form id="cuadrilla-accesorio-form" method="POST" action="{{ route('panel.cuadrillas.accesorios.store', $equipo) }}" hidden>
    @csrf
    <input type="hidden" name="origen" value="edit">
</form>

<x-molecules.confirm-modal
    id="cuadrilla-accesorio-modal"
    form-id="cuadrilla-accesorio-form"
    :title="__('personal.equipos_trabajo.ficha_agregar_accesorio')"
    :message="__('personal.equipos_trabajo.detalle_agregar_accesorio_mensaje')"
    :confirm-label="__('personal.equipos_trabajo.detalle_agregar')"
    tone="info"
    modal-icon="handyman"
>
    <x-atoms.select
        name="accesorio_id"
        id="cuadrilla-accesorio-catalogo"
        form="cuadrilla-accesorio-form"
        icon="handyman"
        :label="__('personal.equipos_trabajo.detalle_accesorio_catalogo')"
        :options="$accesoriosDisponibles"
        :placeholder="__('personal.equipos_trabajo.detalle_accesorio_catalogo_placeholder')"
    />
    <x-atoms.input
        type="text"
        name="nombre_nuevo"
        id="cuadrilla-accesorio-nuevo"
        form="cuadrilla-accesorio-form"
        maxlength="80"
        :label="__('personal.equipos_trabajo.detalle_accesorio_nuevo')"
        :placeholder="__('personal.equipos_trabajo.detalle_accesorio_nuevo_placeholder')"
        :help="__('personal.equipos_trabajo.detalle_accesorio_nuevo_ayuda')"
    />
    <x-atoms.input
        type="number"
        name="cantidad"
        id="cuadrilla-accesorio-cantidad"
        form="cuadrilla-accesorio-form"
        min="1"
        step="1"
        value="1"
        :label="__('personal.equipos_trabajo.ficha_campo_cantidad')"
        required
    />
    <x-atoms.input
        type="text"
        name="observacion"
        id="cuadrilla-accesorio-observacion"
        form="cuadrilla-accesorio-form"
        :label="__('personal.equipos_trabajo.ficha_campo_observacion')"
        :placeholder="__('personal.equipos_trabajo.ficha_campo_observacion_placeholder')"
    />
</x-molecules.confirm-modal>

{{-- Quitar accesorio: el botón de la fila (`confirm-button`) envía este formulario --}}
@foreach ($accesorios as $accesorio)
    <form id="cuadrilla-accesorio-quitar-{{ $accesorio->id }}" method="POST" action="{{ route('panel.cuadrillas.accesorios.destroy', [$equipo, $accesorio]) }}" hidden>
        @csrf
        @method('DELETE')
        <input type="hidden" name="origen" value="edit">
    </form>
@endforeach
