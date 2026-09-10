{{--
    Page: gastos/create (GET /panel/gastos/crear, panel.gastos.create)
    Alta de un gasto (HU-33, tarea 47) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Sin partial `_formulario` compartido
    con una edición: no existe caso de uso de edición (invariante de esta
    tarea, ver `Aplicacion/CrearGasto`) — este archivo ES el formulario
    completo.

    Datos esperados (ver GastosController::create()): la cáscara de
    CascaraPanel, más:
    - $rubrosConSubrubros (Collection<Rubro> con `subrubros` cargado): arma
      el <select> de rubro y la lista completa de subrubros, que
      `resources/js/pages/gastos-form.js` filtra en cliente según el rubro
      elegido usando el mapa subrubro→rubro que viaja como
      `data-mapa-rubro-subrubro` (JSON) en el propio `<select>` de subrubro
      (tarea 76: `x-atoms.select` no soporta atributos por `<option>`).
    - $equiposDisponibles / $basesDisponibles / $trabajosDisponibles /
      $campaniasDisponibles (Collection<int, string>): id => etiqueta, para
      los <select> opcionales de imputación. `$equiposDisponibles` (tarea 73,
      HU-50) se ofrece PRIMERO — es el camino principal de imputación.
      `$campaniasDisponibles` ya viene filtrada a campañas no `cerrada` (ADR
      0015 punto 6) — ver GastosController::campaniasNoCerradas().

    `enctype="multipart/form-data"`: primera subida de archivo humana desde
    el panel (a diferencia de `ope_evidencias`, que sube la app de campo) —
    ver docblock de `Aplicacion/CrearGasto`.

    Tras un error de validación, `old()` pisa los valores vacíos (el archivo
    NO se puede repoblar por HTML — el encargado tiene que volver a
    adjuntarlo, comportamiento estándar de `<input type="file">`).

    Estilos en resources/css/pages/gastos.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    $fecha = old('fecha', now()->toDateString());
    $rubroId = old('rubro_id', '');
    $subrubroId = old('subrubro_id', '');
    $cantidad = old('cantidad', '');
    $precioUnitario = old('precio_unitario', '');
    $equipoTrabajoId = old('equipo_trabajo_id', '');
    $baseId = old('base_id', '');
    $trabajoId = old('trabajo_id', '');
    $campaniaId = old('campania_id', '');
    $subrubrosDisponibles = $rubrosConSubrubros->flatMap->subrubros;
    $subrubrosOpciones = $subrubrosDisponibles->pluck('nombre', 'id');
    $mapaRubroSubrubro = $subrubrosDisponibles->mapWithKeys(fn ($subrubro) => [$subrubro->id => $subrubro->rubro_id]);
@endphp

<x-templates.panel-shell :title="__('finanzas.gastos.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('finanzas.gastos.titulo_crear')"
    >
        <div class="ag-gastos-form-page">
            <form
                method="POST"
                action="{{ route('panel.gastos.store') }}"
                enctype="multipart/form-data"
                class="ag-gastos-form"
                novalidate
                data-ag-gastos-form
            >
                @csrf

                <x-organisms.page-header
                    :title="__('finanzas.gastos.titulo_crear')"
                    :subtitle="__('finanzas.gastos.subtitulo_form')"
                >
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.gastos.index') }}" variant="outline" icon="arrow_back">
                            {{ __('finanzas.gastos.volver') }}
                        </x-atoms.button>
                    </x-slot:actions>
                </x-organisms.page-header>

                <x-molecules.form-section
                    :title="__('finanzas.gastos.seccion_datos')"
                    :count="__('finanzas.gastos.campos_contador', ['cantidad' => 10])"
                >
                    <x-atoms.date
                        name="fecha"
                        label="{{ __('finanzas.gastos.campo_fecha') }}"
                        value="{{ $fecha }}"
                        required
                        error="{{ $errors->first('fecha') }}"
                    />

                    <x-atoms.select
                        name="rubro_id"
                        label="{{ __('finanzas.gastos.campo_rubro') }}"
                        placeholder="{{ __('finanzas.gastos.campo_rubro_placeholder') }}"
                        :options="$rubrosConSubrubros->pluck('nombre', 'id')"
                        value="{{ $rubroId }}"
                        required
                        error="{{ $errors->first('rubro_id') }}"
                        data-ag-gasto-rubro
                    />

                    <x-atoms.select
                        name="subrubro_id"
                        label="{{ __('finanzas.gastos.campo_subrubro') }}"
                        placeholder="{{ __('finanzas.gastos.campo_subrubro_placeholder') }}"
                        :options="$subrubrosOpciones"
                        value="{{ $subrubroId }}"
                        error="{{ $errors->first('subrubro_id') }}"
                        data-ag-gasto-subrubro
                        data-mapa-rubro-subrubro="{{ $mapaRubroSubrubro->toJson() }}"
                    />

                    <x-atoms.input
                        type="number"
                        name="cantidad"
                        label="{{ __('finanzas.gastos.campo_cantidad') }}"
                        value="{{ $cantidad }}"
                        min="0.01"
                        step="0.01"
                        required
                        error="{{ $errors->first('cantidad') }}"
                    />

                    <x-atoms.input
                        type="number"
                        name="precio_unitario"
                        label="{{ __('finanzas.gastos.campo_precio_unitario') }}"
                        value="{{ $precioUnitario }}"
                        min="0.01"
                        step="0.01"
                        required
                        error="{{ $errors->first('precio_unitario') }}"
                    />

                    <x-atoms.select
                        name="equipo_trabajo_id"
                        label="{{ __('finanzas.gastos.campo_equipo') }}"
                        placeholder="{{ __('finanzas.gastos.campo_equipo_placeholder') }}"
                        :options="$equiposDisponibles"
                        value="{{ $equipoTrabajoId }}"
                        error="{{ $errors->first('equipo_trabajo_id') }}"
                    />

                    <x-atoms.select
                        name="base_id"
                        label="{{ __('finanzas.gastos.campo_base') }}"
                        placeholder="{{ __('finanzas.gastos.campo_base_placeholder') }}"
                        :options="$basesDisponibles"
                        value="{{ $baseId }}"
                        error="{{ $errors->first('base_id') }}"
                    />

                    <x-atoms.select
                        name="trabajo_id"
                        label="{{ __('finanzas.gastos.campo_trabajo') }}"
                        placeholder="{{ __('finanzas.gastos.campo_trabajo_placeholder') }}"
                        :options="$trabajosDisponibles"
                        value="{{ $trabajoId }}"
                        error="{{ $errors->first('trabajo_id') }}"
                    />

                    <x-atoms.select
                        name="campania_id"
                        label="{{ __('finanzas.gastos.campo_campania') }}"
                        placeholder="{{ __('finanzas.gastos.campo_campania_placeholder') }}"
                        :options="$campaniasDisponibles"
                        value="{{ $campaniaId }}"
                        help="{{ __('finanzas.gastos.campo_campania_ayuda') }}"
                        error="{{ $errors->first('campania_id') }}"
                    />

                    <div class="ag-form-section__field--full">
                        <x-atoms.input
                            type="file"
                            name="comprobante"
                            label="{{ __('finanzas.gastos.campo_comprobante') }}"
                            accept="image/jpeg,image/png,application/pdf"
                            help="{{ __('finanzas.gastos.campo_comprobante_ayuda') }}"
                            error="{{ $errors->first('comprobante') }}"
                        />
                    </div>
                </x-molecules.form-section>

                <x-organisms.form-actions-bar :status="__('finanzas.gastos.estado_form')">
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.gastos.index') }}" variant="outline">
                            {{ __('ui.action.cancel') }}
                        </x-atoms.button>
                        <x-atoms.button type="submit" variant="primary">
                            {{ __('ui.action.save') }}
                        </x-atoms.button>
                    </x-slot:actions>
                </x-organisms.form-actions-bar>
            </form>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
