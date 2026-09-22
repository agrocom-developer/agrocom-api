{{--
    Page: sesiones/validacion (GET /panel/sesiones/validacion, panel.sesiones.validacion.index)
    Cola de validación del jefe de campo (HU-14, tarea 14): sesiones
    `cerrado` pendientes de aprobación, con acción de validar y de rechazar
    (motivo obligatorio — invariante 2 de CLAUDE.md, mecanismo documentado en
    runs/14.md).

    Homogeneizada en la tarea 114, SOLO en su capa de listado: la lista de
    tarjetas pasa a `molecules/index-table` con sus acciones en
    `organisms/row-actions`, y el motivo del rechazo deja de vivir suelto en
    la fila para viajar dentro del `confirm-modal` que confirma el rechazo
    (mismo patrón que el motivo de pausa de ordenes/_orden-modales). Nada de
    la decisión de quién puede validar cambió: sigue donde estaba, en
    `ValidacionSesionesController` y en los casos de uso.

    Datos esperados (ver ValidacionSesionesController::index()): la cáscara
    de CascaraPanel, más:
    - $sesiones (Collection<Sesion>): `cerrado` y sin `anulada_en`, fin más
      antiguo primero (la cola se vacía en orden de llegada).
    - $personaId (int|null): persona del jefe autenticado — si coincide con
      `piloto_id` de la fila, esa fila no ofrece la acción de validar/rechazar
      (invariante 4: el piloto no decide sobre su propia sesión). El servidor
      revalida esto igual en ValidacionSesionesController — ocultar el botón
      es presentación, no la única barrera.

    Sin toolbar de filtros: la cola es el universo entero (lo que está
    `cerrado` y sin anular), y se vacía sola a medida que se decide cada
    sesión — no hay nada que acotar.

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
        :zona-horaria="$zonaHoraria ?? null"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :version="$version"
        :vista-actual="__('operaciones.sesiones_validacion.titulo')"
    >
        <div class="ag-sesiones-validacion">
            <x-organisms.page-header
                :title="__('operaciones.sesiones_validacion.titulo')"
                :subtitle="__('operaciones.sesiones_validacion.subtitulo')"
            />

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('motivo') || $errors->has('sesion'))
                <x-molecules.alert-strip variant="danger" icon="error">
                    {{ $errors->first('motivo') ?: $errors->first('sesion') }}
                </x-molecules.alert-strip>
            @endif

            @if ($sesiones->isEmpty())
                <x-molecules.empty-state
                    icon="flight"
                    :title="__('operaciones.sesiones_validacion.vacio_titulo')"
                    :detail="__('operaciones.sesiones_validacion.vacio_detalle')"
                />
            @else
                <x-molecules.index-table columns="3rem minmax(0, 0.8fr) minmax(0, 0.8fr) minmax(0, 1.1fr) minmax(0, 1fr) minmax(0, 1.2fr) minmax(0, 1.2fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('operaciones.sesiones_validacion.col_sesion') }}</span>
                        <span role="columnheader">{{ __('operaciones.sesiones_validacion.col_trabajo') }}</span>
                        <span role="columnheader">{{ __('operaciones.sesiones_validacion.col_piloto') }}</span>
                        <span role="columnheader">{{ __('operaciones.sesiones_validacion.col_hectareas') }}</span>
                        <span role="columnheader">{{ __('operaciones.sesiones_validacion.col_fin') }}</span>
                        <span role="columnheader">{{ __('operaciones.sesiones_validacion.col_motivo_cierre') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($sesiones as $sesion)
                        @php
                            // Invariante 4, a nivel de PERSONA: el piloto de la sesión no
                            // decide sobre su propio vuelo. El servidor lo revalida.
                            $esPiloto = $personaId !== null && (int) $sesion->piloto_id === (int) $personaId;
                            $formIdValidar = "sesion-validar-form-{$sesion->id}";
                            $formIdRechazar = "sesion-rechazar-form-{$sesion->id}";
                            $modalIdValidar = "sesion-validar-modal-{$sesion->id}";
                            $modalIdRechazar = "sesion-rechazar-modal-{$sesion->id}";
                        @endphp
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">{{ $loop->iteration }}</span>
                            <span role="cell" class="ag-sesiones-validacion__mono">#{{ $sesion->id }}</span>
                            <span role="cell" class="ag-sesiones-validacion__mono">#{{ $sesion->trabajo_id }}</span>
                            <span role="cell">{{ __('operaciones.trabajos.sesion_piloto', ['id' => $sesion->piloto_id]) }}</span>
                            <span role="cell" class="ag-sesiones-validacion__mono">{{ $sesion->hectareas_declaradas }}</span>
                            <span role="cell" class="ag-sesiones-validacion__mono">
                                {{ $sesion->fin?->format('d/m/Y H:i') ?? __('operaciones.trabajos.sin_fin') }}
                            </span>
                            <span role="cell">
                                @if ($sesion->motivo_cierre !== null)
                                    <x-atoms.badge variant="neutral">
                                        {{ __("operaciones.trabajos.motivo_cierre.{$sesion->motivo_cierre}") }}
                                    </x-atoms.badge>
                                @else
                                    <span class="ag-sesiones-validacion__atenuado">{{ __('operaciones.trabajos.sin_fin') }}</span>
                                @endif
                            </span>

                            <span role="cell" class="ag-index-table__acciones">
                                @if ($esPiloto)
                                    {{-- Sin acciones: es su propia sesión. El badge dice por qué;
                                         el servidor rechaza el POST igual si se fuerza. --}}
                                    <x-atoms.badge variant="warning" icon="block" :title="__('operaciones.sesiones_validacion.propia')">
                                        {{ __('operaciones.sesiones_validacion.propia_corto') }}
                                    </x-atoms.badge>
                                @else
                                    {{-- Forms y modales FUERA de row-actions: ese organism repite su
                                         slot dos veces (visible/menú), y un <form> o un modal con id
                                         ahí adentro quedaría duplicado. Los botones de adentro son
                                         solo triggers; envían por su atributo `form`. --}}
                                    <form id="{{ $formIdValidar }}" method="POST" action="{{ route('panel.sesiones.validacion.validar', $sesion) }}" hidden>
                                        @csrf
                                    </form>

                                    <form id="{{ $formIdRechazar }}" method="POST" action="{{ route('panel.sesiones.validacion.rechazar', $sesion) }}" hidden>
                                        @csrf
                                    </form>

                                    <x-molecules.confirm-modal
                                        :id="$modalIdValidar"
                                        :form-id="$formIdValidar"
                                        :title="__('operaciones.sesiones_validacion.confirmar_validar_titulo')"
                                        :message="__('operaciones.sesiones_validacion.confirmar_validar', ['id' => $sesion->id])"
                                        :confirm-label="__('operaciones.sesiones_validacion.validar')"
                                        tone="success"
                                        modal-icon="check_circle"
                                    >
                                        {{-- «Estado actual → estado destino», la misma simbología de todo
                                             objeto con máquina de estados (21/9/2026). El rechazo no la
                                             lleva: no cambia el estado de la sesión, la anula. --}}
                                        <x-molecules.state-transition
                                            :from-label="__('operaciones.sesion.estado.'.$sesion->estado->value)"
                                            :from-tone="\App\Dominios\Operaciones\Dominio\TonoEstadoSesion::deSesion($sesion->estado)->value"
                                            :to-label="__('operaciones.sesion.estado.validado')"
                                            :to-tone="\App\Dominios\Operaciones\Dominio\TonoEstadoSesion::deSesion(\App\Dominios\Operaciones\Dominio\EstadoSesion::Validado)->value"
                                            :label="__('operaciones.sesiones_validacion.estado_cambio_de_a', [
                                                'desde' => __('operaciones.sesion.estado.'.$sesion->estado->value),
                                                'hacia' => __('operaciones.sesion.estado.validado'),
                                            ])"
                                        />
                                    </x-molecules.confirm-modal>

                                    <x-molecules.confirm-modal
                                        :id="$modalIdRechazar"
                                        :form-id="$formIdRechazar"
                                        :title="__('operaciones.sesiones_validacion.confirmar_rechazar_titulo')"
                                        :message="__('operaciones.sesiones_validacion.confirmar_rechazar', ['id' => $sesion->id])"
                                        :confirm-label="__('operaciones.sesiones_validacion.rechazar')"
                                        tone="danger"
                                        modal-icon="cancel"
                                    >
                                        {{-- El motivo viaja con la confirmación: el control vive en el
                                             modal y se asocia a su <form> por el atributo `form`. --}}
                                        <x-atoms.textarea
                                            :id="'motivo-'.$sesion->id"
                                            name="motivo"
                                            form="{{ $formIdRechazar }}"
                                            :label="__('operaciones.sesiones_validacion.motivo_label')"
                                            :placeholder="__('operaciones.sesiones_validacion.motivo_placeholder')"
                                            :help="__('operaciones.sesiones_validacion.motivo_ayuda')"
                                            rows="2"
                                            required
                                            maxlength="500"
                                        />
                                    </x-molecules.confirm-modal>

                                    <x-organisms.row-actions>
                                        <x-atoms.button
                                            type="button"
                                            data-bs-toggle="modal"
                                            data-bs-target="#{{ $modalIdValidar }}"
                                            variant="success-outline"
                                            size="sm"
                                            icon="check_circle"
                                        >
                                            {{ __('operaciones.sesiones_validacion.validar') }}
                                        </x-atoms.button>

                                        <x-atoms.button
                                            type="button"
                                            data-bs-toggle="modal"
                                            data-bs-target="#{{ $modalIdRechazar }}"
                                            variant="danger-outline"
                                            size="sm"
                                            icon="cancel"
                                        >
                                            {{ __('operaciones.sesiones_validacion.rechazar') }}
                                        </x-atoms.button>
                                    </x-organisms.row-actions>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
