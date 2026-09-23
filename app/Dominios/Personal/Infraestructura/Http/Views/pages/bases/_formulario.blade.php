{{--
    Partial: formulario de base, compartido por create.blade.php y
    edit.blade.php (HU-26, tarea 37) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Sin sub-entidad repetible ni selects:
    una base es cuatro campos planos (nombre, ubicación, latitud, longitud).

    Homogeneizado con el patrón de Clientes y Cuadrillas (tarea 112): el
    cuerpo va en `molecules/form-layout` y, SOLO en edición, el aside con el
    resumen relacionado (§6.3.1) — una base recién creada no puede tener
    todavía personas, cuadrillas, equipos ni stock.

    Espera:
    - $base (PerBase|null): null en alta; el modelo en edición.
    - $resumenRelacionado (list<array{...}>|null): solo en edición, ver
      BasesController::resumenRelacionado(). `null`/ausente en alta.
    - $proveedorMapa (array{proveedor: 'google'|'leaflet', googleMapsApiKey: ?string}):
      resuelto por Compartido\Aplicacion\ResolverProveedorMapa — mismo
      contrato que usan los editores de mapa de Comercial (tarea 132).

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.

    Latitud/longitud (tarea 132): ya no se tipean a mano — el mapa embebido
    de abajo (`organisms/base-mapa-marcador.js`) coloca un único marcador
    (arrastrable, o un click lo reposiciona) y llena estos dos inputs, que
    siguen viajando OCULTOS con el mismo `name` de siempre. Sin marcador
    colocado (alta sin tocar el mapa todavía) quedan vacíos, igual que antes
    — son opcionales, ver CrearBaseRequest/ActualizarBaseRequest.
--}}
@php
    $esEdicion = $base !== null;
    $accion = $esEdicion ? route('panel.bases.update', $base) : route('panel.bases.store');
    $nombre = old('nombre', $base?->nombre ?? '');
    $ubicacion = old('ubicacion', $base?->ubicacion ?? '');
    $latitud = old('latitud', $base?->latitud ?? '');
    $longitud = old('longitud', $base?->longitud ?? '');
    $esGoogle = ($proveedorMapa['proveedor'] ?? 'leaflet') === 'google';
@endphp

<form method="POST" action="{{ $accion }}" class="ag-bases-form" novalidate data-ag-bases-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('personal.bases.titulo_editar') : __('personal.bases.titulo_crear')"
        :subtitle="__('personal.bases.subtitulo_form')"
    >
        <x-slot:actions>
            <x-molecules.boton-volver
                :href="route('panel.bases.index')"
                :label="__('personal.bases.volver')"
                :retorno="$esEdicion ? ['base_id' => $base->id] : []"
            />
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    <x-molecules.form-layout>
        <x-molecules.form-section
            :title="__('personal.bases.seccion_datos')"
            :count="__('personal.bases.campos_contador', ['cantidad' => 4])"
        >
            <x-atoms.input
                type="text"
                name="nombre"
                :label="__('personal.bases.campo_nombre')"
                :value="$nombre"
                required
                :error="$errors->first('nombre')"
            />

            <x-atoms.input
                type="text"
                name="ubicacion"
                :label="__('personal.bases.campo_ubicacion')"
                :value="$ubicacion"
                :error="$errors->first('ubicacion')"
            />

            <div class="ag-form-section__field--full">
                <span class="ag-input__label">{{ __('personal.bases.campo_coordenadas') }}</span>

                @error('latitud')
                    <p class="ag-input__error" role="alert">{{ $message }}</p>
                @enderror
                @error('longitud')
                    <p class="ag-input__error" role="alert">{{ $message }}</p>
                @enderror

                {{-- Los dos valores reales, ocultos — los llena el marcador
                     del mapa. DENTRO de este contenedor a propósito: el JS
                     busca sus referencias con `contenedor.querySelector(...)`
                     acotado a `[data-ag-base-mapa]`. --}}
                <div
                    class="ag-base-mapa"
                    data-ag-base-mapa
                    data-ag-base-mapa-proveedor="{{ $esGoogle ? 'google' : 'leaflet' }}"
                    @if ($esGoogle)
                        data-ag-base-mapa-google-key="{{ $proveedorMapa['googleMapsApiKey'] }}"
                        data-ag-base-mapa-error-google="{{ __('ui.errores.google_maps_no_disponible') }}"
                    @endif
                >
                    <input type="hidden" name="latitud" value="{{ $latitud }}" data-ag-base-latitud>
                    <input type="hidden" name="longitud" value="{{ $longitud }}" data-ag-base-longitud>

                    <div class="ag-base-mapa__lienzo" data-ag-base-mapa-lienzo>
                        <div class="ag-base-mapa__mapa" data-ag-base-mapa-mapa></div>
                    </div>

                    <span class="ag-base-mapa__coordenadas" data-ag-base-coordenadas-texto></span>
                </div>

                <p class="ag-input__help">{{ __('personal.bases.campo_coordenadas_ayuda') }}</p>
            </div>
        </x-molecules.form-section>

        <x-organisms.form-actions-bar :status="__('personal.bases.estado_form')">
            <x-slot:actions>
                <x-molecules.boton-volver :href="route('panel.bases.index')" :retorno="$esEdicion ? ['base_id' => $base->id] : []" cancelar />
                <x-atoms.button type="submit" variant="primary">
                    {{ __('ui.action.save') }}
                </x-atoms.button>
            </x-slot:actions>
        </x-organisms.form-actions-bar>

        @if ($esEdicion)
            <x-slot:aside>
                @foreach ($resumenRelacionado ?? [] as $resumen)
                    @if ($resumen['tieneDatos'])
                        <x-molecules.summary-card :title="$resumen['titulo']" :items="$resumen['items']">
                            @if ($resumen['acciones'] !== [])
                                <x-slot:action>
                                    @foreach ($resumen['acciones'] as $accionResumen)
                                        <x-atoms.button :href="$accionResumen['href']" variant="outline" :icon="$accionResumen['icono'] ?? 'arrow_forward'" block>
                                            {{ $accionResumen['label'] }}
                                        </x-atoms.button>
                                    @endforeach
                                </x-slot:action>
                            @endif
                        </x-molecules.summary-card>
                    @else
                        <x-molecules.empty-state :icon="$resumen['icono']" :title="$resumen['vacioTitulo']" :detail="$resumen['vacioDetalle']">
                            @if ($resumen['acciones'] !== [])
                                <x-slot:action>
                                    @foreach ($resumen['acciones'] as $accionResumen)
                                        <x-atoms.button :href="$accionResumen['href']" variant="outline" :icon="$accionResumen['icono'] ?? 'add'">
                                            {{ $accionResumen['label'] }}
                                        </x-atoms.button>
                                    @endforeach
                                </x-slot:action>
                            @endif
                        </x-molecules.empty-state>
                    @endif
                @endforeach
            </x-slot:aside>
        @endif
    </x-molecules.form-layout>
</form>
