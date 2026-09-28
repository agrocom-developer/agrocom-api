{{--
    Parcial: modal «Cambiar vista» del dashboard — acceso directo a «ver como
    otro usuario» (tarea 140) desde el chip de rol activo del encabezado, sin
    pasar por Seguridad → Usuarios. Solo se incluye cuando `$puedeVerComo` es
    verdadero (ver dashboard.blade.php).

    No abre la vista por sí mismo: es un SELECTOR. Al elegir una cuenta,
    cierra este modal y abre el mismo par form+`molecules/confirm-modal` que
    ya usa `usuarios/index.blade.php` por fila (mismo permiso, mismo POST a
    `panel.usuarios.ver-como`, mismo caso de uso `IniciarVistaComo` sin
    tocar) — este parcial arma esos forms/modales una vez por cuenta
    candidata, a nivel de `.ag-dash` (nunca anidados dentro de este modal:
    Bootstrap encadena el cierre de uno con la apertura del otro por JS, ver
    resources/js/organisms/cambiar-vista-modal.js).

    Espera: $candidatos, la forma que devuelve
    `ListarCandidatosVerComo::ejecutar()`:
    - grupos: list<{clave, nombre, idRol, usuarioIds}> — cuentas internas
      agrupadas por rol vivo (una cuenta con varios roles aparece en más de
      un grupo, mismo criterio que "Usuarios activos por rol" del propio
      dashboard). `idRol` viaja a cada fila (data-ag-cambiar-vista-rol-id)
      para que, si la cuenta tiene más de un rol, el modal de confirmación
      ya sepa CUÁL sin volver a preguntarlo — el grupo clickeado ya lo dice
      (punto 4 del pedido del dueño, 23/9/2026).
    - portalIds: list<int> — cuentas de portal, en su propio grupo.
    - usuarios: array<id, {nombre, username, esPortal, roles, detalle}> — un
      registro por cuenta candidata (sin duplicar entre grupos). `detalle` es
      el nombre del cliente/contrato para una cuenta de portal (`null` en una
      interna).

    El buscador filtra en el cliente (data-ag-cambiar-vista-texto en cada
    opción, ya en minúsculas): no hay ida al servidor por tipear, el volumen
    de cuentas no lo justifica.

    Ancho: `.ag-cambiar-vista__dialog` (cambiar-vista-modal.css) — más ancho
    que el `modal-dialog` default en cualquier pantalla, pero el ancho de
    `modal-lg` recién desde `xxl` (1400px): pedido del dueño 23/9/2026, la
    fila con avatar+nombre+usuario necesita más aire que el modal default sin
    llegar a dejar huecos vacíos en pantallas medianas.
--}}
<div
    class="modal fade"
    id="ag-cambiar-vista-modal"
    tabindex="-1"
    aria-hidden="true"
    aria-labelledby="ag-cambiar-vista-modal-titulo"
    data-ag-cambiar-vista
>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable ag-cambiar-vista__dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title ag-page-header__title" id="ag-cambiar-vista-modal-titulo">
                        {{ __('seguridad.vista_como.selector_titulo') }}
                    </h2>
                    <p class="ag-cambiar-vista__subtitulo">{{ __('seguridad.vista_como.selector_subtitulo') }}</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.action.close') }}"></button>
            </div>

            <div class="modal-body">
                <x-atoms.input
                    type="search"
                    id="ag-cambiar-vista-buscador"
                    :label="__('seguridad.vista_como.selector_buscador_placeholder')"
                    :placeholder="__('seguridad.vista_como.selector_buscador_placeholder')"
                    icon="search"
                    data-ag-cambiar-vista-buscador
                />

                <div class="ag-cambiar-vista__lista" data-ag-cambiar-vista-lista>
                    @foreach ($candidatos['grupos'] as $grupo)
                        <section class="ag-cambiar-vista__grupo" data-ag-cambiar-vista-grupo>
                            <x-molecules.section-head :title="$grupo['nombre']" :count="count($grupo['usuarioIds'])" />
                            <ul class="ag-cambiar-vista__opciones">
                                @foreach ($grupo['usuarioIds'] as $idUsuario)
                                    @include('seguridad::pages.dashboard._modal-cambiar-vista-opcion', [
                                        'idUsuario' => $idUsuario,
                                        'candidato' => $candidatos['usuarios'][$idUsuario],
                                        'rolId' => $grupo['idRol'],
                                        'claveRol' => $grupo['clave'],
                                    ])
                                @endforeach
                            </ul>
                        </section>
                    @endforeach

                    @if ($candidatos['portalIds'] !== [])
                        <section class="ag-cambiar-vista__grupo" data-ag-cambiar-vista-grupo>
                            <x-molecules.section-head
                                :title="__('seguridad.vista_como.selector_grupo_portal')"
                                :count="count($candidatos['portalIds'])"
                                accent="info"
                            />
                            <ul class="ag-cambiar-vista__opciones">
                                @foreach ($candidatos['portalIds'] as $idUsuario)
                                    @include('seguridad::pages.dashboard._modal-cambiar-vista-opcion', [
                                        'idUsuario' => $idUsuario,
                                        'candidato' => $candidatos['usuarios'][$idUsuario],
                                        'rolId' => null,
                                        'claveRol' => 'portal',
                                    ])
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    @if ($candidatos['grupos'] === [] && $candidatos['portalIds'] === [])
                        <x-molecules.empty-state
                            icon="person_search"
                            :title="__('seguridad.vista_como.selector_sin_candidatos_titulo')"
                            :detail="__('seguridad.vista_como.selector_sin_candidatos_detalle')"
                        />
                    @else
                        <div data-ag-cambiar-vista-sin-resultados hidden>
                            <x-molecules.empty-state
                                icon="search_off"
                                :title="__('seguridad.vista_como.selector_sin_resultados_titulo')"
                                :detail="__('seguridad.vista_como.selector_sin_resultados_detalle')"
                            />
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Un form + confirm-modal por cuenta candidata, una sola vez (no por
     grupo): mismo id determinístico que espera el JS de encadenamiento
     (`usuario-ver-como-modal-dash-{id}`). El form es `display:none`
     (cambiar-vista-modal.css) — nunca tiene nada visible, solo el token
     CSRF — y con uno por cuenta candidata (9 en el compose de prueba) como
     hijo directo de `.ag-dash` (flex en columna con `gap`), dejarlos en
     flujo normal acumulaba un hueco vacío entre el encabezado y las
     pestañas del dashboard (bug reportado 23/9/2026): el `gap` se aplica
     entre cualquier hijo flex visible, aunque mida 0 de alto. El botón
     sigue enviándolo igual (atributo HTML `form`), funciona oculto. --}}
@foreach ($candidatos['usuarios'] as $idUsuario => $candidato)
    @php
        $formIdDash = "dash-ver-como-{$idUsuario}";
        $modalIdDash = "usuario-ver-como-modal-dash-{$idUsuario}";
    @endphp

    <form id="{{ $formIdDash }}" method="POST" action="{{ route('panel.usuarios.ver-como', $idUsuario) }}" class="ag-cambiar-vista__form-envio">
        @csrf
    </form>

    <x-molecules.confirm-modal
        :id="$modalIdDash"
        :form-id="$formIdDash"
        :title="__('seguridad.vista_como.modal_titulo', ['nombre' => $candidato['nombre']])"
        :message="__($candidato['esPortal'] ? 'seguridad.vista_como.modal_mensaje_portal' : 'seguridad.vista_como.modal_mensaje_interno')"
        :confirm-label="__('seguridad.vista_como.modal_confirmar')"
        tone="info"
        modal-icon="visibility"
    >
        {{-- El selector ya agrupó por rol (punto 4 del pedido del dueño,
             23/9/2026): clickear la fila bajo "Jefe de campo" ya dice CUÁL
             rol, no hace falta volver a preguntarlo acá. El JS de
             encadenamiento (cambiar-vista-modal.js) escribe el valor antes
             de abrir este modal, leyendo `data-ag-cambiar-vista-rol-id` de
             la fila que se clickeó. --}}
        @if (count($candidato['roles']) > 1)
            <input type="hidden" name="rol_id" form="{{ $formIdDash }}" data-ag-cambiar-vista-rol-input>
        @endif
    </x-molecules.confirm-modal>
@endforeach
