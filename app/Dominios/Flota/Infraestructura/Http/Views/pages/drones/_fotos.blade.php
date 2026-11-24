{{--
    Parcial: las fotos que el proveedor subió del dron — frontal, lateral, placa
    de serie y otras. Se ven desde el disco privado (ruta del panel, nunca una URL
    pública) y se aprueban o rechazan una por una, con motivo al rechazar. Un dron
    no se aprueba sin una foto aprobada de cada tipo obligatorio
    (`config('flota.fotos_obligatorias')`); el cliente solo ve las aprobadas.

    Espera: $dron, $fotos (list<array{id, tipo, estado, motivo}>), $fotosFaltantes
    (list<string>) y $puedeRevisarFotos (bool).
--}}
@php
    $tonos = ['en_revision' => 'warning', 'aprobada' => 'success', 'rechazada' => 'danger'];
@endphp
<x-molecules.form-section accent="distintivo-2" :title="__('flota.fotos.titulo')" :count="(string) count($fotos)">
    <div class="ag-form-section__field--full">
        @if (count($fotosFaltantes) > 0)
            <x-molecules.alert-strip variant="warning" icon="photo_camera">
                {{ __('flota.fotos.faltan', ['fotos' => implode(', ', array_map(fn ($t) => mb_strtolower(__('flota.fotos.tipo.'.$t)), $fotosFaltantes))]) }}
            </x-molecules.alert-strip>
        @endif

        @if (count($fotos) === 0)
            <p class="ag-solicitudes__sub">{{ __('flota.fotos.vacio') }}</p>
        @else
            <ul class="ag-flota__fotos">
                @foreach ($fotos as $foto)
                    <li class="ag-flota__foto">
                        <a href="{{ route('panel.drones-flota.foto', [$dron, $foto['id']]) }}" target="_blank" rel="noopener">
                            <img
                                src="{{ route('panel.drones-flota.foto', [$dron, $foto['id']]) }}"
                                alt="{{ __('flota.fotos.alt', ['tipo' => __('flota.fotos.tipo.'.$foto['tipo']), 'numero' => $loop->iteration]) }}"
                                loading="lazy"
                            >
                        </a>
                        <span class="ag-solicitudes__nombre">{{ __('flota.fotos.tipo.'.$foto['tipo']) }}</span>
                        <x-atoms.badge :variant="$tonos[$foto['estado']] ?? 'neutral'">{{ __('flota.fotos.estado.'.$foto['estado']) }}</x-atoms.badge>
                        @if ($foto['motivo'])
                            <span class="ag-solicitudes__sub">{{ __('flota.fotos.motivo', ['motivo' => $foto['motivo']]) }}</span>
                        @endif
                        @if ($puedeRevisarFotos && $foto['estado'] === 'en_revision')
                            <form method="POST" action="{{ route('panel.drones-flota.foto-revisar', [$dron, $foto['id']]) }}" class="ag-flota__foto-form">
                                @csrf
                                <x-atoms.input
                                    type="text"
                                    name="motivo"
                                    :label="__('flota.fotos.motivo_etiqueta')"
                                    :help="__('flota.fotos.motivo_ayuda')"
                                    :id="'foto-motivo-'.$foto['id']"
                                    autocomplete="off"
                                />
                                <div class="ag-flota__foto-acciones">
                                    <x-atoms.button type="submit" name="decision" value="aprobar" variant="success-outline" size="sm" icon="check">
                                        {{ __('flota.fotos.aprobar') }}
                                    </x-atoms.button>
                                    <x-atoms.button type="submit" name="decision" value="rechazar" variant="danger-outline" size="sm" icon="close">
                                        {{ __('flota.fotos.rechazar') }}
                                    </x-atoms.button>
                                </div>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-molecules.form-section>
