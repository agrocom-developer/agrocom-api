{{--
    Parcial: pestaña "Bitácora y configuración" del administrador de
    plataforma (tarea 139) — lo último que quedó en la bitácora de auditoría y
    los accesos directos a la configuración del sistema y a la organización.

    Los accesos son solo enlaces: la configuración no se embebe acá, se edita
    en su pantalla. Cada sección se gatea con el permiso de su pantalla, así
    que puede faltar cualquiera (ver el `@isset`: `ArmarDashboard` ya decidió
    qué claves existen).

    Bajo 768 px la tabla no scrollea: cada fila se apila y `data-label`
    rotula cada dato (ver dashboard.css).

    Espera, cada una opcional:
    - $secciones['bitacora_reciente'] (list<FilaBitacora>), ver
      ArmarDashboard::bitacoraReciente(): del más reciente al más viejo, con
      el instante ya en la zona de quien mira.
    - $secciones['acceso_configuracion'] y $secciones['acceso_organizacion']:
      solo su presencia importa.
--}}
@php
    // Mismo color por acción que la pantalla de bitácora.
    $tonoAccion = ['creado' => 'success', 'actualizado' => 'info', 'eliminado' => 'danger'];
@endphp

<div class="ag-dash__stack">
    @isset($secciones['bitacora_reciente'])
        <section>
            <x-molecules.section-head :title="__('seguridad.dashboard.seccion_bitacora_reciente')">
                <x-slot:actions>
                    <a class="ag-dash__link" href="{{ route('panel.bitacora.index') }}">{{ __('seguridad.dashboard.bitacora_reciente_ver') }}</a>
                </x-slot:actions>
            </x-molecules.section-head>
            <div class="ag-card">
                <div class="ag-table-scroll">
                    <div class="ag-table ag-table--bitacora" role="table">
                        <div class="ag-table__head" role="row">
                            <span role="columnheader">{{ __('seguridad.bitacora.columna_instante') }}</span>
                            <span role="columnheader">{{ __('seguridad.bitacora.columna_usuario') }}</span>
                            <span role="columnheader">{{ __('seguridad.bitacora.columna_entidad') }}</span>
                            <span role="columnheader">{{ __('seguridad.bitacora.columna_accion') }}</span>
                        </div>
                        @foreach ($secciones['bitacora_reciente'] as $fila)
                            <div class="ag-table__row" role="row">
                                <span class="ag-table__strong" role="cell">{{ $fila->instante->format('d/m/Y H:i') }}</span>
                                <span role="cell" data-label="{{ __('seguridad.bitacora.columna_usuario') }}">{{ $fila->actorNombre ?? __('seguridad.bitacora.actor_sistema') }}</span>
                                <span role="cell" data-label="{{ __('seguridad.bitacora.columna_entidad') }}">
                                    {{ $fila->tablaLegible }} <span class="ag-dash__mono-note">#{{ $fila->registroId }}</span>
                                </span>
                                <span role="cell" class="ag-table__estado" data-label="{{ __('seguridad.bitacora.columna_accion') }}">
                                    <span class="ag-table__dot ag-table__dot--{{ $tonoAccion[$fila->accion->value] ?? 'neutral' }}" aria-hidden="true"></span>{{ __('seguridad.bitacora.acciones.'.$fila->accion->value) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @endisset

    @if (isset($secciones['acceso_configuracion']) || isset($secciones['acceso_organizacion']))
        <section>
            <x-molecules.section-head :title="__('seguridad.dashboard.seccion_accesos')" />
            <div class="ag-dash__par">
                @isset($secciones['acceso_configuracion'])
                    <div class="ag-card">
                        <x-molecules.link-row
                            :href="route('panel.configuracion.index')"
                            icon="tune"
                            :title="__('seguridad.dashboard.acceso_configuracion_titulo')"
                            :meta="__('seguridad.dashboard.acceso_configuracion_detalle')"
                        />
                    </div>
                @endisset
                @isset($secciones['acceso_organizacion'])
                    <div class="ag-card">
                        <x-molecules.link-row
                            :href="route('panel.organizacion.index')"
                            icon="apartment"
                            :title="__('seguridad.dashboard.acceso_organizacion_titulo')"
                            :meta="__('seguridad.dashboard.acceso_organizacion_detalle')"
                        />
                    </div>
                @endisset
            </div>
        </section>
    @endif
</div>
