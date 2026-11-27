{{--
    Page: roles/show (GET /panel/roles/{rol}, panel.roles.show) — «Ver rol»
    Ficha de un rol del panel — arquetipo Detalle (§6.4 de docs/diseno/guia_pantalla_panel.md),
    el mismo molde que usuarios/show.blade.php: cabecera con el estado y las acciones
    (Volver, Editar, Permisos y, al final, Eliminar en rojo), KPIs, datos del rol, las
    cuentas que lo tienen (cada una enlaza a su ficha), el resumen de permisos agrupado por
    módulo —con el color de cada módulo, el mismo de la matriz de permisos— y los últimos
    movimientos de la bitácora.

    Datos esperados (ver RolesController::show()): la cáscara de CascaraPanel, más
    - $rol (SecRole), $nombre (string legible), $esRolActivo, $esDelSistema (bool).
    - $usuariosDelRol (list<array{id, nombre, usuario, activo}>): cuentas vivas con el rol.
    - $resumenPermisos (list<array>): `ResumenDePermisosDeRol::de()`, por módulo con su tono.
    - $permisosActivos, $permisosTotal (int).
    - $puedeEditar, $puedeEliminar, $puedeAsignar, $puedeVerUsuarios, $puedeVerBitacora (bool):
      presentación; el servidor revalida cada acción.
    - $lineaDeTiempo (list<array{title, meta, tone}>): movimientos del rol, del más nuevo al más viejo.

    Eliminar no se ofrece para el rol del sistema (administrador, id 1). Gateada por
    `seguridad.rol.ver`, verificado server-side. Colores por token: clases `ag-modulo-tono--*`
    de components/modulo-tono.css.
--}}
@php
    $titulo = $nombre;
    $estadoValor = $rol->state ? 'activo' : 'inactivo';
    $zona = $zonaHoraria ?? config('app.timezone');
    $fecha = fn ($momento) => $momento?->copy()->timezone($zona)->format('d/m/Y H:i') ?? '—';
    $puedeEliminarEste = $puedeEliminar && ! $esDelSistema;
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
                :subtitle="$rol->description"
            >
                <x-slot:chip>
                    <x-atoms.badge :variant="$rol->state ? 'success' : 'neutral'">{{ __('seguridad.roles.estado_'.$estadoValor) }}</x-atoms.badge>
                    @if ($esDelSistema)
                        <x-atoms.badge variant="info" icon="lock">{{ __('seguridad.roles.badge_sistema') }}</x-atoms.badge>
                    @endif
                </x-slot:chip>
                <x-slot:actions>
                    <x-molecules.boton-volver :href="route('panel.roles.index')" :label="__('seguridad.roles.volver')" />
                    @if ($puedeEditar)
                        <x-atoms.button :href="route('panel.roles.edit', $rol)" variant="warning-outline" icon="edit">{{ __('seguridad.roles.editar') }}</x-atoms.button>
                    @endif
                    @if ($puedeAsignar)
                        <x-atoms.button :href="route('panel.roles.permisos.edit', $rol)" variant="distintivo-1-outline" icon="key">{{ __('seguridad.roles.permisos') }}</x-atoms.button>
                    @endif
                    @if ($puedeEliminarEste)
                        <x-atoms.button
                            type="button"
                            variant="danger"
                            icon="delete"
                            data-bs-toggle="modal"
                            data-bs-target="#rol-eliminar-modal"
                        >{{ __('seguridad.roles.eliminar') }}</x-atoms.button>
                    @endif
                </x-slot:actions>
            </x-organisms.page-header>

            @if ($puedeEliminarEste)
                <form id="rol-eliminar-form" method="POST" action="{{ route('panel.roles.destroy', $rol) }}" hidden>
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="desde" value="ficha">
                </form>

                <x-molecules.confirm-modal
                    id="rol-eliminar-modal"
                    form-id="rol-eliminar-form"
                    :title="__('seguridad.roles.confirmar_eliminar_titulo')"
                    :message="__('seguridad.roles.confirmar_eliminar', ['rol' => $nombre])"
                    :confirm-label="__('seguridad.roles.eliminar')"
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
                    :label="__('seguridad.roles.kpi_estado')"
                    icon="toggle_on"
                    :value="__('seguridad.roles.estado_'.$estadoValor)"
                    :state="$rol->state ? 'success' : null"
                />
                <x-molecules.stat-card
                    :label="__('seguridad.roles.kpi_usuarios')"
                    icon="group"
                    :value="(string) count($usuariosDelRol)"
                    state="info"
                />
                <x-molecules.stat-card
                    :label="__('seguridad.roles.kpi_permisos')"
                    icon="key"
                    :value="$permisosActivos.' / '.$permisosTotal"
                    state="distintivo-1"
                />
            </div>

            <x-molecules.form-layout>
                <x-molecules.form-section accent="primary-2" :title="__('seguridad.roles.seccion_identidad')" :count="__('seguridad.roles.campos_contador', ['cantidad' => 5])">
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('seguridad.roles.campo_nombre') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $nombre }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('seguridad.roles.campo_tipo') }}</p>
                        <p class="ag-detalle__campo-valor">{{ __($esDelSistema ? 'seguridad.roles.tipo_sistema' : 'seguridad.roles.tipo_personalizado') }}</p>
                    </div>
                    <div class="ag-detalle__campo ag-form-section__field--full">
                        <p class="ag-detalle__campo-label">{{ __('seguridad.roles.campo_descripcion') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $rol->description }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('seguridad.roles.campo_creado') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $fecha($rol->created_at) }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('seguridad.roles.campo_actualizado') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $fecha($rol->updated_at) }}</p>
                    </div>
                </x-molecules.form-section>

                <x-molecules.form-section accent="info" :title="__('seguridad.roles.seccion_usuarios')" :count="(string) count($usuariosDelRol)">
                    <div class="ag-form-section__field--full">
                        @if ($usuariosDelRol === [])
                            <x-molecules.empty-state icon="group" :title="__('seguridad.roles.usuarios_vacio_titulo')" :detail="__('seguridad.roles.usuarios_vacio_detalle')" />
                        @else
                            @foreach ($usuariosDelRol as $cuenta)
                                @if ($puedeVerUsuarios)
                                    <x-molecules.link-row
                                        :href="route('panel.usuarios.show', $cuenta['id'])"
                                        icon="person"
                                        :title="$cuenta['nombre']"
                                        :meta="$cuenta['usuario'].' · '.__('seguridad.usuarios.estado_'.($cuenta['activo'] ? 'activo' : 'bloqueado'))"
                                        :tone="$cuenta['activo'] ? 'info' : 'neutral'"
                                    />
                                @else
                                    <div class="ag-detalle__campo">
                                        <p class="ag-detalle__campo-label">{{ $cuenta['nombre'] }}</p>
                                        <p class="ag-detalle__campo-valor">{{ $cuenta['usuario'] }}</p>
                                    </div>
                                @endif
                            @endforeach
                        @endif
                    </div>
                </x-molecules.form-section>

                <x-molecules.form-section accent="distintivo-1" :title="__('seguridad.roles.seccion_permisos')" :count="$permisosActivos.' / '.$permisosTotal">
                    <div class="ag-form-section__field--full">
                        @if ($permisosActivos === 0)
                            <x-molecules.empty-state icon="key" :title="__('seguridad.roles.permisos_vacio_titulo')" :detail="__('seguridad.roles.permisos_vacio_detalle')" />
                        @endif

                        <div class="ag-modulos-resumen">
                            @foreach ($resumenPermisos as $modulo)
                                <section class="ag-modulo ag-modulo-tono--{{ $modulo['tono'] }}" aria-labelledby="modulo-{{ $loop->index }}">
                                    <header class="ag-modulo__cabecera">
                                        <x-atoms.icon :name="$modulo['icono']" size="sm" />
                                        <h3 class="ag-modulo__nombre" id="modulo-{{ $loop->index }}">
                                            {{ $modulo['clave'] === \App\Dominios\Seguridad\Infraestructura\Http\Presentacion\ColorDeModulo::CLAVE_SUELTOS ? __('seguridad.roles.permisos_sueltos_titulo') : __($modulo['clave']) }}
                                        </h3>
                                        <span class="ag-modulo__conteo">{{ __('seguridad.roles.modulo_conteo', ['activos' => $modulo['activos'], 'total' => $modulo['total']]) }}</span>
                                    </header>

                                    <ul class="ag-modulo__lista">
                                        @foreach ($modulo['pantallas'] as $pantalla)
                                            <li class="ag-modulo__item {{ $pantalla['activa'] ? 'is-activo' : '' }}">
                                                <x-atoms.icon :name="$pantalla['activa'] ? 'check_circle' : 'radio_button_unchecked'" size="sm" />
                                                <span>{{ __($pantalla['clave']) }}</span>
                                                @if ($pantalla['accionesTotal'] > 0)
                                                    <span class="ag-modulo__acciones">{{ __('seguridad.roles.permisos_conteo_acciones', ['activas' => $pantalla['accionesActivas'], 'total' => $pantalla['accionesTotal']]) }}</span>
                                                @endif
                                                <span class="visually-hidden">{{ __($pantalla['activa'] ? 'seguridad.roles.pantalla_activa' : 'seguridad.roles.pantalla_inactiva') }}</span>
                                            </li>
                                        @endforeach
                                        @foreach ($modulo['sueltos'] as $codigo)
                                            <li class="ag-modulo__item is-activo">
                                                <x-atoms.icon name="check_circle" size="sm" />
                                                <span>{{ $codigo }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </section>
                            @endforeach
                        </div>
                    </div>
                </x-molecules.form-section>

                <x-slot:aside>
                    @if ($puedeAsignar || $puedeVerUsuarios || $puedeVerBitacora)
                        <x-molecules.form-section accent="info" :title="__('seguridad.usuarios.seccion_relacionado')">
                            <div class="ag-form-section__field--full">
                                @if ($puedeAsignar)
                                    <x-molecules.link-row
                                        :href="route('panel.roles.permisos.edit', $rol)"
                                        icon="key"
                                        :title="__('seguridad.roles.permisos')"
                                        :meta="$permisosActivos.' / '.$permisosTotal"
                                        tone="distintivo-1"
                                    />
                                @endif
                                @if ($puedeVerUsuarios)
                                    <x-molecules.link-row
                                        :href="route('panel.usuarios.index')"
                                        icon="group"
                                        :title="__('seguridad.roles.seccion_usuarios')"
                                        :meta="(string) count($usuariosDelRol)"
                                        tone="info"
                                    />
                                @endif
                                @if ($puedeVerBitacora)
                                    <x-molecules.link-row
                                        :href="route('panel.bitacora.index', ['tabla' => 'sec_role', 'registro_id' => $rol->id])"
                                        icon="history"
                                        :title="__('seguridad.usuarios.aside_bitacora_accion')"
                                        tone="success"
                                    />
                                @endif
                            </div>
                        </x-molecules.form-section>
                    @endif

                    @if ($puedeVerBitacora)
                        <x-molecules.form-section accent="distintivo-2" :title="__('seguridad.usuarios.seccion_actividad')" :count="(string) count($lineaDeTiempo)">
                            <div class="ag-form-section__field--full">
                                @if ($lineaDeTiempo === [])
                                    <x-molecules.empty-state icon="history" :title="__('seguridad.usuarios.aside_bitacora_vacio_titulo')" :detail="__('seguridad.usuarios.aside_bitacora_vacio_detalle')" />
                                @else
                                    <x-molecules.timeline :items="$lineaDeTiempo" />
                                @endif
                            </div>
                        </x-molecules.form-section>
                    @endif
                </x-slot:aside>
            </x-molecules.form-layout>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
