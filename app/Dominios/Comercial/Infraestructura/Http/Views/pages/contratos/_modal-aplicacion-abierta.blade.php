{{--
    Partial: aviso de que un contrato todavía no se puede finalizar ni cancelar
    porque tiene una aplicación abierta (ADR 0022). Lo comparten los pasos de la
    ficha de edición (`_cambio-estado.blade.php`) y las acciones del listado
    (`index.blade.php`), así los dos dicen lo mismo.

    No es una confirmación: sin `<form>` ni botón de confirmar (ver
    `molecules/info-modal`). Primero se cierra o se cancela esa orden — al
    cancelarla se indican la causa y el motivo — y recién después el contrato.
    La restricción es informativa acá y firme en el servidor:
    `MaquinaEstadosContrato` la sigue exigiendo aunque alguien se salte la
    pantalla.

    Espera:
    - $modalId (string): `id` del modal; el mismo que el disparador ya apunta.
    - $contrato (Contrato, con `cliente` cargado).
    - $aplicacion (DatosAplicacionAbierta): la aplicación en curso, que llega
      por el contrato de lectura de Operaciones.
    - $accion ('finalizar'|'cancelar'): lo que el usuario quería hacer.
    - $puedeVerOrden (bool): sin permiso para ver la orden no se ofrece el enlace.
    - $volverA / $volverTexto (opcionales): a dónde vuelve el usuario desde la
      orden; por defecto, la ficha de este contrato.
--}}
@php
    $finalizar = $accion === 'finalizar';
    $volverA ??= route('panel.contratos.edit', $contrato);
    $volverTexto ??= $contrato->cliente->razon_social;
    // El estado al que se quería pasar, con su color: el aviso lo lleva igual que
    // la confirmación que habría abierto. Solo un contrato vigente tiene una
    // aplicación abierta, así que «desde» es siempre `vigente`.
    $destinoAviso = $finalizar ? 'finalizado' : 'cancelado';
    $tonoPorEstado ??= \App\Dominios\Comercial\Infraestructura\Http\PasosDeContrato::TONO_POR_ESTADO;
@endphp

<x-molecules.info-modal
    :id="$modalId"
    :title="__($finalizar ? 'comercial.contratos.aviso_aplicacion_titulo_finalizar' : 'comercial.contratos.aviso_aplicacion_titulo_cancelar')"
    :message="__($finalizar ? 'comercial.contratos.aviso_aplicacion_mensaje_finalizar' : 'comercial.contratos.aviso_aplicacion_mensaje_cancelar', [
        'nro' => $aplicacion->nroAplicacion,
        'total' => $contrato->aplicaciones_previstas,
        'estado' => $aplicacion->estadoEtiqueta,
    ])"
    :close-label="__('comercial.contratos.aviso_aplicacion_entendido')"
    :tone="$tonoPorEstado[$destinoAviso]"
    modal-icon="block"
>
    @include('comercial::pages.contratos._estado-transicion', [
        'desde' => 'vigente',
        'hacia' => $destinoAviso,
        'tonoPorEstado' => $tonoPorEstado,
    ])

    <p class="ag-contratos-estado__nota">{{ __('comercial.contratos.aviso_aplicacion_motivo') }}</p>

    @if ($puedeVerOrden)
        <x-slot:actions>
            <x-atoms.button
                :href="route('panel.ordenes.show', [
                    'orden' => $aplicacion->ordenId,
                    'volver_a' => $volverA,
                    'volver_texto' => $volverTexto,
                ])"
                variant="outline"
                icon="open_in_new"
            >
                {{ __('comercial.contratos.aviso_aplicacion_ir') }}
            </x-atoms.button>
        </x-slot:actions>
    @endif
</x-molecules.info-modal>
