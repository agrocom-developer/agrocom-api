{{--
    Page: trabajos/evidencias (GET /panel/trabajos/{trabajo}/evidencias, panel.trabajos.evidencias)
    Galería de evidencias de un trabajo (HU-42, tarea 56): imagen de campo,
    capturas del control remoto por sesión, firma del acta y fotos de
    incidencia — TODAS las evidencias que existen para un trabajo
    (`ope_evidencias`), agrupadas para revisar sin abrir la base.

    La sección de capturas de RC estaba pendiente desde HU-42 («sin
    `captura_rc` a nivel de sesión, recorte de otra tarea»): faltaba el punto
    de enganche en el esquema. Existe desde
    `2026_09_07_100001_add_captura_rc_id_a_ope_sesiones_table.php`, que
    agregó `ope_sesiones.captura_rc_id` tal como lo pedía la espec §4.3 («la
    sesión se cierra con su propia captura de RC»).

    Datos esperados (ver TrabajosController::evidencias()): la cáscara de
    CascaraPanel, más:
    - $trabajo (Trabajo, con `imagenCampoEvidencia`, `acta.evidenciaFirma`,
      `sesiones.capturaRc` y `sesiones.incidencias.evidenciaFoto`
      precargadas).

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
        :zona-horaria="$zonaHoraria ?? null"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :version="$version"
    >
        <x-atoms.button :href="route('panel.trabajos.detalle', $trabajo)" variant="text" size="sm" icon="arrow_back">
            {{ __('operaciones.trabajos.volver_al_detalle') }}
        </x-atoms.button>

        <x-organisms.page-header :title="__('operaciones.trabajos.evidencias_titulo', ['id' => $trabajo->id])" />

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
                    <x-atoms.button :href="route('panel.evidencias.archivo', $trabajo->imagenCampoEvidencia)" variant="outline" size="sm" icon="download">
                        {{ __('operaciones.trabajos.evidencias_descargar') }}
                    </x-atoms.button>
                </div>
            </div>
        @endif

        <x-molecules.section-head :title="__('operaciones.trabajos.evidencias_capturas_rc_titulo')" class="ag-trabajo-detalle__seccion" />

        @if ($trabajo->sesiones->every(fn ($sesion) => $sesion->capturaRc === null))
            <x-molecules.alert-strip variant="info" icon="screenshot_monitor" class="ag-trabajos__aviso">
                {{ __('operaciones.trabajos.evidencias_capturas_rc_vacio') }}
            </x-molecules.alert-strip>
        @else
            <div class="ag-galeria-evidencias__grid">
                @foreach ($trabajo->sesiones as $sesion)
                    @continue ($sesion->capturaRc === null)

                    <div class="ag-galeria-evidencias__item">
                        <a href="{{ route('panel.evidencias.archivo', $sesion->capturaRc) }}" target="_blank" rel="noopener">
                            <img
                                src="{{ route('panel.evidencias.archivo', $sesion->capturaRc) }}"
                                alt="{{ __('operaciones.trabajos.evidencias_capturas_rc_alt', ['secuencia' => $sesion->secuencia]) }}"
                                class="ag-galeria-evidencias__miniatura"
                                loading="lazy"
                            >
                        </a>
                        <span class="ag-galeria-evidencias__etiqueta">
                            {{ __('operaciones.trabajos.evidencias_sesion_titulo', ['secuencia' => $sesion->secuencia]) }}
                            &middot;
                            {{ __('operaciones.trabajos.evidencias_capturas_rc_hectareas', ['hectareas' => $sesion->hectareas_declaradas]) }}
                        </span>
                        <x-atoms.button :href="route('panel.evidencias.archivo', $sesion->capturaRc)" variant="outline" size="sm" icon="download">
                            {{ __('operaciones.trabajos.evidencias_descargar') }}
                        </x-atoms.button>
                    </div>
                @endforeach
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
                    <x-atoms.button :href="route('panel.evidencias.archivo', $trabajo->acta->evidenciaFirma)" variant="outline" size="sm" icon="download">
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
                            <x-atoms.button :href="route('panel.evidencias.archivo', $incidencia->evidenciaFoto)" variant="outline" size="sm" icon="download">
                                {{ __('operaciones.trabajos.evidencias_descargar') }}
                            </x-atoms.button>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif
    </x-templates.panel-layout>
</x-templates.panel-shell>
