{{--
    Partial: formulario de cultivo, compartido por create.blade.php y
    edit.blade.php (HU-48, tarea 71; atributos agronómicos, ampliación
    16/9/2026) — arquetipo Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md.

    Espera:
    - $cultivo (Cultivo|null): null en alta; el modelo en edición.
    - $tiposCultivo (list<TipoCultivo>), $ciclosVida (list<CicloVidaCultivo>):
      opciones de los dos select.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.

    Dos tarjetas: "Datos del cultivo" (nombre común, nombre científico,
    tipo, ciclo de vida) y "Notas agronómicas" (texto libre aparte, a
    propósito — es sugerencia informativa de un agrónomo, no un dato
    estructurado del catálogo; memoria "la mezcla es del cliente": Agrocom
    no define ni valida la composición del caldo).

    Sin campo `activo`: es una propiedad de esquema (todo registro nace
    activo), no una decisión del formulario — §6.3.3 de
    docs/diseno/guia_pantalla_panel.md. Se activa/desactiva desde una pieza
    aparte, todavía sin construir.

    Resumen relacionado en edición (§6.3.1 de la guía): dos tarjetas —lotes
    sembrados con este cultivo y propiedades donde aparece— sobre la campaña
    vigente, armadas server-side por `CultivosController::resumenRelacionado()`
    con `Aplicacion/ResumirSiembraDeCultivo`. Cada una viene gateada por el
    permiso `.ver` de lote/propiedad; un rol sin ninguno de los dos no tiene
    aside. En el alta no hay aside: un cultivo que todavía no existe no está
    sembrado en ningún lado.

    - $resumenCultivo (list<array>, solo edición): las tarjetas del aside, con
      la forma de `resumenPropiedad` (`titulo`, `icono`, `tieneDatos`,
      `items`, `vacioTitulo`, `vacioDetalle`, `acciones`).
--}}
@php
    $esEdicion = $cultivo !== null;
    $accion = $esEdicion ? route('panel.cultivos.update', $cultivo) : route('panel.cultivos.store');
    $nombreComun = old('nombre_comun', $cultivo?->nombre_comun ?? '');
    $nombreCientifico = old('nombre_cientifico', $cultivo?->nombre_cientifico ?? '');
    $tipoCultivoValor = old('tipo_cultivo', $cultivo?->tipo_cultivo?->value ?? '');
    $cicloVidaValor = old('ciclo_vida', $cultivo?->ciclo_vida?->value ?? '');
    $notasAgronomicas = old('notas_agronomicas', $cultivo?->notas_agronomicas ?? '');

    $tiposCultivoOptions = collect($tiposCultivo)->mapWithKeys(fn ($tipo) => [
        $tipo->value => __('comercial.cultivos.tipo_cultivo_opcion.'.$tipo->value),
    ]);
    $ciclosVidaOptions = collect($ciclosVida)->mapWithKeys(fn ($ciclo) => [
        $ciclo->value => __('comercial.cultivos.ciclo_vida_opcion.'.$ciclo->value),
    ]);
@endphp

<form method="POST" action="{{ $accion }}" class="ag-cultivos-form" novalidate data-ag-cultivos-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('comercial.cultivos.titulo_editar') : __('comercial.cultivos.titulo_crear')"
        :subtitle="__('comercial.cultivos.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button :href="route('panel.cultivos.index')" variant="outline" icon="arrow_back">
                {{ __('comercial.cultivos.volver') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    <x-molecules.form-layout>
    @if ($esEdicion && ($resumenCultivo ?? []) !== [])
        <x-slot:aside>
            @foreach ($resumenCultivo as $resumen)
                @if ($resumen['tieneDatos'])
                    <x-molecules.summary-card :title="$resumen['titulo']" :items="$resumen['items']">
                        @if ($resumen['acciones'] !== [])
                            <x-slot:action>
                                @foreach ($resumen['acciones'] as $accion)
                                    <x-atoms.button :href="$accion['href']" variant="outline" :icon="$accion['icono'] ?? 'arrow_forward'" block>
                                        {{ $accion['label'] }}
                                    </x-atoms.button>
                                @endforeach
                            </x-slot:action>
                        @endif
                    </x-molecules.summary-card>
                @else
                    <x-molecules.empty-state
                        :icon="$resumen['icono']"
                        :title="$resumen['vacioTitulo']"
                        :detail="$resumen['vacioDetalle']"
                    >
                        @if ($resumen['acciones'] !== [])
                            <x-slot:action>
                                @foreach ($resumen['acciones'] as $accion)
                                    <x-atoms.button :href="$accion['href']" variant="outline" :icon="$accion['icono'] ?? 'add'">
                                        {{ $accion['label'] }}
                                    </x-atoms.button>
                                @endforeach
                            </x-slot:action>
                        @endif
                    </x-molecules.empty-state>
                @endif
            @endforeach
        </x-slot:aside>
    @endif

    <x-molecules.form-section
        :title="__('comercial.cultivos.seccion_datos')"
        :count="__('comercial.cultivos.campos_contador', ['cantidad' => 4])"
    >
        <x-atoms.input
            type="text"
            name="nombre_comun"
            :label="__('comercial.cultivos.campo_nombre_comun')"
            :value="$nombreComun"
            :help="__('comercial.cultivos.campo_nombre_comun_ayuda')"
            required
            :error="$errors->first('nombre_comun')"
        />

        <x-atoms.input
            type="text"
            name="nombre_cientifico"
            :label="__('comercial.cultivos.campo_nombre_cientifico')"
            :value="$nombreCientifico"
            :placeholder="__('comercial.cultivos.campo_nombre_cientifico_placeholder')"
            :help="__('comercial.cultivos.campo_nombre_cientifico_ayuda')"
            :error="$errors->first('nombre_cientifico')"
        />

        <x-atoms.select
            name="tipo_cultivo"
            id="tipo_cultivo"
            :label="__('comercial.cultivos.campo_tipo_cultivo')"
            :options="$tiposCultivoOptions"
            :value="$tipoCultivoValor"
            :placeholder="__('comercial.cultivos.campo_tipo_cultivo_placeholder')"
            required
            :error="$errors->first('tipo_cultivo')"
        />

        <x-atoms.select
            name="ciclo_vida"
            id="ciclo_vida"
            :label="__('comercial.cultivos.campo_ciclo_vida')"
            :options="$ciclosVidaOptions"
            :value="$cicloVidaValor"
            :placeholder="__('comercial.cultivos.campo_ciclo_vida_placeholder')"
            required
            :error="$errors->first('ciclo_vida')"
        />
    </x-molecules.form-section>

    <x-molecules.form-section
        :title="__('comercial.cultivos.seccion_notas')"
        :count="__('comercial.cultivos.campos_contador', ['cantidad' => 1])"
    >
        <x-atoms.textarea
            class="ag-form-section__field--full"
            name="notas_agronomicas"
            :label="__('comercial.cultivos.campo_notas_agronomicas')"
            :placeholder="__('comercial.cultivos.campo_notas_agronomicas_placeholder')"
            :value="$notasAgronomicas"
            :help="__('comercial.cultivos.campo_notas_agronomicas_ayuda')"
            :error="$errors->first('notas_agronomicas')"
        />
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('comercial.cultivos.estado_form')">
        <x-slot:actions>
            <x-atoms.button :href="route('panel.cultivos.index')" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>
    </x-molecules.form-layout>
</form>
