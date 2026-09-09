{{--
    Page: seleccionar-rol (GET /panel/seleccionar-rol, panel.rol-activo.selector)
    Quinta vuelta — maqueta 5c: layout partido del login (foto 55% / panel
    45%, templates/auth-layout con copy fijo propio), chip del usuario +
    "Cerrar sesión" arriba, tarjetas de rol seleccionables
    (molecules/role-card: nombre legible, ícono, descripción, chips de
    permisos, badge "ÚLTIMO USADO"), checkbox "Entrar siempre con este rol"
    y botón pill con el nombre del rol elegido.

    Accesibilidad (consigna 5c): `role="radiogroup"` + navegación por
    flechas y Enter — resources/js/organisms/role-selection.js (que también
    postea a POST /panel/rol-activo con `recordar`).

    Esta pantalla NO se muestra con un solo rol, y con rol preferido se
    saltea salvo `?cambiar=1` — esa lógica vive en
    RolActivoController::create(), no acá.

    Datos esperados (ver RolActivoController::create()):
    - roles (list<array{id, name, nombre, descripcion, permisos, icono}>):
      ya presentados por PresentadorRol. Puede llegar vacía.
    - preseleccionId (int|null), ultimoRolId (int|null),
      recordarInicial (bool), usuarioNombre / usuarioUsername (string),
      tema ("light"|"dark"), accionActualizar (URL POST),
      urlDashboard (URL destino tras elegir).
--}}
{{-- `transicion-de-vista`: mitad ENTRANTE del salto login → selección de rol.
     Es la única pantalla del panel que declara `@view-transition` (la saliente
     lo hace en su propio <head>, pages/login.blade.php) — el resto del panel
     navega sin transición a propósito, ver resources/css/transicion-vista.css. --}}
<x-templates.panel-shell :title="__('seguridad.rol.seleccion_titulo')" :tema="$tema" :transicion-de-vista="true">
    <x-templates.auth-layout
        :headline="__('seguridad.rol.foto_headline')"
        :subheadline="__('seguridad.rol.foto_subheadline')"
    >
        <x-slot:headerEnd>
            <button type="button" class="ag-role-select__logout" data-ag-logout>
                <x-atoms.icon name="logout" size="sm" />
                {{ __('ui.topbar.logout') }}
            </button>
        </x-slot:headerEnd>

        <section
            class="ag-role-select"
            data-ag-role-select
            data-accion="{{ $accionActualizar }}"
            data-url-dashboard="{{ $urlDashboard }}"
        >
            @php
                $iniciales = collect(preg_split('/\s+/', trim((string) $usuarioNombre)))
                    ->filter()
                    ->map(fn ($palabra) => mb_strtoupper(mb_substr($palabra, 0, 1)))
                    ->take(2)
                    ->implode('');
            @endphp

            <div class="ag-role-select__user">
                <span class="ag-role-select__avatar" aria-hidden="true">{{ $iniciales ?: '?' }}</span>
                <span class="ag-role-select__user-text">{{ $usuarioNombre }} · {{ $usuarioUsername }}</span>
            </div>

            <div class="ag-role-select__heading">
                <h1 class="ag-role-select__title">{{ __('seguridad.rol.seleccion_titulo') }}</h1>
                <p class="ag-role-select__subtitle">{{ __('seguridad.rol.seleccion_subtitulo') }}</p>
            </div>

            @if (count($roles) === 0)
                <p class="ag-role-select__empty">{{ __('seguridad.rol.seleccion_vacia') }}</p>
            @else
                <div
                    class="ag-role-select__group"
                    role="radiogroup"
                    aria-label="{{ __('seguridad.rol.seleccion_grupo_aria') }}"
                    data-ag-role-group
                >
                    @foreach ($roles as $rol)
                        <x-molecules.role-card
                            :id="$rol['id']"
                            :nombre="$rol['nombre']"
                            :descripcion="$rol['descripcion']"
                            :permisos="$rol['permisos']"
                            :icon="$rol['icono']"
                            :selected="$preseleccionId !== null && (int) $rol['id'] === (int) $preseleccionId"
                            :ultimo-usado="$ultimoRolId !== null && (int) $rol['id'] === (int) $ultimoRolId"
                        />
                    @endforeach
                </div>

                <x-atoms.checkbox
                    name="recordar"
                    label="{{ __('seguridad.rol.recordar') }}"
                    :checked="$recordarInicial"
                    data-ag-role-recordar
                />

                <p
                    class="ag-role-select__error"
                    data-ag-role-error
                    data-mensaje-error="{{ __('seguridad.rol.error_actualizar') }}"
                    data-mensaje-red="{{ __('seguridad.rol.error_red') }}"
                    hidden
                ></p>

                @php
                    $nombreInicial = collect($roles)->firstWhere('id', $preseleccionId)['nombre'] ?? collect($roles)->first()['nombre'];
                @endphp
                <x-atoms.button
                    type="button"
                    variant="primary"
                    :block="true"
                    icon="arrow_forward"
                    icon-position="end"
                    data-ag-role-continuar
                >
                    <span data-ag-role-continuar-label>{{ __('seguridad.rol.seleccion_boton_continuar', ['rol' => $nombreInicial]) }}</span>
                </x-atoms.button>

                {{-- Plantilla del label del botón para el JS (":rol" se
                     sustituye por el nombre del rol elegido). --}}
                <template data-ag-role-boton-template>{{ __('seguridad.rol.seleccion_boton_continuar', ['rol' => '__ROL__']) }}</template>
            @endif
        </section>
    </x-templates.auth-layout>
</x-templates.panel-shell>
