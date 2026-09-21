{{--
    Partial: formulario de emisión de una factura, incluido por
    create.blade.php (HU-31, tarea 45; homogeneizado en la tarea 120) —
    arquetipo Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md. Un solo
    campo: el acta a facturar. El monto no se pide ni se muestra acá — lo
    calcula el servidor al confirmar (hectáreas conformadas del acta × precio
    por hectárea del contrato).

    Solo alta y sin aside: una factura emitida es un snapshot inmutable (no hay
    edición ni baja), así que no tiene ficha a la que volver ni nada
    relacionado que resumir. `store()` vuelve al listado, donde se pinta el
    aviso de éxito; el bloque `session('estado')` de acá es el mismo que llevan
    todos los formularios del panel.

    Espera:
    - $actasDisponibles (list<array{actaId: int, contratoId: int,
      hectareasConformadas: string, clienteNombre: string}>): actas firmadas
      sin factura, ya resueltas por `Aplicacion/ListarActasFacturables`.

    Sin actas por facturar el formulario se dibuja igual (§6.3.5 de la guía):
    la sección muestra el vacío que explica qué falta y el guardado lo frena
    la validación del campo obligatorio, como con cualquier otro.

    Tras un error de validación (incluido el rechazo por `ActaNoFacturable`,
    capturado en FacturasController::store()), `old()` pisa el valor vacío.

    Estilos en resources/css/pages/facturas.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@use('App\Dominios\Comercial\Infraestructura\Http\FormatoMonto')
@php
    $actaId = old('acta_id', '');
    $actasOptions = collect($actasDisponibles)->mapWithKeys(fn ($acta) => [
        $acta['actaId'] => __('comercial.facturas.campo_acta_opcion', [
            'cliente' => $acta['clienteNombre'],
            'id' => $acta['actaId'],
            'hectareas' => FormatoMonto::decimal($acta['hectareasConformadas']),
        ]),
    ]);
@endphp

<form method="POST" action="{{ route('panel.facturas.store') }}" class="ag-facturas-form" novalidate>
    @csrf

    <x-organisms.page-header
        :title="__('comercial.facturas.titulo_crear')"
        :subtitle="__('comercial.facturas.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button :href="route('panel.facturas.index')" variant="outline" icon="arrow_back">
                {{ __('comercial.facturas.volver') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    <x-molecules.form-layout>
        <x-molecules.form-section
            :title="__('comercial.facturas.seccion_datos')"
            :count="__('comercial.facturas.campos_contador', ['cantidad' => 1])"
        >
            <div class="ag-form-section__field--full">
                <x-atoms.select
                    name="acta_id"
                    id="acta_id"
                    :label="__('comercial.facturas.campo_acta')"
                    :options="$actasOptions"
                    :value="$actaId"
                    :placeholder="__('comercial.facturas.campo_acta_placeholder')"
                    required
                    :error="$errors->first('acta_id')"
                />
            </div>

            @if (count($actasDisponibles) === 0)
                <x-molecules.empty-state
                    class="ag-form-section__field--full"
                    icon="receipt_long"
                    :title="__('comercial.facturas.sin_actas_titulo')"
                    :detail="__('comercial.facturas.sin_actas_detalle')"
                />
            @endif
        </x-molecules.form-section>

        <x-organisms.form-actions-bar :status="__('comercial.facturas.estado_form')">
            <x-slot:actions>
                <x-atoms.button :href="route('panel.facturas.index')" variant="outline">
                    {{ __('ui.action.cancel') }}
                </x-atoms.button>
                <x-atoms.button type="submit" variant="primary">
                    {{ __('ui.action.save') }}
                </x-atoms.button>
            </x-slot:actions>
        </x-organisms.form-actions-bar>
    </x-molecules.form-layout>
</form>
