{{--
    Page: sesiones/validacion (GET /panel/sesiones/validacion, panel.sesiones.validacion.index)
    Cola de validación del jefe de campo (HU-14, tarea 14): sesiones
    `cerrado` pendientes de aprobación, con acción de validar y de rechazar
    (motivo obligatorio — invariante 2 de CLAUDE.md, mecanismo documentado en
    runs/14.md).

    Datos esperados (ver ValidacionSesionesController::index()): la cáscara
    de CascaraPanel, más:
    - $sesiones (Collection<Sesion>): `cerrado` y sin `anulada_en`, fin más
      antiguo primero (la cola se vacía en orden de llegada).
    - $personaId (int|null): persona del jefe autenticado — si coincide con
      `piloto_id` de la fila, esa fila no ofrece la acción de validar/rechazar
      (invariante 4: el piloto no decide sobre su propia sesión). El servidor
      revalida esto igual en ValidacionSesionesController — ocultar el botón
      es presentación, no la única barrera.

    Gateada por el permiso `operaciones.sesion.validar`, verificado
    server-side en el controlador.

    Estilos en resources/css/pages/sesiones-validacion.css — cero color
    hardcodeado (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('operaciones.sesiones_validacion.titulo')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campana="$campana"
        :periodo="$periodo"
        :version="$version"
    >
        <x-organisms.page-header
            :title="__('operaciones.sesiones_validacion.titulo')"
            :subtitle="__('operaciones.sesiones_validacion.subtitulo')"
        />

        @if (session('estado'))
            <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-sesiones-validacion__aviso">
                {{ session('estado') }}
            </x-molecules.alert-strip>
        @endif

        @if ($errors->has('motivo'))
            <x-molecules.alert-strip variant="danger" icon="error" class="ag-sesiones-validacion__aviso">
                {{ $errors->first('motivo') }}
            </x-molecules.alert-strip>
        @endif

        @if ($sesiones->isEmpty())
            <x-molecules.alert-strip variant="info" icon="flight" class="ag-sesiones-validacion__aviso">
                {{ __('operaciones.sesiones_validacion.vacio') }}
            </x-molecules.alert-strip>
        @else
            <div class="ag-sesiones-validacion__lista">
                @foreach ($sesiones as $sesion)
                    @php
                        $esPiloto = $personaId !== null && (int) $sesion->piloto_id === (int) $personaId;
                    @endphp

                    <article class="ag-sesiones-validacion__tarjeta" role="group" aria-label="{{ __('operaciones.sesiones_validacion.sesion_titulo', ['id' => $sesion->id]) }}">
                        <div class="ag-sesiones-validacion__datos">
                            <span class="ag-sesiones-validacion__campo">
                                <strong>{{ __('operaciones.sesiones_validacion.col_sesion') }}</strong> #{{ $sesion->id }}
                            </span>
                            <span class="ag-sesiones-validacion__campo">
                                <strong>{{ __('operaciones.sesiones_validacion.col_trabajo') }}</strong> #{{ $sesion->trabajo_id }}
                            </span>
                            <span class="ag-sesiones-validacion__campo">
                                <strong>{{ __('operaciones.sesiones_validacion.col_piloto') }}</strong>
                                {{ __('operaciones.trabajos.sesion_piloto', ['id' => $sesion->piloto_id]) }}
                            </span>
                            <span class="ag-sesiones-validacion__campo">
                                <strong>{{ __('operaciones.sesiones_validacion.col_hectareas') }}</strong> {{ $sesion->hectareas_declaradas }}
                            </span>
                            <span class="ag-sesiones-validacion__campo">
                                <strong>{{ __('operaciones.sesiones_validacion.col_fin') }}</strong> {{ $sesion->fin?->format('d/m/Y H:i') ?? __('operaciones.trabajos.sin_fin') }}
                            </span>
                            @if ($sesion->motivo_cierre !== null)
                                <span class="ag-sesiones-validacion__campo">
                                    <strong>{{ __('operaciones.sesiones_validacion.col_motivo_cierre') }}</strong>
                                    {{ __("operaciones.trabajos.motivo_cierre.{$sesion->motivo_cierre}") }}
                                </span>
                            @endif
                        </div>

                        @if ($esPiloto)
                            <x-molecules.alert-strip variant="warning" icon="block" class="ag-sesiones-validacion__bloqueo">
                                {{ __('operaciones.sesiones_validacion.propia') }}
                            </x-molecules.alert-strip>
                        @else
                            <div class="ag-sesiones-validacion__acciones">
                                <form method="POST" action="{{ route('panel.sesiones.validacion.validar', $sesion) }}">
                                    @csrf
                                    <x-atoms.button type="submit" variant="primary" size="sm" icon="check_circle">
                                        {{ __('operaciones.sesiones_validacion.validar') }}
                                    </x-atoms.button>
                                </form>

                                <form method="POST" action="{{ route('panel.sesiones.validacion.rechazar', $sesion) }}" class="ag-sesiones-validacion__rechazo">
                                    @csrf
                                    <div class="ag-input">
                                        <label for="motivo-{{ $sesion->id }}" class="ag-input__label">
                                            {{ __('operaciones.sesiones_validacion.motivo_label') }}
                                            <span class="ag-input__required" aria-hidden="true">*</span>
                                        </label>
                                        <div class="ag-input__control">
                                            <textarea
                                                id="motivo-{{ $sesion->id }}"
                                                name="motivo"
                                                class="ag-input__field"
                                                rows="2"
                                                required
                                                maxlength="500"
                                                placeholder="{{ __('operaciones.sesiones_validacion.motivo_placeholder') }}"
                                            ></textarea>
                                        </div>
                                    </div>
                                    <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="cancel">
                                        {{ __('operaciones.sesiones_validacion.rechazar') }}
                                    </x-atoms.button>
                                </form>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </x-templates.panel-layout>
</x-templates.panel-shell>
