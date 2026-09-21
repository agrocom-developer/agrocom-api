{{--
    Partial: formulario de cliente, compartido por create.blade.php y
    edit.blade.php (HU-22, tarea 33; actualizado ADR 0018, y tarea "resumen
    de cliente") — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Las dos pantallas arman el MISMO
    formulario; lo único que cambia es contra qué URL/método postea y los
    valores iniciales — evita que las dos plantillas diverjan con el tiempo,
    que es justo el riesgo de un molde que van a copiar HU-23 a HU-27.

    Espera:
    - $cliente (Cliente|null): null en alta; el modelo, con `contactos`,
      `contratos` y `propiedades` ya cargadas, en edición.
    - $tiposContacto (list<TipoContactoCliente>): opciones del select de
      tipo de contacto (ver ClientesController) — la vista no conoce el
      enum de dominio.
    - $tiposPersona (list<TipoPersonaCliente>): opciones del select de tipo
      de persona (física/jurídica, ADR 0018) — la vista no conoce el enum
      de dominio.
    - $logoArchivo (array{nombre: string, peso: string, url: string}|null):
      resuelto por ClientesController::logoArchivo() (HU-75, tarea 91) —
      null en alta o si el cliente no tiene logo guardado. `remove-name`
      del `file-field` solo se pasa en edición: en alta no hay logo previo
      que quitar.
    - $resumenRelacionado (list<array{...}>|null): solo en edición, ver
      ClientesController::resumenRelacionado(). `null`/ausente en alta.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición, para que el usuario no pierda lo
    que ya había cargado.

    "Datos del cliente" reordenado (tarea "resumen de cliente", pedido
    directo): el logo (`size="xl"`, `ag-clientes-form__campo-logo` con
    `grid-row: span 2`) ocupa la columna izquierda a lo alto de DOS filas del
    grid de dos columnas, con un preview real más grande (no espacio vacío);
    la columna derecha en ese mismo tramo apila "Tipo de persona" y, debajo,
    "Nombre comercial" (solo jurídica — con persona física esa celda se
    RESERVA vacía vía `ag-clientes-form__campo-reservado`, `visibility:
    hidden`, no `hidden`/`display:none`, para que "Razón social" no la ocupe
    en su lugar). Debajo de ese bloque, razón social + NIT en su propia fila,
    y ubicación de oficina cierra a ancho completo.

    Aside pegajoso del arquetipo (tarea "resumen de cliente", reemplaza la
    decisión anterior documentada en runs/33.md de omitirlo): SOLO en
    edición — un cliente recién creado no puede tener contratos, propiedades
    ni órdenes de aplicación todavía. Por cada categoría, `summary-card` si
    ya hay datos o `empty-state` compacto con acceso directo a "Nuevo
    contrato"/"Nueva propiedad" (con `cliente_id` precargado) o "Nueva orden
    de aplicación" (sin precarga: la orden se filtra por contrato, no por
    cliente, ver `OrdenesController::create()`) si no hay nada — nunca las
    dos cosas a la vez. Es el primer tramo del flujo cliente →
    contrato/propiedad → lote. "Campañas" salió de este aside el 15/9/2026
    (ADR 0015): dejó de ser del cliente, ver `ClientesController::resumenRelacionado()`.
--}}
@php
    $esEdicion = $cliente !== null;
    $accion = $esEdicion ? route('panel.clientes.update', $cliente) : route('panel.clientes.store');
    $razonSocial = old('razon_social', $cliente?->razon_social ?? '');
    $nombreComercial = old('nombre_comercial', $cliente?->nombre_comercial ?? '');
    $nit = old('nit', $cliente?->nit ?? '');
    $ubicacionOficina = old('ubicacion_oficina', $cliente?->ubicacion_oficina ?? '');
    $tipoPersonaValor = old('tipo_persona', $cliente?->tipo_persona?->value ?? '');
    $contactosPorDefecto = $esEdicion
        ? $cliente->contactos->map(fn ($contacto) => [
            'id' => $contacto->id,
            'tipo' => $contacto->tipo->value,
            'tipo_otro' => $contacto->tipo_otro,
            'nombre' => $contacto->nombre,
            'telefono' => $contacto->telefono,
            'email' => $contacto->email,
            'observaciones' => $contacto->observaciones,
        ])->all()
        : [[]];
    $contactosIniciales = old('contactos', $contactosPorDefecto);
    $tiposPersonaOptions = collect($tiposPersona)->mapWithKeys(fn ($tipo) => [
        $tipo->value => __('comercial.clientes.tipo_persona_opcion.'.$tipo->value)
    ]);
@endphp

<form method="POST" action="{{ $accion }}" enctype="multipart/form-data" class="ag-clientes-form" novalidate data-ag-clientes-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif
    {{-- Alta rápida desde otro formulario (tarea "contratos-lotes", 16/9/2026):
         solo hace falta reenviarlo en el alta — en edición ya llega vía
         sesión (`ClientesController::edit()`), no como campo del form. --}}
    @if (! $esEdicion && ! empty($volverA))
        <input type="hidden" name="volver_a" value="{{ $volverA }}">
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('comercial.clientes.titulo_editar') : __('comercial.clientes.titulo_crear')"
        :subtitle="__('comercial.clientes.subtitulo_form')"
    >
        <x-slot:actions>
            <x-molecules.boton-volver
                :href="route('panel.clientes.index')"
                :label="__('comercial.clientes.volver')"
                :retorno="$esEdicion ? ['cliente_id' => $cliente->id] : []"
            />
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    <x-molecules.form-layout>
            <x-molecules.form-section
                :title="__('comercial.clientes.seccion_datos')"
                :count="__('comercial.clientes.campos_contador', ['cantidad' => 6])"
            >
                {{-- `ag-clientes-form__campo-logo` (grid-row: span 2, ver
                     clientes.css): el preview "xl" ocupa las dos filas de
                     tipo de persona + nombre comercial con un preview real
                     más grande (no espacio vacío) — dando la sensación de
                     dos columnas de igual alto: una con el logo solo, la
                     otra con dos campos apilados. --}}
                <x-molecules.file-field
                    class="ag-clientes-form__campo-logo"
                    name="logo"
                    size="xl"
                    accept=".png,.svg,.jpg,.jpeg,.webp,.gif"
                    :remove-name="$esEdicion ? 'logo_eliminar' : null"
                    :label="__('comercial.clientes.campo_logo')"
                    :file-name="$logoArchivo['nombre'] ?? null"
                    :file-size="$logoArchivo['peso'] ?? null"
                    :preview-url="$logoArchivo['url'] ?? null"
                    :help="__('comercial.clientes.campo_logo_ayuda')"
                    :replace-label="__('comercial.clientes.campo_logo_reemplazar')"
                    :remove-label="$esEdicion && $logoArchivo ? __('comercial.clientes.campo_logo_quitar') : null"
                    :error="$errors->first('logo')"
                >
                    {{-- Placeholder mientras el cliente no tiene logo cargado
                         (pedido directo, tarea "resumen de cliente"):
                         public/images/logo-placeholder.png en vez del ícono
                         genérico que usa Organización — mismo criterio de
                         asset estático versionado que public/logo.png
                         (atoms/logo), nunca por el disco `public` de Storage
                         (eso es solo para el logo YA subido). --}}
                    <img src="{{ asset('images/logo-placeholder.png') }}" alt="" class="ag-file-field__preview-img">
                </x-molecules.file-field>

                <x-atoms.select
                    name="tipo_persona"
                    id="tipo_persona"
                    :label="__('comercial.clientes.campo_tipo_persona')"
                    :options="$tiposPersonaOptions"
                    :value="$tipoPersonaValor"
                    :placeholder="__('comercial.clientes.campo_tipo_persona_placeholder')"
                    required
                    :error="$errors->first('tipo_persona')"
                />

                {{-- Apilado bajo "Tipo de persona" A PROPÓSITO (misma columna
                     derecha, ver comentario del logo arriba) — por eso NO es
                     `--field--full`. Solo tiene sentido para persona
                     jurídica, sincronizado por resources/js/pages/clientes-form.js
                     contra el <select> de arriba.

                     `visibility: hidden` (clase `ag-clientes-form__campo-reservado`),
                     NO el atributo `hidden` (pedido directo, 15/9/2026): con
                     `hidden` (=`display:none`) el campo sale del grid y
                     "Razón social" fluye a este lugar en su reemplazo,
                     quedando huérfana al lado del logo y "NIT" solo debajo
                     sin pareja — acá la celda se RESERVA vacía en vez de
                     cederla, y "Razón social"/"NIT" bajan juntas a su propia
                     fila. Estado inicial calculado acá mismo (SSR) para que
                     no haya parpadeo si JS tarda en cargar. --}}
                <div
                    data-ag-nombre-comercial
                    class="{{ $tipoPersonaValor !== 'juridica' ? 'ag-clientes-form__campo-reservado' : '' }}"
                >
                    <x-atoms.input
                        type="text"
                        name="nombre_comercial"
                        :label="__('comercial.clientes.campo_nombre_comercial')"
                        :value="$nombreComercial"
                        :help="__('comercial.clientes.campo_nombre_comercial_ayuda')"
                        :error="$errors->first('nombre_comercial')"
                    />
                </div>

                <x-atoms.input
                    type="text"
                    name="razon_social"
                    :label="__('comercial.clientes.campo_razon_social')"
                    :value="$razonSocial"
                    required
                    :error="$errors->first('razon_social')"
                />

                <x-atoms.input
                    type="text"
                    name="nit"
                    :label="__('comercial.clientes.campo_nit')"
                    :value="$nit"
                    :help="__('comercial.clientes.campo_nit_ayuda')"
                    :error="$errors->first('nit')"
                />

                <x-atoms.input
                    class="ag-form-section__field--full"
                    type="text"
                    name="ubicacion_oficina"
                    :label="__('comercial.clientes.campo_ubicacion_oficina')"
                    :value="$ubicacionOficina"
                    :help="__('comercial.clientes.campo_ubicacion_oficina_ayuda')"
                    :error="$errors->first('ubicacion_oficina')"
                />
            </x-molecules.form-section>

            <x-molecules.form-section
                :title="__('comercial.clientes.seccion_contactos')"
                :count="__('comercial.clientes.campos_contador', ['cantidad' => 6])"
            >
                <div class="ag-form-section__field--full ag-clientes-form__contactos" data-ag-contactos>
                    @if ($errors->has('contactos'))
                        <p class="ag-input__error" role="alert">{{ $errors->first('contactos') }}</p>
                    @endif

                    <div data-ag-contactos-lista>
                        @foreach ($contactosIniciales as $indice => $contacto)
                            @include('comercial::pages.clientes._contacto-fila', ['indice' => $indice, 'contacto' => $contacto])
                        @endforeach
                    </div>

                    <x-atoms.button type="button" variant="outline" icon="add" class="ag-clientes-form__contactos-agregar" data-ag-contactos-agregar>
                        {{ __('comercial.clientes.contacto_agregar') }}
                    </x-atoms.button>

                    {{-- Plantilla clonable (JS vanilla, resources/js/pages/clientes-form.js):
                         el índice literal se reemplaza por el próximo número al clonar. Un
                         <template> nunca se renderiza ni se envía con el form. --}}
                    <template data-ag-contacto-template>
                        @include('comercial::pages.clientes._contacto-fila', ['indice' => '__INDICE__', 'contacto' => []])
                    </template>
                </div>
            </x-molecules.form-section>

            <x-organisms.form-actions-bar :status="__('comercial.clientes.estado_form')">
                <x-slot:actions>
                    @if ($esEdicion && ! empty($volverA))
                        <x-atoms.button href="{{ $volverA }}{{ str_contains($volverA, '?') ? '&' : '?' }}cliente_id={{ $cliente->id }}" variant="outline" icon="arrow_back">
                            {{ __('comercial.clientes.volver_a_formulario_origen') }}
                        </x-atoms.button>
                    @endif
                    <x-molecules.boton-volver :href="route('panel.clientes.index')" :retorno="$esEdicion ? ['cliente_id' => $cliente->id] : []" cancelar />
                    <x-atoms.button type="submit" variant="primary">
                        {{ __('ui.action.save') }}
                    </x-atoms.button>
                </x-slot:actions>
            </x-organisms.form-actions-bar>

        @if ($esEdicion)
            <x-slot:aside>
                {{-- `mostrarAccion` viene del permiso `.crear` de cada módulo
                     (ClientesController::resumenRelacionado) — sin él, ni
                     summary-card ni empty-state ofrecen el atajo de alta. --}}
                @foreach ($resumenRelacionado ?? [] as $resumen)
                    @if ($resumen['tieneDatos'])
                        <x-molecules.summary-card :title="$resumen['titulo']" :items="$resumen['items']">
                            @if ($resumen['mostrarAccion'])
                                <x-slot:action>
                                    <x-atoms.button :href="$resumen['accion']['href']" variant="outline" icon="add" block>
                                        {{ $resumen['accion']['label'] }}
                                    </x-atoms.button>
                                </x-slot:action>
                            @endif
                        </x-molecules.summary-card>
                    @else
                        <x-molecules.empty-state
                            :icon="$resumen['icono']"
                            :title="$resumen['vacioTitulo']"
                            :detail="$resumen['vacioDetalle']"
                        >
                            @if ($resumen['mostrarAccion'])
                                <x-slot:action>
                                    <x-atoms.button :href="$resumen['accion']['href']" variant="outline" icon="add">
                                        {{ $resumen['accion']['label'] }}
                                    </x-atoms.button>
                                </x-slot:action>
                            @endif
                        </x-molecules.empty-state>
                    @endif
                @endforeach
            </x-slot:aside>
        @endif
    </x-molecules.form-layout>
</form>
