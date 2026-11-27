{{--
    Page: usuarios/show (GET /panel/usuarios/{usuario}, panel.usuarios.show)
    Ficha de una cuenta del panel — arquetipo Detalle (§6.4 de
    docs/diseno/guia_pantalla_panel.md): cabecera con el estado de acceso y las
    acciones (Volver, Editar, Bloquear o Desbloquear y, al final, Eliminar en rojo),
    KPIs, datos de acceso y personales, el rol con sus permisos y los últimos
    movimientos de la bitácora.

    Datos esperados (ver UsuariosController::show()): la cáscara de CascaraPanel, más
    - $usuario (SecUser): cuenta de tipo `panel`.
    - $rolesCuenta (list<array>): `PresentadorRol::presentar()` de cada rol asignado.
    - $tonoPorEstado (array<string, string>): estado de acceso → tono, el mismo del listado.
    - $puedeEditar, $puedeBloquear, $puedeEliminar, $puedeVerRoles, $puedeVerBitacora (bool):
      presentación; el servidor revalida cada acción.
    - $lineaDeTiempo (list<array{title, meta, tone}>): movimientos de la cuenta, del más nuevo al más viejo.

    Gateada por `seguridad.usuario.ver`, verificado server-side en el controlador.
--}}
@php
    $titulo = $usuario->name;
    $estadoValor = $usuario->state ? 'activo' : 'bloqueado';
    $estadoDestino = $usuario->state ? 'bloqueado' : 'activo';
    $zona = $zonaHoraria ?? config('app.timezone');
    $fecha = fn ($momento) => $momento?->copy()->timezone($zona)->format('d/m/Y H:i') ?? '—';
@endphp
<x-templates.panel-shell :title="$titulo" :tema="$tema">
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
        :vista-actual="$titulo"
    >
        <div class="ag-detalle">
            <x-organisms.page-header
                :title="$titulo"
                :subtitle="__('seguridad.usuarios.detalle_subtitulo', ['usuario' => $usuario->username])"
            >
                <x-slot:chip>
                    <x-atoms.badge :variant="$tonoPorEstado[$estadoValor]">{{ __('seguridad.usuarios.estado_'.$estadoValor) }}</x-atoms.badge>
                </x-slot:chip>
                <x-slot:actions>
                    <x-molecules.boton-volver :href="route('panel.usuarios.index')" :label="__('seguridad.usuarios.volver')" />
                    @if ($puedeEditar)
                        <x-atoms.button :href="route('panel.usuarios.edit', $usuario)" variant="warning-outline" icon="edit">{{ __('seguridad.usuarios.editar') }}</x-atoms.button>
                    @endif
                    @if ($puedeBloquear)
                        <x-atoms.button
                            type="button"
                            :variant="$tonoPorEstado[$estadoDestino].'-outline'"
                            :icon="$usuario->state ? 'lock' : 'lock_open'"
                            data-bs-toggle="modal"
                            data-bs-target="#usuario-bloqueo-modal"
                        >{{ __($usuario->state ? 'seguridad.usuarios.bloquear' : 'seguridad.usuarios.desbloquear') }}</x-atoms.button>
                    @endif
                    @if ($puedeEliminar)
                        <x-atoms.button
                            type="button"
                            variant="danger"
                            icon="delete"
                            data-bs-toggle="modal"
                            data-bs-target="#usuario-eliminar-modal"
                        >{{ __('seguridad.usuarios.eliminar_accion') }}</x-atoms.button>
                    @endif
                </x-slot:actions>
            </x-organisms.page-header>

            @if ($puedeBloquear)
                <form id="usuario-bloqueo-form" method="POST" action="{{ route('panel.usuarios.bloqueo', $usuario) }}" hidden>
                    @csrf
                    <input type="hidden" name="desde" value="ficha">
                </form>

                <x-molecules.confirm-modal
                    id="usuario-bloqueo-modal"
                    form-id="usuario-bloqueo-form"
                    :title="__($usuario->state ? 'seguridad.usuarios.confirmar_bloquear_titulo' : 'seguridad.usuarios.confirmar_desbloquear_titulo')"
                    :message="__($usuario->state ? 'seguridad.usuarios.confirmar_bloquear' : 'seguridad.usuarios.confirmar_desbloquear')"
                    :confirm-label="__($usuario->state ? 'seguridad.usuarios.bloquear' : 'seguridad.usuarios.desbloquear')"
                    :tone="$tonoPorEstado[$estadoDestino]"
                    :modal-icon="$usuario->state ? 'lock' : 'lock_open'"
                >
                    @include('seguridad::pages.usuarios._estado-transicion', ['desde' => $estadoValor, 'hacia' => $estadoDestino])
                </x-molecules.confirm-modal>
            @endif

            @if ($puedeEliminar)
                <form id="usuario-eliminar-form" method="POST" action="{{ route('panel.usuarios.destroy', $usuario) }}" hidden>
                    @csrf
                    @method('DELETE')
                </form>

                <x-molecules.confirm-modal
                    id="usuario-eliminar-modal"
                    form-id="usuario-eliminar-form"
                    :title="__('seguridad.usuarios.confirmar_eliminar_titulo')"
                    :message="__('seguridad.usuarios.confirmar_baja')"
                    :confirm-label="__('seguridad.usuarios.eliminar_accion')"
                />
            @endif

            @if ($errors->any())
                <x-molecules.alert-strip variant="danger" icon="error">
                    {{ $errors->first() }}
                </x-molecules.alert-strip>
            @endif

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <div class="ag-detalle__kpis">
                <x-molecules.stat-card
                    :label="__('seguridad.usuarios.kpi_estado')"
                    icon="lock_person"
                    :value="__('seguridad.usuarios.estado_'.$estadoValor)"
                    :state="$usuario->state ? 'success' : null"
                />
                <x-molecules.stat-card
                    :label="__('seguridad.usuarios.kpi_rol')"
                    icon="shield_person"
                    :value="$rolesCuenta === [] ? '—' : implode(', ', array_column($rolesCuenta, 'nombre'))"
                    state="info"
                />
                <x-molecules.stat-card
                    :label="__('seguridad.usuarios.kpi_alta')"
                    icon="calendar_month"
                    :value="$usuario->created_at?->copy()->timezone($zona)->format('d/m/Y') ?? '—'"
                    state="distintivo-1"
                />
            </div>

            <x-molecules.form-layout>
                <x-molecules.form-section accent="primary-2" :title="__('seguridad.usuarios.seccion_acceso')" :count="__('seguridad.usuarios.campos_contador', ['cantidad' => 3])">
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('seguridad.usuarios.campo_username') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $usuario->username }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('seguridad.usuarios.campo_email') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $usuario->email ?? '—' }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('seguridad.usuarios.campo_estado_acceso') }}</p>
                        <p class="ag-detalle__campo-valor">
                            <x-atoms.badge :variant="$tonoPorEstado[$estadoValor]">{{ __('seguridad.usuarios.estado_'.$estadoValor) }}</x-atoms.badge>
                        </p>
                    </div>
                </x-molecules.form-section>

                <x-molecules.form-section accent="info" :title="__('seguridad.usuarios.seccion_personal')" :count="__('seguridad.usuarios.campos_contador', ['cantidad' => 3])">
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('seguridad.usuarios.campo_name') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $usuario->name }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('seguridad.usuarios.campo_telefono') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $usuario->telefono ?? '—' }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('seguridad.usuarios.campo_actualizado') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $fecha($usuario->updated_at) }}</p>
                    </div>
                </x-molecules.form-section>

                <x-molecules.form-section accent="distintivo-1" :title="__('seguridad.usuarios.seccion_roles')" :count="(string) count($rolesCuenta)">
                    <div class="ag-form-section__field--full">
                        @if ($rolesCuenta === [])
                            <x-molecules.empty-state icon="shield_person" :title="__('seguridad.usuarios.aside_roles_vacio_titulo')" :detail="__('seguridad.usuarios.aside_roles_vacio_detalle')" />
                        @else
                            @foreach ($rolesCuenta as $rol)
                                <div class="ag-detalle__campo">
                                    <p class="ag-detalle__campo-label">
                                        @if ($puedeVerRoles)
                                            <a href="{{ route('panel.roles.show', $rol['id']) }}" class="ag-enlace-insignia" title="{{ __('seguridad.usuarios.ver_rol') }}">{{ $rol['nombre'] }}</a>
                                        @else
                                            {{ $rol['nombre'] }}
                                        @endif
                                    </p>
                                    @if ($rol['descripcion'])
                                        <p class="ag-detalle__campo-valor">{{ $rol['descripcion'] }}</p>
                                    @endif
                                    @if ($rol['permisos'] !== [])
                                        <p class="ag-detalle__campo-valor">
                                            @foreach ($rol['permisos'] as $permiso)
                                                <x-atoms.badge variant="neutral">{{ $permiso }}</x-atoms.badge>
                                            @endforeach
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                            @if ($puedeVerRoles)
                                <x-atoms.button :href="route('panel.roles.show', $rolesCuenta[0]['id'])" variant="outline" icon="shield_person">{{ __('seguridad.usuarios.ver_rol') }}</x-atoms.button>
                            @endif
                        @endif
                    </div>
                </x-molecules.form-section>

                @if ($puedeVerBitacora)
                    <x-molecules.form-section accent="success" :title="__('seguridad.usuarios.seccion_actividad')" :count="(string) count($lineaDeTiempo)">
                        <div class="ag-form-section__field--full">
                            @if ($lineaDeTiempo === [])
                                <x-molecules.empty-state icon="history" :title="__('seguridad.usuarios.aside_bitacora_vacio_titulo')" :detail="__('seguridad.usuarios.aside_bitacora_vacio_detalle')" />
                            @else
                                <x-molecules.timeline :items="$lineaDeTiempo" />
                                <x-atoms.button :href="route('panel.bitacora.index', ['tabla' => 'sec_user', 'registro_id' => $usuario->id])" variant="outline" icon="history">{{ __('seguridad.usuarios.aside_bitacora_accion') }}</x-atoms.button>
                            @endif
                        </div>
                    </x-molecules.form-section>
                @endif
            </x-molecules.form-layout>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
