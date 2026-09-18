{{--
    Partial: formulario de orden de aplicación, compartido por
    create.blade.php y edit.blade.php (HU-25, tarea 38; reforma 18/9/2026) —
    arquetipo Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md.

    Espera (reforma Entrega 1, 18/9/2026):
    - $orden (OrdenAplicacion|null): null en alta; el modelo en edición.
    - $lotesOrden (Collection<int, OrdenLote>|null, SOLO en edición): los
      lotes ya cargados de la orden.
    - $contratosDisponibles (Collection<int, string>): id => label
      enriquecido (cliente + propiedad(es) + "Contrato #id"), resuelto por
      el controlador.
    - $datosContrato (array<int, array>): indexado por contrato_id, con
      cliente, propiedades, aplicaciones_previstas, contactos (list), lotes
      (list con desnivel_label/limpieza_label ya traducidos), y
      nro_aplicacion_sugerido (SOLO en create(), null en edit()) — ver docblock
      de `OrdenesController::datosContratoParaFormulario()`.
    - $categoriasInsumoDisponibles (Collection<int, CategoriaInsumo>): para
      armar el select de categoría y el mapa tipo_insumo.
    - $contactosDisponibles (Collection<int, string>): valor heredado, NO se
      usa para "Datos del contrato" (usá `datosContrato[contratoId].contactos`
      — ya scopeado). Si terminas sin usarla, está ok.

    `estado` NUNCA es un campo de este formulario: lo fija la máquina de
    estados al crear, y lo cambia `panel.ordenes.activar` (otra pantalla).
    En edición, el formulario solo se ofrece para una orden `emitida`.

    Los 8 campos de clima/vuelo (humedad, viento, temperatura, velocidad,
    altura, ancho) YA NO están acá (se movieron a `Trabajo`, cargados por
    equipo en `AsignarEquipoOrdenRequest`).

    El aside pegajoso (§6.3.1) SÍ se usa en edición. Espera además:
    - $resumenRelacionado (list<array{...}>, SOLO en edición): asignación de
      equipos de esta orden.
--}}
@php
    $esEdicion = $orden !== null;
    $accion = $esEdicion ? route('panel.ordenes.update', $orden) : route('panel.ordenes.store');
    $valor = fn (string $campo, mixed $porDefecto = '') => old($campo, $orden?->{$campo} ?? $porDefecto);
    // `null`, no `''`: `atoms/select` solo marca el placeholder `selected`
    // cuando `$value === null` (ver su docblock). Con `''` acá, ningún
    // <option> queda `selected` en el HTML — el placeholder es `disabled`
    // y el navegador cae por defecto al PRIMER valor real de la lista, sin
    // que el usuario haya elegido nada. Antes de esta reforma pasaba
    // desapercibido (un <select> plano no delata el default); ahora
    // "Datos del contrato" pinta datos reales apenas carga la página, así
    // que ese default silencioso es mucho más peligroso — mismo criterio
    // para contrato y contacto.
    $contratoId = old('contrato_id', $orden?->contrato_id);
    $contactoId = old('emitida_por_contacto_id', $orden?->emitida_por_contacto_id);
    $nroAplicacion = old('nro_aplicacion', $orden?->nro_aplicacion ?? '');
    // Alta: hoy por defecto (el usuario la cambia si emite con fecha
    // atrasada) — edición sigue mostrando la fecha real ya guardada.
    $fechaEmision = old('fecha_emision', $orden?->fecha_emision?->toDateString() ?? now()->toDateString());
    $tipoAplicacion = old('tipo_aplicacion', $orden?->tipo_aplicacion?->value ?? \App\Dominios\Operaciones\Dominio\TipoAplicacion::Desarrollo->value);
    $cantidadEquiposNecesarios = old('cantidad_equipos_necesarios', $orden?->cantidad_equipos_necesarios ?? 1);
    $opcionesTipoAplicacion = collect(\App\Dominios\Operaciones\Dominio\TipoAplicacion::cases())
        ->mapWithKeys(fn ($caso) => [$caso->value => __('operaciones.tipo_aplicacion.'.$caso->value)]);
    $categoriaInsumoId = old('categoria_insumo_id', $orden?->categoria_insumo_id ?? '');
    $categoriasInsumoOptions = $categoriasInsumoDisponibles->mapWithKeys(fn ($categoria) => [
        $categoria->id => $categoria->nombre,
    ]);
    $mapaCategoriaInsumoTipo = $categoriasInsumoDisponibles->mapWithKeys(fn ($categoria) => [$categoria->id => $categoria->tipo_insumo->value]);
    $tipoInsumoSeleccionado = old('tipo_insumo_filtro', $mapaCategoriaInsumoTipo->get((int) $categoriaInsumoId) ?? '');
    $opcionesTipoInsumo = collect(\App\Dominios\Operaciones\Dominio\TipoInsumo::cases())
        ->mapWithKeys(fn ($caso) => [$caso->value => __('operaciones.tipo_insumo.'.$caso->value)]);

    // Opciones de nro_aplicacion (1-10): ordinales en tuteo neutro.
    $opcionesNroAplicacion = array_combine(
        range(1, 10),
        array_map(fn ($n) => __('operaciones.ordenes.nro_aplicacion_opcion_' . $n), range(1, 10))
    );

    // Si nro_aplicacion está fuera de 1-10 (dato viejo, no debería pasar),
    // agregarlo como opción extra.
    if ($nroAplicacion !== '' && ((int) $nroAplicacion < 1 || (int) $nroAplicacion > 10)) {
        $opcionesNroAplicacion[(int) $nroAplicacion] = __('operaciones.ordenes.nro_aplicacion_opcion_extra', ['n' => (int) $nroAplicacion]);
        ksort($opcionesNroAplicacion);
    }

    $lotesPorDefecto = ($lotesOrden ?? null) !== null
        ? $lotesOrden->map(fn ($lote) => ['lote_id' => $lote->lote_id, 'hectareas_solicitadas' => $lote->hectareas_solicitadas])->all()
        : [];
    $lotesIniciales = old('lotes', $lotesPorDefecto);
@endphp

<form method="POST" action="{{ $accion }}" class="ag-ordenes-form" novalidate data-ag-ordenes-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('operaciones.ordenes.titulo_editar') : __('operaciones.ordenes.titulo_crear')"
        :subtitle="__('operaciones.ordenes.subtitulo_form')"
    >
        <x-slot:actions>
            <x-molecules.boton-volver :href="route('panel.ordenes.index')" :label="__('operaciones.ordenes.volver')" />
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    <x-molecules.form-layout>
    {{--
        Sección 1: Datos del contrato (nueva, reforma 18/9/2026)
        Contrato + resumen + contacto (autoseleccionado si único)
    --}}
    <x-molecules.form-section :title="__('operaciones.ordenes.seccion_datos_contrato')">
        {{--
            JSON embebido: datosContrato por contrato_id para el JS. Flags
            JSON_HEX_* (a diferencia del mismo patrón en Comercial,
            `contratos/_formulario.blade.php`): acá el blob incluye nombres
            libres de propiedad/lote/contacto que un operador interno puede
            haber tipeado con `</script>` u otra secuencia — sin los flags,
            eso corta el bloque JSON antes de tiempo.
        --}}
        <script type="application/json" data-ag-datos-contrato>
            {!! json_encode($datosContrato, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
        </script>

        {{--
            Contrato + leyenda de búsqueda, una sola celda del grid (mitad de
            ancho — el select de contacto ocupa la otra mitad, al lado).
        --}}
        <x-atoms.select
            name="contrato_id"
            id="contrato_id"
            :label="__('operaciones.ordenes.campo_contrato')"
            :options="$contratosDisponibles"
            :value="$contratoId"
            :placeholder="__('operaciones.ordenes.campo_contrato_placeholder')"
            :help="__('operaciones.ordenes.campo_contrato_ayuda')"
            searchable
            required
            :error="$errors->first('contrato_id')"
            data-ag-orden-contrato
        />

        {{--
            Contacto: autoseleccionado si único, hidden si es así. Si el
            campo ya viene con un error del servidor (`emitida_por_contacto_id`
            dejó de existir entre la carga y el submit, por ejemplo),
            `data-tiene-error` le avisa al JS que NO lo vuelva a ocultar —
            mismo espíritu que el fix de switches sin `:error` del PR #240:
            un error que el servidor manda nunca puede quedar invisible.
        --}}
        <div data-ag-contacto-wrap @if ($errors->has('emitida_por_contacto_id')) data-tiene-error @endif>
            <x-atoms.select
                name="emitida_por_contacto_id"
                id="emitida_por_contacto_id"
                :label="__('operaciones.ordenes.campo_contacto')"
                :options="$contactosDisponibles"
                :value="$contactoId"
                :placeholder="__('operaciones.ordenes.campo_contacto_placeholder')"
                :error="$errors->first('emitida_por_contacto_id')"
                data-ag-orden-contacto
            />
        </div>

        {{--
            Resumen del contrato — logo del cliente a la izquierda, datos
            (cliente, propiedad(es) — un contrato puede cruzar varias — y
            aplicaciones pactadas) como complemento a la derecha. Celda
            completa del grid (`field--full`), no un grid propio: el grid de
            dos columnas ya lo da `ag-form-section__body` del molecule.
        --}}
        <div class="ag-form-section__field--full ag-ordenes-form__resumen-contrato" data-ag-resumen-contrato hidden>
            {{--
                Una sola imagen: el JS le pone el logo real
                (`datos.logo_url`) o cae al placeholder de acá —
                `data-logo-placeholder` en vez de hardcodear la ruta en el JS.
            --}}
            <img
                class="ag-ordenes-form__logo"
                data-ag-cliente-logo
                data-logo-placeholder="{{ asset('images/logo-placeholder.png') }}"
                src="{{ asset('images/logo-placeholder.png') }}"
                alt=""
            >
            {{--
                Mismas clases que "Datos de la orden" de `ordenes/show.blade.php`
                (`ag-ordenes-detalle__campo-label`/`-valor`) — pedido
                explícito del usuario 18/9/2026: que se vea igual que esa
                pantalla, no un estilo aparte. Grid propio por composición
                (`ag-form-section__body`, mismo criterio que "Condiciones de
                vuelo" de Asignación de equipos) — el de afuera ya lo usan
                el select de Contrato/Contacto de arriba.
            --}}
            <div class="ag-form-section__body ag-ordenes-form__resumen-datos">
                <div class="ag-ordenes-detalle__campo">
                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_cliente') }}</p>
                    <p class="ag-ordenes-detalle__campo-valor" data-ag-cliente-nombre>—</p>
                </div>
                <div class="ag-ordenes-detalle__campo">
                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_propiedades') }}</p>
                    <p class="ag-ordenes-detalle__campo-valor" data-ag-propiedades-nombres>—</p>
                </div>
                <div class="ag-ordenes-detalle__campo">
                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_aplicaciones') }}</p>
                    <p class="ag-ordenes-detalle__campo-valor" data-ag-aplicaciones-previstas>—</p>
                </div>
                <div class="ag-ordenes-detalle__campo">
                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_hectareas') }}</p>
                    <p class="ag-ordenes-detalle__campo-valor" data-ag-hectareas-contratadas>—</p>
                </div>
                <div class="ag-ordenes-detalle__campo">
                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_fecha_inicio') }}</p>
                    <p class="ag-ordenes-detalle__campo-valor" data-ag-fecha-inicio>—</p>
                </div>
                <div class="ag-ordenes-detalle__campo">
                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_fecha_fin') }}</p>
                    <p
                        class="ag-ordenes-detalle__campo-valor"
                        data-ag-fecha-fin
                        data-texto-sin-definir="{{ __('operaciones.ordenes.valor_sin_definir') }}"
                    >—</p>
                </div>
            </div>
        </div>
    </x-molecules.form-section>

    {{--
        Sección 2: Datos de la orden (recortada, 9 campos)
        Sin: contrato_id, emitida_por_contacto_id, clima/vuelo
    --}}
    <x-molecules.form-section
        :title="__('operaciones.ordenes.seccion_datos')"
        :count="__('operaciones.ordenes.campos_contador', ['cantidad' => 9])"
    >

        {{--
            "Tipo" (Sólido/Líquido) es de PRESENTACIÓN: sin `name` validado
            por el server, solo filtra "Categoría de insumo" de abajo.
        --}}
        <x-atoms.select
            name="tipo_insumo_filtro"
            id="tipo_insumo_filtro"
            :label="__('operaciones.ordenes.campo_tipo_insumo')"
            :options="$opcionesTipoInsumo"
            :value="$tipoInsumoSeleccionado"
            :placeholder="__('operaciones.ordenes.campo_tipo_insumo_placeholder')"
            required
            data-ag-orden-tipo-insumo
        />

        <x-atoms.select
            name="categoria_insumo_id"
            id="categoria_insumo_id"
            :label="__('operaciones.ordenes.campo_categoria_insumo')"
            :options="$categoriasInsumoOptions"
            :value="$categoriaInsumoId"
            :placeholder="__('operaciones.ordenes.campo_categoria_insumo_placeholder')"
            required
            :error="$errors->first('categoria_insumo_id')"
            data-ag-orden-categoria-insumo
            data-mapa-categoria-insumo-tipo="{{ $mapaCategoriaInsumoTipo->toJson() }}"
        />

        {{--
            Cuál de los dos campos hace falta depende del tipo_insumo de la
            categoría elegida (HU-79, tarea 110).
        --}}
        <div data-ag-orden-campo-tipo="liquido" @if ($tipoInsumoSeleccionado !== 'liquido') hidden @endif>
            <x-atoms.input
                type="number"
                name="litros_ha"
                :label="__('operaciones.ordenes.campo_litros_ha')"
                :value="$valor('litros_ha')"
                min="0.01"
                step="0.01"
                :error="$errors->first('litros_ha')"
            />
        </div>

        <div data-ag-orden-campo-tipo="solido" @if ($tipoInsumoSeleccionado !== 'solido') hidden @endif>
            <x-atoms.input
                type="number"
                name="kilos_por_vuelo"
                :label="__('operaciones.ordenes.campo_kilos_por_vuelo')"
                :value="$valor('kilos_por_vuelo')"
                min="0.01"
                step="0.01"
                :error="$errors->first('kilos_por_vuelo')"
            />
        </div>

        {{-- nro_aplicacion: cambió de input number a select ordinal --}}
        <x-atoms.select
            name="nro_aplicacion"
            id="nro_aplicacion"
            :label="__('operaciones.ordenes.campo_nro_aplicacion')"
            :options="$opcionesNroAplicacion"
            :value="$nroAplicacion"
            :placeholder="__('operaciones.ordenes.campo_nro_aplicacion_placeholder')"
            :searchable="false"
            required
            :error="$errors->first('nro_aplicacion')"
            data-ag-orden-nro-aplicacion
        />

        {{--
            Input-group: cantidad de equipos + atajo a "Asignar equipos".
            Solo en edición — el atajo lleva a una ficha propia de la orden
            (`panel.asignacion-equipos.show`) que no existe hasta guardar.
        --}}
        <div class="ag-ordenes-form__input-group">
            <x-atoms.input
                type="number"
                name="cantidad_equipos_necesarios"
                :label="__('operaciones.ordenes.campo_cantidad_equipos')"
                :value="$cantidadEquiposNecesarios"
                min="1"
                step="1"
                required
                :error="$errors->first('cantidad_equipos_necesarios')"
            />
            @if ($esEdicion)
                <x-atoms.button
                    :href="route('panel.asignacion-equipos.show', $orden)"
                    variant="outline"
                    icon="groups"
                    size="sm"
                    class="ag-ordenes-form__input-group-boton"
                >
                    {{ __('operaciones.ordenes.campo_cantidad_equipos_asignar') }}
                </x-atoms.button>
            @endif
        </div>

        <x-atoms.select
            name="tipo_aplicacion"
            id="tipo_aplicacion"
            :label="__('operaciones.ordenes.campo_tipo_aplicacion')"
            :options="$opcionesTipoAplicacion"
            :value="$tipoAplicacion"
            required
            :error="$errors->first('tipo_aplicacion')"
        />

        <x-atoms.date
            name="fecha_emision"
            :label="__('operaciones.ordenes.campo_fecha_emision')"
            :value="$fechaEmision"
            required
            :error="$errors->first('fecha_emision')"
        />

        <div class="ag-form-section__field--full">
            <x-atoms.textarea
                name="observaciones"
                :label="__('operaciones.ordenes.campo_observaciones')"
                :value="$valor('observaciones')"
                :error="$errors->first('observaciones')"
            />
        </div>
    </x-molecules.form-section>

    {{--
        Sección 3: Lotes de la orden (tabla nueva con checkbox, búsqueda, paginado)
        Reemplaza el repetible anterior de `_lote-orden-fila.blade.php`.
    --}}
    <x-molecules.form-section :title="__('operaciones.ordenes.seccion_lotes')" class="ag-ordenes-form__lotes-seccion">
        {{--
            Buscador arriba a la derecha del título — mismas clases
            visuales que el buscador global del header (`ag-topbar__search*`,
            ver `organisms/topbar.blade.php`), pedido explícito del usuario
            18/9/2026. `role="search"`, no `<form>`: acá no navega a
            ningún lado, filtra la tabla en el cliente (`ordenes-form.js`).
            Oculto hasta elegir contrato, igual que el resto del contenido
            (`data-ag-lotes-buscador-wrap`, ver JS).
        --}}
        <x-slot:actions>
            <div class="ag-topbar__search ag-ordenes-form__lotes-buscador" role="search" data-ag-lotes-buscador-wrap hidden>
                <span class="material-symbols-rounded ag-icon ag-icon--sm ag-topbar__search-icon" aria-hidden="true">search</span>
                <input
                    type="search"
                    class="ag-topbar__search-input"
                    placeholder="{{ __('operaciones.ordenes.lotes_buscar_placeholder') }}"
                    aria-label="{{ __('operaciones.ordenes.lotes_buscar_aria') }}"
                    autocomplete="off"
                    data-ag-lotes-buscar
                >
            </div>
        </x-slot:actions>

        <div class="ag-form-section__field--full">
            {{--
                Semilla de selección: `old('lotes')` tras un error de
                validación, o los lotes ya guardados de la orden en edición
                (`$lotesOrden`) — sin esto, `ordenes-form.js` no tiene forma
                de saber qué filas venían tildadas al cargar la página.
            --}}
            <script type="application/json" data-ag-lotes-iniciales>
                {!! json_encode($lotesIniciales, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
            </script>

            {{--
                Errores de `lotes` — TODOS, no solo la clave exacta `lotes`
                ("Agrega al menos un lote"): `withValidator()` también agrega
                errores por fila (`lotes.N.lote_id` si es de otro cliente,
                `lotes.N.hectareas_solicitadas` si supera el lote), y la
                tabla se arma 100% en JS sin un lugar propio donde pintarlos
                fila por fila. Sin este bloque, esos errores llegan al
                servidor y vuelven en `$errors` pero no se ven en ningún
                lado — mismo punto ciego que ya se corrigió en switches sin
                `:error` (PR #240): la validación falla en silencio.
            --}}
            @php
                $erroresLotes = collect($errors->keys())
                    ->filter(fn (string $clave): bool => $clave === 'lotes' || str_starts_with($clave, 'lotes.'))
                    ->map(fn (string $clave): string => $errors->first($clave))
                    ->unique()
                    ->values();
            @endphp
            @if ($erroresLotes->isNotEmpty())
                <div class="ag-input__error" role="alert">
                    @foreach ($erroresLotes as $mensaje)
                        <p>{{ $mensaje }}</p>
                    @endforeach
                </div>
            @endif

            {{--
                Sin contrato elegido todavía: card de estado vacío SOLA, sin
                el borde/fondo de tabla de abajo (pedido explícito del
                usuario 18/9/2026 — mostrar un control de búsqueda para una
                tabla que todavía no tiene universo de datos es confuso, Y
                una tarjeta de estado vacío dentro de otra tarjeta de tabla
                se ve anidada de más). Arranca visible; `ordenes-form.js` la
                oculta apenas hay contrato (elegido a mano, o ya cargado en
                edición/redisplay).
            --}}
            <x-molecules.empty-state
                icon="inventory_2"
                :title="__('operaciones.ordenes.lotes_vacio_titulo')"
                :detail="__('operaciones.ordenes.lotes_vacio_detalle')"
                data-ag-lotes-sin-contrato
            />

            {{--
                Contenido real (buscador + tabla + paginado): oculto hasta
                elegir contrato. La clase/estilo de "tabla" (borde, fondo,
                radio) vive ACÁ, no en el wrapper de arriba — solo tiene
                sentido cuando hay una tabla real que mostrar.
            --}}
            <div
                class="ag-ordenes-form__lotes-tabla"
                data-ag-tabla-lotes
                data-ag-lotes-contenido
                data-texto-col-codigo="{{ __('operaciones.ordenes.lotes_columna_codigo') }}"
                data-texto-col-propiedad="{{ __('operaciones.ordenes.lotes_columna_propiedad') }}"
                data-texto-col-hectareas-lote="{{ __('operaciones.ordenes.lotes_columna_hectareas_lote') }}"
                data-texto-col-hectareas-solicitadas="{{ __('operaciones.ordenes.campo_lote_hectareas') }}"
                data-texto-col-hectareas-solicitadas-ayuda="{{ __('operaciones.ordenes.lotes_columna_hectareas_solicitadas_ayuda') }}"
                data-texto-col-desnivel="{{ __('operaciones.ordenes.lotes_columna_desnivel') }}"
                data-texto-col-limpieza="{{ __('operaciones.ordenes.lotes_columna_limpieza') }}"
                data-texto-seleccionar-todos="{{ __('operaciones.ordenes.lotes_seleccionar_todos') }}"
                hidden
            >
                {{-- Tabla de lotes (todos los de datosContrato[contratoId].lotes, con checkbox) --}}
                <div class="ag-ordenes-form__lotes-contenedor" data-ag-lotes-contenedor>
                    <!-- Rellenado por JS al elegir contrato -->
                </div>

                {{-- Paginado (mínimo: botones anterior/siguiente) --}}
                <div class="ag-ordenes-form__lotes-paginado" data-ag-lotes-paginado hidden>
                    <button type="button" class="ag-button ag-button--text ag-button--sm" data-ag-pagina-anterior aria-label="{{ __('operaciones.ordenes.lotes_pagina_anterior') }}">
                        <span class="material-symbols-rounded ag-icon ag-icon--sm">chevron_left</span>
                        {{ __('operaciones.ordenes.lotes_pagina_anterior') }}
                    </button>
                    <span class="ag-ordenes-form__paginado-info" data-ag-pagina-info>1 / 1</span>
                    <button type="button" class="ag-button ag-button--text ag-button--sm" data-ag-pagina-siguiente aria-label="{{ __('operaciones.ordenes.lotes_pagina_siguiente') }}">
                        {{ __('operaciones.ordenes.lotes_pagina_siguiente') }}
                        <span class="material-symbols-rounded ag-icon ag-icon--sm">chevron_right</span>
                    </button>
                </div>

                {{-- Estado vacío: contrato elegido, pero la búsqueda no encuentra nada (o el contrato no tiene lotes). --}}
                <div class="ag-ordenes-form__lotes-vacio" data-ag-lotes-vacio hidden>
                    {{ __('operaciones.ordenes.lotes_tabla_vacio') }}
                </div>
            </div>
        </div>
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('operaciones.ordenes.estado_form')">
        <x-slot:actions>
            <x-atoms.button :href="route('panel.ordenes.index')" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>

    @if ($esEdicion)
        <x-slot:aside>
            @foreach ($resumenRelacionado ?? [] as $resumen)
                @if ($resumen['tieneDatos'])
                    <x-molecules.summary-card :title="$resumen['titulo']" :items="$resumen['items']">
                        @if ($resumen['mostrarAccion'])
                            <x-slot:action>
                                <x-atoms.button :href="$resumen['accion']['href']" variant="outline" icon="groups" block>
                                    {{ $resumen['accion']['label'] }}
                                </x-atoms.button>
                            </x-slot:action>
                        @endif
                    </x-molecules.summary-card>
                @else
                    <x-molecules.empty-state :icon="$resumen['icono']" :title="$resumen['vacioTitulo']" :detail="$resumen['vacioDetalle']">
                        @if ($resumen['mostrarAccion'])
                            <x-slot:action>
                                <x-atoms.button :href="$resumen['accion']['href']" variant="outline" icon="groups">
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
