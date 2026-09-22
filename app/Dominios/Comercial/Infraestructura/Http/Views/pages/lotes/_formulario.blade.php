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
    // Alta rápida con ?propiedad_id= (tarea "contratos-lotes"): sin lote
    // todavía, el cliente se resuelve de la propiedad preseleccionada, no
    // solo de $lote->propiedad — si no, el select de cliente queda vacío y
    // el cascade de lotes-form.js arranca sin filtrar (16/9/2026).
    $clienteId = old('cliente_id', $lote?->propiedad?->cliente_id ?? $propiedadesDisponibles->get($propiedadIdPreseleccionado ?? 0)?->cliente_id ?? '');
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

    <x-molecules.form-layout>
    <x-organisms.page-header
        :title="$esEdicion ? __('comercial.lotes.titulo_editar') : __('comercial.lotes.titulo_crear')"
        :subtitle="__('comercial.lotes.subtitulo_form')"
    >
        <x-slot:actions>
            <x-molecules.boton-volver
                :href="route('panel.lotes.index')"
                :label="__('comercial.lotes.volver')"
                :retorno="$esEdicion ? ['propiedad_id' => $lote->propiedad_id, 'lote_id' => $lote->id] : []"
            />
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
            :label="__('comercial.lotes.campo_cliente')"
            :options="$clientesDisponibles"
            :value="$clienteId"
            :placeholder="__('comercial.lotes.campo_cliente_placeholder')"
            :help="__('comercial.lotes.campo_cliente_ayuda')"
            data-ag-lote-cliente
        />

        <x-atoms.select
            name="propiedad_id"
            id="propiedad_id"
            :label="__('comercial.lotes.campo_propiedad')"
            :options="$propiedadesOptions"
            :value="$propiedadId"
            :placeholder="__('comercial.lotes.campo_propiedad_placeholder')"
            required
            :error="$errors->first('propiedad_id')"
            data-ag-lote-propiedad
            data-mapa-cliente-propiedad="{{ $mapaClientePropiedad->toJson() }}"
        />
    </x-molecules.form-section>

    {{-- `data-ag-lote-ficha` envuelve las DOS secciones (campos + mapa,
         16/9/2026): `organisms/lote-mapa-editor.js` sube hasta acá con
         `.closest()` para encontrar el input de hectáreas de la OTRA
         sección al hacer "usar superficie" — antes estaba todo dentro del
         mismo `[data-ag-lote-fila]`, ahora el mapa vive en su propia
         sección hermana. --}}
    <div class="ag-lotes-form__ficha" data-ag-lote-ficha>
        <x-molecules.form-section :title="__('comercial.lotes.seccion_lote')">
            <div class="ag-form-section__field--full">
                @include('comercial::pages.lotes._lote-fila', ['lote' => $datosLote, 'prefijo' => 'lote', 'mostrarQuitar' => false])
            </div>
        </x-molecules.form-section>

        {{-- Sección propia (16/9/2026, pedido directo): el mapa no es un
             campo más de "Datos del lote", es su propio bloque, mismo
             criterio que la sección de mapa de `propiedades/mapa.blade.php`. --}}
        <x-molecules.form-section :title="__('comercial.lotes.seccion_mapa')">
            @include('comercial::pages.lotes._lote-mapa', ['lote' => $datosLote, 'prefijo' => 'lote'])
        </x-molecules.form-section>
    </div>

    <x-organisms.form-actions-bar :status="__('comercial.lotes.estado_form')">
        <x-slot:actions>
            @if ($esEdicion && ! empty($volverA))
                <x-atoms.button href="{{ $volverA }}{{ str_contains($volverA, '?') ? '&' : '?' }}propiedad_id={{ $lote->propiedad_id }}&lote_id={{ $lote->id }}" variant="outline" icon="arrow_back">
                    {{ __('comercial.lotes.volver_a_formulario_origen') }}
                </x-atoms.button>
            @endif
            <x-molecules.boton-volver :href="route('panel.lotes.index')" :retorno="$esEdicion ? ['propiedad_id' => $lote->propiedad_id, 'lote_id' => $lote->id] : []" cancelar />
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>

    @if ($esEdicion)
        <x-slot:aside>
            @foreach ($resumenLote ?? [] as $resumen)
                @if ($resumen['tieneDatos'])
                    <x-molecules.summary-card :title="$resumen['titulo']" :items="$resumen['items']">
                        @if ($resumen['mostrarAccion'])
                            <x-slot:action>
                                <x-atoms.button :href="$resumen['accion']['href']" variant="outline" icon="arrow_forward" block>
                                    {{ $resumen['accion']['label'] }}
                                </x-atoms.button>
                            </x-slot:action>
                        @endif
                    </x-molecules.summary-card>
                @else
                    <x-molecules.empty-state
                        :icon="$resumen['icono']"
                        :title="$resumen['vacioTitulo']"
                        :detail="$resumen['vacioDetalle']"
                    >
                        @if ($resumen['mostrarAccion'])
                            <x-slot:action>
                                <x-atoms.button :href="$resumen['accion']['href']" variant="outline" icon="arrow_forward">
                                    {{ $resumen['accion']['label'] }}
                                </x-atoms.button>
                            </x-slot:action>
                        @endif
                    </x-molecules.empty-state>
                @endif
            @endforeach
        </x-slot:aside>
    @endif
    </x-molecules.form-layout>
</form>
