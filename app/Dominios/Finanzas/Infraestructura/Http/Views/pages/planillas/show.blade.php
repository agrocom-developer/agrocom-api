{{--
    Page: planillas/show (GET /panel/planillas/{planilla}, panel.planillas.show)
    Detalle de una planilla del período (HU-30, tarea 44): arquetipo
    Detalle, §6 de docs/diseno/guia_pantalla_panel.md — resumen → tabla de
    renglones por persona, con recibo en PDF cuando la planilla ya está
    aprobada. Mismo molde que trabajos/show.blade.php.

    Datos esperados (ver PlanillasController::show()): la cáscara de
    CascaraPanel, más:
    - $planilla (Planilla).
    - $detalles (Collection<PlanillaDetalle>): persona ascendente.
    - $etiquetasPersona (array<int, string>): persona_id => nombre.
    - $puedeAprobar (bool): gatea el botón "Aprobar planilla" (además de que
      la planilla esté en borrador — las dos condiciones, no una sola).

    Gateada por `finanzas.planilla.ver`; el botón de aprobar se oculta con
    `@if ($puedeAprobar ...)` (presentación, no autorización — el servidor
    revalida `finanzas.planilla.aprobar` en PlanillasController::aprobar()).

    Estilos en resources/css/pages/planilla.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('finanzas.planillas.detalle_titulo', ['periodo' => $planilla->periodo])" :tema="$tema">
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
        :vista-actual="__('finanzas.planillas.titulo')"
    >
        <div class="ag-planilla-detalle">
            <x-atoms.button href="{{ route('panel.planillas.index') }}" variant="text" size="sm" icon="arrow_back">
                {{ __('finanzas.planillas.volver') }}
            </x-atoms.button>

            <x-organisms.page-header
                :title="__('finanzas.planillas.detalle_titulo', ['periodo' => $planilla->periodo])"
                :subtitle="__('finanzas.planillas.subtitulo')"
            >
                @if ($puedeAprobar && $planilla->estado->value === 'borrador')
                    <x-slot:actions>
                        <form
                            method="POST"
                            action="{{ route('panel.planillas.aprobar', $planilla) }}"
                            onsubmit="return confirm('{{ __('finanzas.planillas.confirmar_aprobar') }}')"
                        >
                            @csrf
                            <x-atoms.button type="submit" variant="primary" icon="check_circle">
                                {{ __('finanzas.planillas.aprobar_accion') }}
                            </x-atoms.button>
                        </form>
                    </x-slot:actions>
                @endif
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-planilla-detalle__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @error('estado')
                <x-molecules.alert-strip variant="danger" icon="error" class="ag-planilla-detalle__aviso">
                    {{ $message }}
                </x-molecules.alert-strip>
            @enderror

            <div class="ag-planilla-detalle__resumen">
                <span class="ag-planilla-detalle__campo">
                    <strong>{{ __('finanzas.planillas.col_estado') }}</strong>
                    <x-atoms.badge :variant="$planilla->estado->value === 'aprobada' ? 'success' : 'warning'">
                        {{ __("finanzas.planillas.estado.{$planilla->estado->value}") }}
                    </x-atoms.badge>
                </span>
                <span class="ag-planilla-detalle__campo">
                    <strong>{{ __('finanzas.planillas.detalle_total') }}</strong>
                    {{ __('finanzas.planillas.monto_valor', ['monto' => $planilla->total]) }}
                </span>
                @if ($planilla->estado->value === 'aprobada')
                    <span class="ag-planilla-detalle__campo">
                        <strong>{{ __('finanzas.planillas.detalle_aprobacion') }}</strong>
                        {{ __('finanzas.planillas.detalle_aprobada_por', [
                            'usuario' => $aprobadaPorNombre ?? "#{$planilla->aprobada_por}",
                            'fecha' => optional($planilla->aprobada_en)->format('d/m/Y H:i'),
                        ]) }}
                    </span>
                @endif
            </div>

            @if ($detalles->isEmpty())
                <x-molecules.alert-strip variant="info" icon="event_note" class="ag-planilla-detalle__aviso">
                    {{ __('finanzas.planillas.detalle_vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-planilla-detalle__tabla" role="table">
                    <div class="ag-planilla-detalle__head" role="row">
                        <span role="columnheader">{{ __('finanzas.planillas.col_persona') }}</span>
                        <span role="columnheader">{{ __('finanzas.planillas.col_devengado') }}</span>
                        <span role="columnheader">{{ __('finanzas.planillas.col_anticipos') }}</span>
                        <span role="columnheader">{{ __('finanzas.planillas.col_neto') }}</span>
                        <span role="columnheader">{{ __('finanzas.planillas.col_recibo') }}</span>
                    </div>

                    @foreach ($detalles as $detalle)
                        <div class="ag-planilla-detalle__fila" role="row">
                            <span role="cell" class="ag-planilla-detalle__persona">{{ $etiquetasPersona[$detalle->persona_id] ?? "#{$detalle->persona_id}" }}</span>
                            <span role="cell" class="ag-planilla-detalle__cifra">{{ __('finanzas.planillas.monto_valor', ['monto' => $detalle->devengado]) }}</span>
                            <span role="cell" class="ag-planilla-detalle__cifra">{{ __('finanzas.planillas.monto_valor', ['monto' => $detalle->anticipos]) }}</span>
                            <span role="cell" class="ag-planilla-detalle__cifra">{{ __('finanzas.planillas.monto_valor', ['monto' => $detalle->neto]) }}</span>
                            <span role="cell">
                                @if ($detalle->pdf_path !== null)
                                    <x-atoms.button href="{{ route('panel.planillas.recibo', [$planilla, $detalle]) }}" variant="outline" size="sm" icon="picture_as_pdf">
                                        {{ __('finanzas.planillas.ver_recibo') }}
                                    </x-atoms.button>
                                @else
                                    <span class="ag-planilla-detalle__recibo-pendiente">{{ __('finanzas.planillas.recibo_pendiente') }}</span>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
