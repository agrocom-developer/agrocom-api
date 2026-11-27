{{--
    Partial: sección «Documentos» de una ficha de aprobación (ADR 0032). Los documentos del sujeto como
    cuadrícula de bloques (`organisms/cuadricula-medios`): la miniatura, el tipo y el estado. Revisar un
    documento (aprobar, rechazar con motivo, descargar el original) se hace en el modal de la cuadrícula, que
    muestra el archivo completo; en el bloque no hay acciones. Avisa los documentos obligatorios que faltan.

    Espera:
    - $itemsDocumentos (list<array>): ItemsDeRevisionDeDocumentos::deFicha().
    - $documentos (list<DatosDocumento>), $documentosFaltantes (list<TipoDocumento>).
    - $puedeVerDocumentos (bool): sin `proveedores.documento.ver` no hay archivo que mostrar y la sección
      lista solo tipo y estado.
    - $idCuadricula (string, opcional): id de la cuadrícula (default «documentos»).
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
    @elseif ($puedeVerDocumentos)
        <div class="ag-form-section__field--full">
            <x-organisms.cuadricula-medios :id="$idCuadricula ?? 'documentos'" :items="$itemsDocumentos" :label="__('proveedores.documentos.titulo')" />
        </div>
    @else
        <div class="ag-form-section__field--full">
            <ul class="ag-lista-estados">
                @foreach ($documentos as $documento)
                    <li>
                        <span>{{ __('proveedores.documentos.tipo.'.$documento->tipo) }}</span>
                        <x-atoms.badge :variant="['en_revision' => 'warning', 'aprobado' => 'success', 'rechazado' => 'danger', 'vencido' => 'danger'][$documento->estado] ?? 'neutral'">{{ __('proveedores.documentos.estado.'.$documento->estado) }}</x-atoms.badge>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</x-molecules.form-section>
