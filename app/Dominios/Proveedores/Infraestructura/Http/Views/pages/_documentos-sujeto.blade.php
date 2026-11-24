{{--
    Partial: sección «Documentos» de una ficha de aprobación (ADR 0032). Tabla de
    los documentos del sujeto con su estado y vencimiento, y las acciones de
    revisión por fila: ver el archivo (acceso restringido), aprobar y rechazar con
    motivo. Avisa los documentos obligatorios que faltan.

    Espera:
    - $documentos (list<DatosDocumento>), $documentosFaltantes (list<TipoDocumento>).
    - $puedeVerDocumentos, $puedeRevisarDocumentos (bool): permisos del rol activo.
    - $modalBaseDocumentos (string): prefijo del id de los modales de los documentos.
--}}
<x-molecules.form-section accent="info" :title="__('proveedores.documentos.titulo')" :count="(string) count($documentos)">
    @if ($documentosFaltantes !== [])
        <div class="ag-form-section__field--full">
            <x-molecules.alert-strip variant="warning" icon="assignment_late">
                {{ __('proveedores.documentos.faltantes', ['documentos' => collect($documentosFaltantes)->map(fn ($tipo) => __('proveedores.documentos.tipo.'.$tipo->value))->implode(', ')]) }}
            </x-molecules.alert-strip>
        </div>
    @endif

    @if ($documentos === [])
        <div class="ag-form-section__field--full">
            <x-molecules.empty-state
                icon="folder_off"
                :title="__('proveedores.documentos.vacio_titulo')"
                :detail="__('proveedores.documentos.vacio_detalle')"
            />
        </div>
    @else
        <div class="ag-form-section__field--full">
            <x-molecules.index-table columns="minmax(0, 1.4fr) minmax(0, 0.9fr) minmax(0, 0.9fr) minmax(0, 1.4fr) var(--ag-row-actions-width)">
                <x-slot:head>
                    <span role="columnheader">{{ __('proveedores.documentos.col_tipo') }}</span>
                    <span role="columnheader">{{ __('proveedores.documentos.col_vence') }}</span>
                    <span role="columnheader">{{ __('proveedores.documentos.col_estado') }}</span>
                    <span role="columnheader">{{ __('proveedores.documentos.col_detalle') }}</span>
                    <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                </x-slot:head>

                @foreach ($documentos as $documento)
                    @php
                        $tonoDocumento = ['en_revision' => 'warning', 'aprobado' => 'success', 'rechazado' => 'danger', 'vencido' => 'danger'][$documento->estado] ?? 'neutral';
                    @endphp
                    <div class="ag-index-table__row" role="row">
                        <span role="cell">{{ __('proveedores.documentos.tipo.'.$documento->tipo) }}</span>
                        <span role="cell">
                            {{ $documento->venceEn ?? __('proveedores.documentos.sin_vencimiento') }}
                            @if ($documento->estaPorVencer())
                                <x-atoms.badge variant="warning" icon="event_upcoming">{{ __('proveedores.documentos.vence_en_dias', ['dias' => max($documento->diasParaVencer, 0)]) }}</x-atoms.badge>
                            @endif
                        </span>
                        <span role="cell">
                            <x-atoms.badge :variant="$tonoDocumento">{{ __('proveedores.documentos.estado.'.$documento->estado) }}</x-atoms.badge>
                        </span>
                        <span role="cell">
                            {{ $documento->motivoRechazo ?? __('proveedores.documentos.subido_el', ['fecha' => $documento->subidoEn]) }}
                        </span>
                        <span role="cell" class="ag-index-table__acciones">
                            @include('proveedores::pages._acciones-documento', [
                                'docId' => $documento->id,
                                'docEstado' => $documento->estado,
                                'docEtiqueta' => __('proveedores.documentos.tipo.'.$documento->tipo),
                                'puedeVer' => $puedeVerDocumentos,
                                'puedeRevisar' => $puedeRevisarDocumentos,
                            ])
                        </span>
                    </div>
                @endforeach
            </x-molecules.index-table>
        </div>
    @endif
</x-molecules.form-section>
