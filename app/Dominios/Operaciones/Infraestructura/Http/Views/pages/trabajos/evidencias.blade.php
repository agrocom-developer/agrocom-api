{{--
    Page: trabajos/evidencias (GET /panel/trabajos/{trabajo}/evidencias, panel.trabajos.evidencias)
    Galería de evidencias de un trabajo (HU-42, tarea 56): imagen de campo,
    firma del acta y fotos de incidencia por sesión — TODAS las evidencias
    que existen hoy para un trabajo (`ope_evidencias`), agrupadas para
    revisar sin abrir la base. Sin `captura_rc` a nivel de sesión (recorte
    de otra tarea, fuera de alcance).

    Datos esperados (ver TrabajosController::evidencias()): la cáscara de
    CascaraPanel, más:
    - $trabajo (Trabajo, con `imagenCampoEvidencia`, `acta.evidenciaFirma` y
      `sesiones.incidencias.evidenciaFoto` precargadas).

    Solo lectura, gateada por `operaciones.trabajo.ver` — mismo permiso que
    el detalle del trabajo, verificado server-side en el controlador.

    `Evidencia::archivo_url` es una ruta privada del disco `r2`, nunca una
    URL pública: cada miniatura/descarga apunta a
    `panel.evidencias.archivo`, que hace streaming (mismo criterio que
    `panel.trabajos.acta-pdf`/`panel.trabajos.reporte-pdf`).

    Estilos en resources/css/pages/trabajos.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('operaciones.trabajos.evidencias_titulo', ['id' => $trabajo->id])" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campana="$campana"
        :periodo="$periodo"
        :version="$version"
    >
        <x-atoms.button href="{{ route('panel.trabajos.show', $trabajo) }}" variant="text" size="sm" icon="arrow_back">
            {{ __('operaciones.trabajos.volver_al_detalle') }}
        </x-atoms.button>

        <h1>{{ __('operaciones.trabajos.evidencias_titulo', ['id' => $trabajo->id]) }}</h1>

        <x-molecules.section-head :title="__('operaciones.trabajos.evidencias_imagen_campo_titulo')" class="ag-trabajo-detalle__seccion" />

        @if ($trabajo->imagenCampoEvidencia === null)
            <x-molecules.alert-strip variant="info" icon="photo_library" class="ag-trabajos__aviso">
                {{ __('operaciones.trabajos.evidencias_imagen_campo_vacio') }}
            </x-molecules.alert-strip>
        @else
            <div class="ag-galeria-evidencias__grid">
                <div class="ag-galeria-evidencias__item">
                    <a href="{{ route('panel.evidencias.archivo', $trabajo->imagenCampoEvidencia) }}" target="_blank" rel="noopener">
                        <img
                            src="{{ route('panel.evidencias.archivo', $trabajo->imagenCampoEvidencia) }}"
                            alt="{{ __('operaciones.trabajos.evidencias_imagen_campo_titulo') }}"
                            class="ag-galeria-evidencias__miniatura"
                            loading="lazy"
                        >
                    </a>
                    <x-atoms.button href="{{ route('panel.evidencias.archivo', $trabajo->imagenCampoEvidencia) }}" variant="outline" size="sm" icon="download">
                        {{ __('operaciones.trabajos.evidencias_descargar') }}
                    </x-atoms.button>
                </div>
            </div>
        @endif

        <x-molecules.section-head :title="__('operaciones.trabajos.evidencias_firma_acta_titulo')" class="ag-trabajo-detalle__seccion" />

        @if ($trabajo->acta?->evidenciaFirma === null)
            <x-molecules.alert-strip variant="info" icon="description" class="ag-trabajos__aviso">
                {{ __('operaciones.trabajos.evidencias_firma_acta_vacio') }}
            </x-molecules.alert-strip>
        @else
            <div class="ag-galeria-evidencias__grid">
                <div class="ag-galeria-evidencias__item">
                    <a href="{{ route('panel.evidencias.archivo', $trabajo->acta->evidenciaFirma) }}" target="_blank" rel="noopener">
                        <img
                            src="{{ route('panel.evidencias.archivo', $trabajo->acta->evidenciaFirma) }}"
                            alt="{{ __('operaciones.trabajos.evidencias_firma_acta_titulo') }}"
                            class="ag-galeria-evidencias__miniatura"
                            loading="lazy"
                        >
                    </a>
                    <x-atoms.button href="{{ route('panel.evidencias.archivo', $trabajo->acta->evidenciaFirma) }}" variant="outline" size="sm" icon="download">
                        {{ __('operaciones.trabajos.evidencias_descargar') }}
                    </x-atoms.button>
                </div>
            </div>
        @endif

        <x-molecules.section-head :title="__('operaciones.trabajos.evidencias_incidencias_titulo')" class="ag-trabajo-detalle__seccion" />

        @if ($trabajo->sesiones->flatMap->incidencias->isEmpty())
            <x-molecules.alert-strip variant="info" icon="report" class="ag-trabajos__aviso">
                {{ __('operaciones.trabajos.evidencias_incidencias_vacio') }}
            </x-molecules.alert-strip>
        @else
            @foreach ($trabajo->sesiones as $sesion)
                @continue ($sesion->incidencias->isEmpty())

                <h3 class="ag-galeria-evidencias__sesion-titulo">
                    {{ __('operaciones.trabajos.evidencias_sesion_titulo', ['secuencia' => $sesion->secuencia]) }}
                </h3>

                <div class="ag-galeria-evidencias__grid">
                    @foreach ($sesion->incidencias as $incidencia)
                        @continue ($incidencia->evidenciaFoto === null)

                        <div class="ag-galeria-evidencias__item">
                            <a href="{{ route('panel.evidencias.archivo', $incidencia->evidenciaFoto) }}" target="_blank" rel="noopener">
                                <img
                                    src="{{ route('panel.evidencias.archivo', $incidencia->evidenciaFoto) }}"
                                    alt="{{ __("operaciones.trabajos.incidencia_tipo.{$incidencia->tipo->value}") }}"
                                    class="ag-galeria-evidencias__miniatura"
                                    loading="lazy"
                                >
                            </a>
                            <span class="ag-galeria-evidencias__etiqueta">
                                {{ __("operaciones.trabajos.incidencia_tipo.{$incidencia->tipo->value}") }}
                            </span>
                            <x-atoms.button href="{{ route('panel.evidencias.archivo', $incidencia->evidenciaFoto) }}" variant="outline" size="sm" icon="download">
                                {{ __('operaciones.trabajos.evidencias_descargar') }}
                            </x-atoms.button>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif
    </x-templates.panel-layout>
</x-templates.panel-shell>
