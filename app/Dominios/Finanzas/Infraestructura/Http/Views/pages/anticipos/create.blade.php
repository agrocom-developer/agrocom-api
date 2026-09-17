{{--
    Page: anticipos/create (GET /panel/anticipos/crear, panel.anticipos.create)
    Alta de un anticipo (HU-29, tarea 41) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Sin partial `_formulario` compartido
    con una edición: no existe caso de uso de edición (invariante de esta
    tarea, ver `Aplicacion/RegistrarAnticipo`) — este archivo ES el
    formulario completo.

    Datos esperados (ver AnticiposController::create()): la cáscara de
    CascaraPanel, más:
    - $personasDisponibles (Collection<int, string>): id => nombre, para el
      <select> de persona (compartido por la consulta y el alta).
    - $consultaDisponible (array{personaId: int, personaNombre: string,
      disponible: string}|null): resultado de la consulta de disponible de
      abajo, `null` si todavía no se consultó nada.

    La caja "Consultar disponible" es un <form method="GET"> propio (mismo
    action que esta página, con `?persona_id=`) que recarga la pantalla — no
    hace falta un endpoint ni JS aparte, mismo patrón que los filtros de
    `anticipos/index.blade.php` y `devengos/show.blade.php`. No es parte del
    criterio de aceptación (el servidor SIEMPRE revalida el tope al enviar el
    alta real, vía AnticipoExcedeTope) — es solo para que el encargado no
    descubra el tope recién al rechazo.

    Tras un error de validación (incluido el rechazo por tope, capturado en
    AnticiposController::store()), `old()` pisa los valores vacíos.

    Estilos en resources/css/pages/anticipos.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    // Si se llegó acá vía "Consultar disponible" (GET ?persona_id=), esa
    // misma persona queda preseleccionada en el formulario real — evita que
    // el encargado tenga que elegirla dos veces. `old()` manda si hubo un
    // envío previo con error (incluido el rechazo por tope): esa selección
    // es más reciente que la consulta.
    $personaId = old('persona_id', $consultaDisponible['personaId'] ?? '');
    $fecha = old('fecha', now()->toDateString());
    $monto = old('monto', '');
    $motivo = old('motivo', '');
@endphp

<x-templates.panel-shell :title="__('finanzas.anticipos.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('finanzas.anticipos.titulo_crear')"
    >
        <div class="ag-anticipos-form-page">
            <div class="ag-anticipos-form-page__consulta">
                <form method="GET" action="{{ route('panel.anticipos.create') }}" class="ag-anticipos-consulta">
                    <div class="ag-anticipos-consulta__texto">
                        <p class="ag-anticipos-consulta__titulo">{{ __('finanzas.anticipos.consulta_titulo') }}</p>
                        <p class="ag-anticipos-consulta__ayuda">{{ __('finanzas.anticipos.consulta_ayuda') }}</p>
                    </div>

                    <x-atoms.select
                        name="persona_id"
                        id="consulta-persona"
                        :label="__('finanzas.anticipos.campo_persona')"
                        :options="$personasDisponibles"
                        :value="$consultaDisponible !== null ? (string) $consultaDisponible['personaId'] : ''"
                        :placeholder="__('finanzas.anticipos.campo_persona_placeholder')"
                    />

                    <x-atoms.button type="submit" variant="outline" size="md" icon="search">
                        {{ __('finanzas.anticipos.consulta_boton') }}
                    </x-atoms.button>
                </form>

                @if ($consultaDisponible !== null)
                    <x-molecules.alert-strip variant="info" icon="payments" class="ag-anticipos-form-page__resultado">
                        {{ __('finanzas.anticipos.consulta_resultado', ['persona' => $consultaDisponible['personaNombre'], 'monto' => $consultaDisponible['disponible']]) }}
                    </x-molecules.alert-strip>
                @endif
            </div>

            <form method="POST" action="{{ route('panel.anticipos.store') }}" class="ag-anticipos-form" novalidate data-ag-anticipos-form>
                @csrf

                <x-organisms.page-header
                    :title="__('finanzas.anticipos.titulo_crear')"
                    :subtitle="__('finanzas.anticipos.subtitulo_form')"
                >
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.anticipos.index')" variant="outline" icon="arrow_back">
                            {{ __('finanzas.anticipos.volver') }}
                        </x-atoms.button>
                    </x-slot:actions>
                </x-organisms.page-header>

                @if ($errors->has('estado'))
                    <x-molecules.alert-strip variant="danger" icon="error" class="ag-anticipos-form-page__resultado">
                        {{ $errors->first('estado') }}
                    </x-molecules.alert-strip>
                @endif

                <x-molecules.form-section
                    :title="__('finanzas.anticipos.seccion_datos')"
                    :count="__('finanzas.anticipos.campos_contador', ['cantidad' => 4])"
                >
                    <x-atoms.select
                        name="persona_id"
                        id="persona_id"
                        :label="__('finanzas.anticipos.campo_persona')"
                        :options="$personasDisponibles"
                        :value="(string) $personaId"
                        :placeholder="__('finanzas.anticipos.campo_persona_placeholder')"
                        :error="$errors->first('persona_id')"
                        required
                    />

                    <x-atoms.input
                        type="number"
                        name="monto"
                        :label="__('finanzas.anticipos.campo_monto')"
                        :value="$monto"
                        min="0.01"
                        step="0.01"
                        required
                        :error="$errors->first('monto')"
                    />

                    <x-atoms.date
                        name="fecha"
                        :label="__('finanzas.anticipos.campo_fecha')"
                        :value="$fecha"
                        required
                        :error="$errors->first('fecha')"
                    />

                    <div class="ag-form-section__field--full">
                        <x-atoms.input
                            type="text"
                            name="motivo"
                            :label="__('finanzas.anticipos.campo_motivo')"
                            :value="$motivo"
                            :error="$errors->first('motivo')"
                        />
                    </div>
                </x-molecules.form-section>

                <x-organisms.form-actions-bar :status="__('finanzas.anticipos.estado_form')">
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.anticipos.index')" variant="outline">
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
