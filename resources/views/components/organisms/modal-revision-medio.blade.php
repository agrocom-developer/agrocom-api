{{--
    Organism: modal-revision-medio (`.ag-modal-medio`)
    El único lugar del panel donde se revisa una imagen o un documento: muestra el contenido COMPLETO (imagen
    ajustada con zoom simple, PDF embebido), sus datos (tipo, estado, fecha, peso, formato) y las acciones
    Aprobar, Rechazar (el motivo es obligatorio) y Descargar original. Los botones Anterior y Siguiente recorren
    los archivos de la misma ficha sin cerrar. La lógica vive en `resources/js/organisms/modal-revision-medio.js`.

    Cada acción llega ya decidida en el ítem (`puede_aprobar`, `puede_rechazar`, `url_descarga`): la vista no
    consulta permisos. El enlace de descarga solo existe si el ítem trae `medio.url_descarga`. Si el archivo no
    existe (`medio.existe = false`), el modal lo dice y bloquea Aprobar. Al enviar, la página se recarga y el
    modal vuelve a abrirse en el siguiente pendiente.

    Usa el modal de Bootstrap: trampa de foco, Esc para cerrar y `aria-modal` salen de ahí. Abrirlo: cualquier
    elemento con `data-medio-modal="{id}"` y `data-medio-clave="{clave del ítem}"`.

    Props:
    - id (requerido): id del modal.
    - items (requerido): lista de `ItemDeRevisionDeMedio::aArray()`.
--}}
@props([
    'id',
    'items' => [],
])

@php
    $tituloId = $id.'-titulo';
    $textos = [
        'abrir' => __('revision_medios.modal.abrir'),
        'ampliar' => __('revision_medios.modal.ampliar'),
        'reducir' => __('revision_medios.modal.reducir'),
        'imagen_alt' => __('revision_medios.modal.imagen_alt'),
        'pdf_titulo' => __('revision_medios.modal.pdf_titulo'),
        'sin_vista_previa' => __('revision_medios.modal.sin_vista_previa'),
        'motivo_anterior' => __('revision_medios.modal.motivo_anterior'),
        'posicion' => __('revision_medios.modal.posicion'),
        'si' => __('revision_medios.modal.si'),
        'no' => __('revision_medios.modal.no'),
        'sin_dato' => __('proveedores.sin_dato'),
        'tipos' => ['imagen' => __('revision_medios.imagen'), 'pdf' => __('revision_medios.pdf'), 'otro' => __('revision_medios.otro')],
    ];
@endphp

<div
    class="modal fade ag-modal-medio"
    id="{{ $id }}"
    tabindex="-1"
    aria-hidden="true"
    aria-labelledby="{{ $tituloId }}"
    data-ag-modal-medio
>
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content ag-modal-medio__contenido">
            <div class="ag-modal-medio__cabecera">
                <h2 class="ag-modal-medio__titulo" id="{{ $tituloId }}" data-medio="titulo"></h2>
                <span class="ag-badge ag-badge--neutral" data-medio="estado"><span class="ag-badge__label"></span></span>
                <button type="button" class="ag-modal-medio__cerrar" data-bs-dismiss="modal" aria-label="{{ __('revision_medios.modal.cerrar') }}">
                    <x-atoms.icon name="close" />
                </button>
            </div>

            <div class="ag-modal-medio__cuerpo">
                <section class="ag-modal-medio__visor-zona" aria-label="{{ __('revision_medios.modal.visor') }}">
                    <div class="ag-modal-medio__visor" data-medio="visor" tabindex="0"></div>
                    <div class="ag-modal-medio__visor-acciones">
                        <button type="button" class="ag-modal-medio__enlace" data-medio="zoom" hidden aria-pressed="false">
                            <x-atoms.icon name="zoom_in" size="sm" /><span data-medio="zoom-texto">{{ __('revision_medios.modal.ampliar') }}</span>
                        </button>
                        <a class="ag-modal-medio__enlace" data-medio="abrir" href="#" target="_blank" rel="noopener" hidden>
                            <x-atoms.icon name="open_in_new" size="sm" />{{ __('revision_medios.modal.abrir') }}
                        </a>
                    </div>
                </section>

                <aside class="ag-modal-medio__panel">
                    <div class="ag-modal-medio__aviso ag-modal-medio__aviso--peligro" role="alert" data-medio="no-existe" hidden>
                        <x-atoms.icon name="error" size="sm" />
                        <span>{{ __('revision_medios.modal.no_disponible') }}</span>
                    </div>
                    <p class="ag-modal-medio__aviso" data-medio="motivo" hidden></p>

                    <h3 class="ag-modal-medio__subtitulo">{{ __('revision_medios.modal.datos') }}</h3>
                    <dl class="ag-modal-medio__datos">
                        <div data-medio-fila="origen" hidden><dt>{{ __('revision_medios.modal.dato_origen') }}</dt><dd data-medio="origen"></dd></div>
                        <div><dt>{{ __('revision_medios.modal.dato_tipo') }}</dt><dd data-medio="tipo"></dd></div>
                        <div><dt>{{ __('revision_medios.modal.dato_estado') }}</dt><dd data-medio="estado-texto"></dd></div>
                        <div><dt>{{ __('revision_medios.modal.dato_fecha') }}</dt><dd data-medio="fecha"></dd></div>
                        <div><dt>{{ __('revision_medios.modal.dato_peso') }}</dt><dd data-medio="peso"></dd></div>
                        <div><dt>{{ __('revision_medios.modal.dato_formato') }}</dt><dd data-medio="mime"></dd></div>
                        <div><dt>{{ __('revision_medios.modal.dato_obligatoria') }}</dt><dd data-medio="obligatoria"></dd></div>
                    </dl>

                    <a class="ag-modal-medio__enlace" data-medio="ficha" href="#" hidden>
                        <x-atoms.icon name="arrow_outward" size="sm" />{{ __('revision_medios.modal.ver_ficha') }}
                    </a>

                    <form method="POST" action="#" class="ag-modal-medio__form" data-medio="form" hidden>
                        @csrf
                        <div class="ag-modal-medio__acciones" data-medio="acciones">
                            <x-atoms.button type="submit" variant="primary" icon="check" name="decision" value="aprobar" data-medio="aprobar">{{ __('revision_medios.modal.aprobar') }}</x-atoms.button>
                            <x-atoms.button type="button" variant="alert-outline" icon="close" data-medio="rechazar">{{ __('revision_medios.modal.rechazar') }}</x-atoms.button>
                        </div>
                        <div class="ag-modal-medio__rechazo" data-medio="rechazo" hidden>
                            <x-atoms.textarea
                                name="motivo"
                                :id="$id.'-motivo'"
                                :label="__('revision_medios.modal.motivo_etiqueta')"
                                :help="__('revision_medios.modal.motivo_ayuda')"
                                :rows="3"
                                required
                                disabled
                            />
                            <div class="ag-modal-medio__acciones">
                                <x-atoms.button type="submit" variant="danger" icon="close" name="decision" value="rechazar">{{ __('revision_medios.modal.confirmar_rechazo') }}</x-atoms.button>
                                <x-atoms.button type="button" variant="outline" data-medio="cancelar-rechazo">{{ __('revision_medios.modal.cancelar_rechazo') }}</x-atoms.button>
                            </div>
                        </div>
                    </form>

                    <a class="ag-button ag-button--outline ag-button--md ag-modal-medio__descarga" data-medio="descargar" href="#" hidden>
                        <x-atoms.icon name="download" size="sm" class="ag-button__icon" />{{ __('revision_medios.modal.descargar') }}
                    </a>
                </aside>
            </div>

            <nav class="ag-modal-medio__pie" aria-label="{{ __('revision_medios.modal.recorrer') }}">
                <x-atoms.button type="button" variant="outline" icon="chevron_left" data-medio="anterior">{{ __('revision_medios.modal.anterior') }}</x-atoms.button>
                <span class="ag-modal-medio__posicion" data-medio="posicion" aria-live="polite"></span>
                <x-atoms.button type="button" variant="outline" icon="chevron_right" icon-position="end" data-medio="siguiente">{{ __('revision_medios.modal.siguiente') }}</x-atoms.button>
            </nav>
        </div>
    </div>

    <script type="application/json" data-medio="datos">@json(['items' => $items, 'textos' => $textos])</script>
</div>
