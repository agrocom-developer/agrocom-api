{{--
    Partial: acciones de una fila de documento (ver archivo, aprobar, rechazar),
    compartida por la cola de documentos y las fichas. El `<form>` y el modal van
    FUERA de `row-actions` (ese organism repite su slot y duplicaría el id); el
    disparador vive adentro.

    Espera:
    - $docId (int), $docEstado (string), $docEtiqueta (string): el documento.
    - $puedeVer, $puedeRevisar (bool): permisos del rol activo.
--}}
@php
    $revisable = $puedeRevisar && $docEstado === 'en_revision';
    $formAprobar = "documento-aprobar-{$docId}";
    $formRechazar = "documento-rechazar-{$docId}";
@endphp

@if ($revisable)
    <form id="{{ $formAprobar }}" method="POST" action="{{ route('panel.documentos.revisar', $docId) }}" hidden>
        @csrf
        <input type="hidden" name="decision" value="aprobar">
    </form>
    <form id="{{ $formRechazar }}" method="POST" action="{{ route('panel.documentos.revisar', $docId) }}" hidden>
        @csrf
        <input type="hidden" name="decision" value="rechazar">
    </form>

    <x-molecules.confirm-modal
        :id="'documento-aprobar-modal-'.$docId"
        :form-id="$formAprobar"
        :title="__('proveedores.documentos.modal.aprobar_titulo', ['documento' => $docEtiqueta])"
        :message="__('proveedores.documentos.modal.aprobar_mensaje')"
        :confirm-label="__('proveedores.documentos.aprobar')"
        tone="success"
    />

    <x-molecules.confirm-modal
        :id="'documento-rechazar-modal-'.$docId"
        :form-id="$formRechazar"
        :title="__('proveedores.documentos.modal.rechazar_titulo', ['documento' => $docEtiqueta])"
        :message="__('proveedores.documentos.modal.rechazar_mensaje')"
        :confirm-label="__('proveedores.documentos.rechazar')"
        tone="danger"
    >
        <x-atoms.textarea
            name="motivo"
            :id="'documento-rechazar-motivo-'.$docId"
            :form="$formRechazar"
            :label="__('proveedores.aprobacion.motivo_etiqueta')"
            :help="__('proveedores.documentos.modal.motivo_ayuda')"
            :rows="3"
            required
        />
    </x-molecules.confirm-modal>
@endif

<x-organisms.row-actions>
    @if ($puedeVer)
        <x-atoms.button :href="route('panel.documentos.archivo', $docId)" target="_blank" rel="noopener" variant="info-outline" size="sm" icon="visibility">
            {{ __('proveedores.documentos.ver_archivo') }}
        </x-atoms.button>
    @endif

    @if ($revisable)
        <x-atoms.button type="button" data-bs-toggle="modal" data-bs-target="#documento-aprobar-modal-{{ $docId }}" variant="success-outline" size="sm" icon="check_circle">
            {{ __('proveedores.documentos.aprobar') }}
        </x-atoms.button>
        <x-atoms.button type="button" data-bs-toggle="modal" data-bs-target="#documento-rechazar-modal-{{ $docId }}" variant="danger-outline" size="sm" icon="cancel">
            {{ __('proveedores.documentos.rechazar') }}
        </x-atoms.button>
    @endif
</x-organisms.row-actions>
