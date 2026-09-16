{{--
    Partial: formulario de lote suelto, compartido por create.blade.php y
    edit.blade.php (tarea 77, HU-54, etapa 2; actualizado ADR 0020) — arquetipo
    Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md. Mismo patrón que
    `propiedades/_formulario.blade.php`: las dos páginas arman el MISMO
    formulario; lo único que cambia es contra qué URL/método postea y los
    valores iniciales.

    Espera:
    - $lote (Lote|null): null en alta; el modelo, con `propiedad` ya
      cargada, en edición.
    - $clientesDisponibles (Collection<int, string>): id => razón social —
      alimenta el select de cliente, que es solo un FILTRO del segundo nivel
      (no viaja al servidor como columna propia: un lote no tiene `cliente_id`,
      lo hereda de su propiedad).
    - $propiedadesDisponibles (Collection<int, Propiedad>): id => Propiedad (con
      `cliente_id`, `nombre`) — primer y único nivel del cascade cliente →
      propiedad (ADR 0020: eliminó el nivel de campo intermedio).

    El código de lote/hectáreas/geometría/restricciones reusa el MISMO
    partial `lotes/_lote-fila.blade.php` que arma cada fila de un lote suelto
    (tarea 77: `$prefijo` generaliza el nombre de los campos para que sirva también
    en el formulario de lotes dentro de una propiedad) — un solo lugar donde vive
    el editor de mapa y el resto de los campos.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.
--}}
@php
    $esEdicion = $lote !== null;
    $accion = $esEdicion ? route('panel.lotes.update', $lote) : route('panel.lotes.store');
    // $propiedadIdPreseleccionado (tarea "contratos-lotes"): solo llega en
    // alta, desde ?propiedad_id= del acceso rápido del formulario de
    // contrato — `edit()` no lo pasa (no aplica editando un lote existente).
    $propiedadId = old('propiedad_id', $lote?->propiedad_id ?? $propiedadIdPreseleccionado ?? '');
    $clienteId = old('cliente_id', $lote?->propiedad?->cliente_id ?? '');
    $datosLote = [
        'codigo' => old('lote.codigo', $lote?->codigo ?? ''),
        'hectareas' => old('lote.hectareas', $lote?->hectareas ?? ''),
        'geometria' => old('lote.geometria', $lote?->geometria !== null ? json_encode($lote->geometria) : ''),
        'restricciones' => old('lote.restricciones', $lote?->restricciones ?? ''),
        'desnivel' => old('lote.desnivel', $lote?->desnivel ?? ''),
        'limpieza' => old('lote.limpieza', $lote?->limpieza ?? ''),
    ];
    $mapaClientePropiedad = $propiedadesDisponibles->pluck('cliente_id', 'id');
    $propiedadesOptions = $propiedadesDisponibles->mapWithKeys(fn ($propiedad) => [
        $propiedad->id => $propiedad->nombre,
    ]);
@endphp

<form method="POST" action="{{ $accion }}" class="ag-lotes-form" novalidate data-ag-lotes-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif
    {{-- Alta rápida desde otro formulario (tarea "contratos-lotes", 16/9/2026):
         solo hace falta reenviarlo en el alta — en edición ya llega vía
         sesión (`LotesController::edit()`), no como campo del form. --}}
    @if (! $esEdicion && ! empty($volverA))
        <input type="hidden" name="volver_a" value="{{ $volverA }}">
    @endif

    {{-- Capas de referencia del mapa (pedido directo del dueño, 16/9/2026):
         el límite de la propiedad elegida y sus lotes ya dibujados, para no
         dibujar el lote nuevo a ciegas — ver LotesController::referenciaMapa().
         `organisms/lote-mapa-editor.js` las lee y las redibuja cada vez que
         cambia el select de propiedad. --}}
    <script type="application/json" data-ag-lote-mapa-referencia>
        {!! json_encode($referenciaMapa) !!}
    </script>

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

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

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
            required
            error="{{ $errors->first('propiedad_id') }}"
            data-ag-lote-propiedad
            data-mapa-cliente-propiedad="{{ $mapaClientePropiedad->toJson() }}"
        />
    </x-molecules.form-section>

    <x-molecules.form-section :title="__('comercial.lotes.seccion_lote')">
        <div class="ag-form-section__field--full">
            @include('comercial::pages.lotes._lote-fila', ['lote' => $datosLote, 'prefijo' => 'lote', 'mostrarQuitar' => false])
        </div>
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('comercial.lotes.estado_form')">
        <x-slot:actions>
            @if ($esEdicion && ! empty($volverA))
                <x-atoms.button href="{{ $volverA }}{{ str_contains($volverA, '?') ? '&' : '?' }}lote_id={{ $lote->id }}" variant="outline" icon="arrow_back">
                    {{ __('comercial.lotes.volver_a_formulario_origen') }}
                </x-atoms.button>
            @endif
            <x-atoms.button href="{{ route('panel.lotes.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>
</form>
