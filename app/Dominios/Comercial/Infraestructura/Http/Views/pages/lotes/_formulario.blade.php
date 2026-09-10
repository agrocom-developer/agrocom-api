{{--
    Partial: formulario de lote suelto, compartido por create.blade.php y
    edit.blade.php (tarea 77, HU-54, etapa 2; actualizado ADR 0018) — arquetipo
    Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md. Mismo patrón que
    `campos/_formulario.blade.php`: las dos páginas arman el MISMO
    formulario; lo único que cambia es contra qué URL/método postea y los
    valores iniciales.

    Espera:
    - $lote (Lote|null): null en alta; el modelo, con `campo.propiedad` ya
      cargada, en edición.
    - $clientesDisponibles (Collection<int, string>): id => razón social —
      alimenta el select de cliente, que es solo un FILTRO del segundo nivel
      (no viaja al servidor como columna propia: un lote no tiene `cliente_id`,
      lo hereda de su propiedad).
    - $propiedadesDisponibles (Collection<int, Propiedad>): id => Propiedad (con
      `cliente_id`, `nombre`) — primer nivel del cascade cliente → propiedad →
      campo (ADR 0018).
    - $camposDisponibles (Collection<int, Campo>): id => Campo (con
      `propiedad_id`, `nombre`) — segundo nivel del cascade, y select final
      donde cuelga el lote. Arma el mapa propiedad→campo que filtra en la
      propiedad (`resources/js/pages/lotes-form.js`, expandido a 3 niveles).

    El código de campo/hectáreas/geometría/restricciones reusa el MISMO
    partial `campos/_lote-fila.blade.php` que arma cada fila del array de
    lotes del formulario de propiedad (tarea 77: `$prefijo` generaliza el
    nombre de los campos para que sirva también acá, sin envolver un único
    lote en un array de uno) — un solo lugar donde vive el editor de mapa y
    el resto de los campos, dos formularios que lo incluyen.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.
--}}
@php
    $esEdicion = $lote !== null;
    $accion = $esEdicion ? route('panel.lotes.update', $lote) : route('panel.lotes.store');
    $campoId = old('campo_id', $lote?->campo_id ?? '');
    $propiedadId = old('propiedad_id', $lote?->campo?->propiedad_id ?? '');
    $clienteId = old('cliente_id', $lote?->campo?->propiedad?->cliente_id ?? '');
    $datosLote = [
        'codigo' => old('lote.codigo', $lote?->codigo ?? ''),
        'hectareas' => old('lote.hectareas', $lote?->hectareas ?? ''),
        'geometria' => old('lote.geometria', $lote?->geometria !== null ? json_encode($lote->geometria) : ''),
        'restricciones' => old('lote.restricciones', $lote?->restricciones ?? ''),
    ];
    $mapaClientePropiedad = $propiedadesDisponibles->pluck('cliente_id', 'id');
    $propiedadesOptions = $propiedadesDisponibles->mapWithKeys(fn ($propiedad) => [
        $propiedad->id => $propiedad->nombre,
    ]);
    $mapaPropiedadCampo = $camposDisponibles->pluck('propiedad_id', 'id');
    $camposOptions = $camposDisponibles->mapWithKeys(fn ($campo) => [
        $campo->id => $campo->nombre,
    ]);
@endphp

<form method="POST" action="{{ $accion }}" class="ag-lotes-form" novalidate data-ag-lotes-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('comercial.lotes.titulo_editar') : __('comercial.lotes.titulo_crear')"
        :subtitle="__('comercial.lotes.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.lotes.index') }}" variant="outline" icon="arrow_back">
                {{ __('comercial.lotes.volver') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    <x-molecules.form-section
        :title="__('comercial.lotes.seccion_datos')"
        :count="__('comercial.lotes.campos_contador', ['cantidad' => 3])"
    >
        <x-atoms.select
            name="cliente_id"
            id="cliente_id"
            label="{{ __('comercial.lotes.campo_cliente') }}"
            :options="$clientesDisponibles"
            :value="$clienteId"
            placeholder="{{ __('comercial.lotes.campo_cliente_placeholder') }}"
            help="{{ __('comercial.lotes.campo_cliente_ayuda') }}"
            data-ag-lote-cliente
        />

        <x-atoms.select
            name="propiedad_id"
            id="propiedad_id"
            label="{{ __('comercial.lotes.campo_propiedad') }}"
            :options="$propiedadesOptions"
            :value="$propiedadId"
            placeholder="{{ __('comercial.lotes.campo_propiedad_placeholder') }}"
            data-ag-lote-propiedad
            data-mapa-cliente-propiedad="{{ $mapaClientePropiedad->toJson() }}"
        />

        <x-atoms.select
            name="campo_id"
            id="campo_id"
            label="{{ __('comercial.lotes.campo_campo') }}"
            :options="$camposOptions"
            :value="$campoId"
            placeholder="{{ __('comercial.lotes.campo_campo_placeholder') }}"
            required
            error="{{ $errors->first('campo_id') }}"
            data-ag-lote-campo
            data-mapa-propiedad-campo="{{ $mapaPropiedadCampo->toJson() }}"
        />
    </x-molecules.form-section>

    <x-molecules.form-section :title="__('comercial.lotes.seccion_lote')">
        <div class="ag-form-section__field--full">
            @include('comercial::pages.campos._lote-fila', ['lote' => $datosLote, 'prefijo' => 'lote', 'mostrarQuitar' => false])
        </div>
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('comercial.lotes.estado_form')">
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.lotes.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>
</form>
