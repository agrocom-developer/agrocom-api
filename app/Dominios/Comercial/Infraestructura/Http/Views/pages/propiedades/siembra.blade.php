{{--
    Page: propiedades/siembra (GET/POST /panel/propiedades/{propiedad}/siembra,
    panel.propiedades.siembra[.guardar])
    Qué se sembró en cada lote de la propiedad, por campaña (HU-48, tarea 71,
    etapa 3; ADR 0015 punto 4). Se entra desde la ficha de la propiedad, gateada
    por `comercial.propiedad.editar` (mismo permiso que editar la propiedad: no es
    un ABM propio).

    Datos esperados (ver SiembraController::mostrar()): la cáscara de
    CascaraPanel, más:
    - $propiedad (Propiedad, con `lotes` cargada).
    - $campanias (Collection<int, string>): id => código, campañas del
      CLIENTE DUEÑO de esta propiedad, la más reciente primero. Vacía si el
      cliente todavía no tiene ninguna.
    - $campaniaId (int|null): la campaña que se está mostrando — de la
      querystring, o la primera de $campanias si no vino ninguna.
    - $siembraPorLote (Collection<int, LoteCampania>): lote_id => su
      siembra en $campaniaId, si existe.
    - $cultivosDisponibles (Collection<int, string>): id => nombre.

    Cambiar de campaña en el selector de arriba es un GET nuevo (recarga la
    página con otra $campaniaId): la siembra de la campaña anterior no se
    toca hasta que se vuelva a guardar el formulario de ESA campaña — nunca
    se pisan entre sí (GuardarSiembraCampania siempre filtra por
    campania_id).
--}}
<x-templates.panel-shell :title="__('comercial.siembra.titulo', ['propiedad' => $propiedad->nombre])" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :zona-horaria="$zonaHoraria ?? null"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :version="$version"
        :vista-actual="__('comercial.siembra.titulo', ['propiedad' => $propiedad->nombre])"
    >
        <div class="ag-siembra">
            <x-organisms.page-header
                :title="__('comercial.siembra.titulo', ['campo' => $propiedad->nombre])"
                :subtitle="__('comercial.siembra.subtitulo')"
            >
                <x-slot:actions>
                    <x-atoms.button href="{{ route('panel.propiedades.index') }}" variant="outline" icon="arrow_back">
                        {{ __('comercial.siembra.volver') }}
                    </x-atoms.button>
                </x-slot:actions>
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-siembra__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('campania_id'))
                <x-molecules.alert-strip variant="danger" icon="error" class="ag-siembra__aviso">
                    {{ $errors->first('campania_id') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('lotes'))
                <x-molecules.alert-strip variant="danger" icon="error" class="ag-siembra__aviso">
                    {{ $errors->first('lotes') }}
                </x-molecules.alert-strip>
            @endif

            @if ($campanias->isEmpty())
                <x-molecules.alert-strip variant="info" icon="event_busy" class="ag-siembra__aviso">
                    {{ __('comercial.siembra.sin_campanias') }}

                    @puede('campania.campania.crear')
                        <x-atoms.button href="{{ route('panel.campanias.create') }}" variant="text" size="sm">
                            {{ __('comercial.siembra.crear_campania') }}
                        </x-atoms.button>
                    @endpuede
                </x-molecules.alert-strip>
            @else
                <form method="GET" action="{{ route('panel.propiedades.siembra', $propiedad) }}" class="ag-filtros ag-siembra__filtros">
                    <x-atoms.select
                        name="campania_id"
                        id="campania_id"
                        label="{{ __('comercial.siembra.campo_campania') }}"
                        :options="$campanias"
                        :value="$campaniaId"
                    />

                    <div class="ag-filtros__acciones ag-siembra__filtros-acciones">
                        <x-atoms.button type="submit" variant="outline" size="md" icon="visibility">
                            {{ __('comercial.siembra.ver') }}
                        </x-atoms.button>
                    </div>
                </form>

                <form method="POST" action="{{ route('panel.propiedades.siembra.guardar', $propiedad) }}" class="ag-siembra-form" novalidate>
                    @csrf
                    <input type="hidden" name="campania_id" value="{{ $campaniaId }}">

                    <x-molecules.form-section
                        :title="__('comercial.siembra.seccion_lotes')"
                        :count="__('comercial.siembra.lotes_contador', ['cantidad' => $propiedad->lotes->count()])"
                    >
                        <div class="ag-form-section__field--full ag-siembra-form__lotes">
                            @foreach ($propiedad->lotes as $indice => $lote)
                                @include('comercial::pages.propiedades._siembra-fila', [
                                    'indice' => $indice,
                                    'lote' => $lote,
                                    'siembra' => $siembraPorLote->get($lote->id),
                                    'cultivosDisponibles' => $cultivosDisponibles,
                                ])
                            @endforeach
                        </div>
                    </x-molecules.form-section>

                    <x-organisms.form-actions-bar :status="__('comercial.siembra.estado_form')">
                        <x-slot:actions>
                            <x-atoms.button href="{{ route('panel.propiedades.index') }}" variant="outline">
                                {{ __('ui.action.cancel') }}
                            </x-atoms.button>
                            <x-atoms.button type="submit" variant="primary">
                                {{ __('ui.action.save') }}
                            </x-atoms.button>
                        </x-slot:actions>
                    </x-organisms.form-actions-bar>
                </form>
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
