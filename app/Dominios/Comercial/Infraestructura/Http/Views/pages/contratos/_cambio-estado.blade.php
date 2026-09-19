{{--
    Partial: formularios + modales del cambio de estado de UN contrato, para
    los pasos de `molecules/step-arrow` de la ficha de edición
    (`_formulario.blade.php`). El paso solo abre el modal; el modal envía el
    `<form>` de acá a `panel.contratos.cambiar-estado`, que pasa por
    `CambiarEstadoContrato` → `MaquinaEstadosContrato` (invariante 7).

    Va FUERA del `<form>` de `_formulario.blade.php` a propósito — un `<form>`
    no puede anidarse en otro — y los `<form>` van con `hidden` para no sumar
    un hueco en el contenedor. Mismo patrón que
    `campania::pages.campanias._cambio-estado`.

    Espera:
    - $contrato (Contrato).
    - $pasosEstado (list<array{...}>): los de `PasosDeContrato::armar()`. Solo
      los pasos `next` (a los que el usuario puede pasar) traen su modal; el
      `id` del modal es el `modal` del paso.
    - $aplicacionAbierta (DatosAplicacionAbierta|null): la aplicación que sigue
      en curso (ADR 0022). Viene por el contrato de lectura de Operaciones.
    - $puedeVerOrden (bool): si el usuario puede abrir la orden — sin permiso
      el aviso explica lo mismo pero no ofrece el enlace.
    - $contratosQueEntranEnConflicto (list<array{cliente, lotes, editar_url}>):
      los contratos que quedarían «En conflicto» si este se aprueba (ADR 0021).

    Dos avisos según lo que el servidor va a exigir, para que el usuario no
    confirme algo que luego se le rechaza:
    - Finalizar o cancelar con una aplicación abierta NO es una confirmación
      sino un modal informativo: primero se cierra o se cancela esa orden (ahí
      se indican la causa y el motivo) y recién después el contrato. El
      servidor sigue rechazándolo aunque alguien salte la pantalla.
    - Aprobar un contrato que comparte lotes con otros en aprobación lista, en
      el mismo modal de confirmación, cuáles van a quedar en conflicto.

    Textos de cada confirmación: los mismos que el listado (`index.blade.php`).
    El TONO de cada modal es el del estado al que se pasa —el de su badge, ver
    `PasosDeContrato::TONO_POR_ESTADO`— y dentro va la ficha «estado actual →
    estado destino» (`_estado-transicion`), para ver antes de confirmar a cuál
    se pasa.
--}}
@php
    $tonoPorEstado = \App\Dominios\Comercial\Infraestructura\Http\PasosDeContrato::TONO_POR_ESTADO;
@endphp

@foreach ($pasosEstado as $paso)
    @continue($paso['status'] !== 'next')

    @php
        $destino = $paso['key'];
        $formIdEstado = "contrato-estado-form-{$destino}";
        $cierraContrato = in_array($destino, ['finalizado', 'cancelado'], true);
    @endphp

    @if ($cierraContrato && $aplicacionAbierta !== null)
        {{-- Aviso, no confirmación: sin `<form>` ni botón de confirmar. --}}
        @include('comercial::pages.contratos._modal-aplicacion-abierta', [
            'modalId' => $paso['modal'],
            'contrato' => $contrato,
            'aplicacion' => $aplicacionAbierta,
            'accion' => $destino === 'finalizado' ? 'finalizar' : 'cancelar',
            'puedeVerOrden' => $puedeVerOrden,
        ])

        @continue
    @endif

    <form id="{{ $formIdEstado }}" method="POST" action="{{ route('panel.contratos.cambiar-estado', $contrato) }}" hidden>
        @csrf
        <input type="hidden" name="estado" value="{{ $destino }}">
    </form>

    @php
        $esAprobar = $destino === 'vigente' && $contrato->estado->value === 'borrador';

        // [título, mensaje, texto del botón, ícono del círculo]: el ícono es el del
        // botón de la fila del listado; `null` deja el que trae `confirm-modal`.
        [$tituloModal, $mensajeModal, $accionModal, $iconoModal] = match (true) {
            $esAprobar => [__('comercial.contratos.confirmar_aprobar_titulo'), __('comercial.contratos.confirmar_aprobar'), __('comercial.contratos.accion_aprobar'), null],
            $destino === 'vigente' => [__('comercial.contratos.confirmar_reanudar_titulo'), __('comercial.contratos.confirmar_reanudar'), __('comercial.contratos.accion_reanudar'), 'play_circle'],
            $destino === 'pausado' => [__('comercial.contratos.confirmar_pausar_titulo'), __('comercial.contratos.confirmar_pausar'), __('comercial.contratos.accion_pausar'), 'pause_circle'],
            $destino === 'finalizado' => [__('comercial.contratos.confirmar_finalizar_titulo'), __('comercial.contratos.confirmar_finalizar'), __('comercial.contratos.accion_finalizar'), null],
            default => [__('comercial.contratos.confirmar_cancelar_titulo'), __('comercial.contratos.confirmar_cancelar'), __('comercial.contratos.accion_cancelar'), null],
        };
    @endphp

    <x-molecules.confirm-modal
        :id="$paso['modal']"
        :form-id="$formIdEstado"
        :title="$tituloModal"
        :message="$mensajeModal"
        :confirm-label="$accionModal"
        :cancel-label="$destino === 'cancelado' ? __('ui.action.close') : null"
        :tone="$tonoPorEstado[$destino]"
        :modal-icon="$iconoModal"
    >
        @include('comercial::pages.contratos._estado-transicion', [
            'desde' => $contrato->estado->value,
            'hacia' => $destino,
            'tonoPorEstado' => $tonoPorEstado,
        ])

        @if ($esAprobar && $contratosQueEntranEnConflicto !== [])
            <div class="ag-contratos-estado__aviso" role="note">
                <p class="ag-contratos-estado__nota">{{ __('comercial.contratos.aprobar_conflictos_aviso') }}</p>
                <ul class="ag-contratos-estado__lista">
                    @foreach ($contratosQueEntranEnConflicto as $otro)
                        <li class="ag-contratos-estado__item">
                            <span>{{ __('comercial.contratos.aprobar_conflictos_item', ['cliente' => $otro['cliente'], 'lotes' => $otro['lotes']]) }}</span>
                            <a class="ag-contratos-estado__enlace" href="{{ $otro['editar_url'] }}">{{ __('comercial.contratos.aprobar_conflictos_ver') }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </x-molecules.confirm-modal>
@endforeach
