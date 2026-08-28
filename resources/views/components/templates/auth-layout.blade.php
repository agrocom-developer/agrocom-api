{{--
    Template: auth-layout (docs/diseno/sistema_diseno_panel.md §4.9)
    "Layout de autenticación" (ADR 0002 punto 1): logo + tarjeta centrada +
    slot de contenido, compartido por login y selección de rol.

    Decisión de composición (no estaba cerrada en el catálogo): split-screen
    con foto real en vez de la tarjeta centrada sobre fondo liso — es
    explícitamente lo que HU-02 pidió para dejar de verse "plano".
    Refinamiento editorial (tercera vuelta, rediseño visual a partir de
    mockup de referencia aprobado): wordmark arriba en dos tonos (AGRO
    blanco + COM ámbar) con chip verde a la derecha, headline grande en
    serif abajo, ambos montados sobre la foto con un scrim degradado para
    legibilidad y una regla de gradiente que marca la transición visual. Sin
    blobs/gradientes decorativos de color: la profundidad la da la foto, el
    scrim (solo negro a distinta opacidad, nunca un color de marca) y la
    estructura nítida del panel del formulario (ya sin `box-shadow` ni
    elevación, con fondo propio `--ag-color-bg-auth` tintado de marca).

    Galería de 3 imágenes (cuarta vuelta, ver `resources/js/templates/
    auth-layout.js` y `resources/css/components/auth-layout.css`): la foto
    única del pase anterior se convierte en 3 slides con crossfade
    (`public/images/drone-hero.jpg`, `-2.jpg`, `-3.jpg`, misma
    fotógrafa/serie, ver `public/images/CREDITS.md`), cada uno con su propio
    headline/subheadline (`lang/es/seguridad.php` → `seguridad.auth.galeria`,
    array de `{headline, subheadline}` por índice — los nombres de archivo de
    imagen NO viven en el lang file, son datos de presentación que arma este
    Blade, no copy). El wordmark y el chip de tagline (`.ag-auth-layout__top`)
    quedan FIJOS, fuera del loop de slides — no cambian por imagen. Auto-avance
    cada 6s con indicadores (dots) para navegación manual; bajo
    `prefers-reduced-motion: reduce` el auto-avance se apaga (JS), la
    navegación manual sigue funcionando. Primera imagen con `loading="eager"`
    (es la que se ve al entrar), las otras dos con `loading="lazy"`.

    Split-screen: 55% foto (desktop ≥992px) / 45% panel de formulario, sin
    cap de `max-width` sobre la foto para mantener el ratio flexible en
    cualquier ancho de escritorio. Colapsa a imagen-arriba/formulario-abajo
    (~240px de altura de imagen) en tablet, y a solo-formulario en mobile
    (ahí la foto es decorativa en segundo plano, no compite con el
    formulario en pantallas chicas).

    El wordmark NO usa el átomo `logo` (aunque `public/logo.png` ya es
    transparente — la limitación de fondo horneado que forzaba esto antes
    está resuelta, ver logo.css): sobre la foto el mockup pide el tratamiento
    tipográfico bicromía "AGRO"/"COM" en `--ag-font-family-display`, no el
    isotipo cuadrado del logo — es una decisión de diseño, no una limitación
    técnica. Se resuelve como texto tipográfico con `--ag-font-family-display`,
    partiendo "AGRO" (blanco) de "COM" (ámbar) para marcar el branding de
    marca. El color del wordmark, el scrim y el headline son tokens CONSTANTES
    entre temas (`--ag-color-text-on-scrim`, `--ag-color-scrim*`,
    `--ag-color-wordmark-accent`, `--ag-color-chip-icon`) — la foto no
    reasigna por tema, así que lo que va montado sobre ella tampoco. Ver
    tokens/semantic/theme-{light,dark}.css y la verificación de contraste en
    sistema_diseno_panel.md §1.3.

    Header/footer (logo + theme-toggle arriba, copyright + versión abajo)
    se renderiza en el template (no en `login-form`) para que
    `seleccionar-rol.blade.php` los herede gratis y manteng coherencia entre
    ambas pantallas (nunca duplicar markup, ADR 0008).

    Slot (default): el organism de esa pantalla (`login-form`, o la lista de
    `role-selector-item` + botón continuar que arme `frontend`).
--}}
{{--
    Props (quinta vuelta — la pantalla de selección de rol, maqueta 5c,
    reutiliza este template con copy PROPIO y fijo):
    - headline / subheadline (nullable string): si se pasan, TODOS los
      slides muestran ese copy fijo (el crossfade solo cambia la foto) en
      vez del copy por slide de `seguridad.auth.galeria`.
    Slot opcional `headerEnd`: contenido extra a la derecha del header del
    panel (p. ej. "Cerrar sesión" en la selección de rol).
--}}
@props([
    'headline' => null,
    'subheadline' => null,
])

@php
    // Datos de la galería: nombres de archivo (presentación, propiedad de
    // este Blade) + copy por slide (lang/es/seguridad.php, propiedad del
    // idioma — ADR 0013). No se mezclan en el lang file porque un nombre de
    // archivo de imagen no es texto traducible.
    $galeriaCopy = __('seguridad.auth.galeria');
    $galeriaImagenes = ['drone-hero.jpg', 'drone-hero-2.jpg', 'drone-hero-3.jpg'];
    $galeria = collect($galeriaImagenes)->values()->map(fn ($imagen, $indice) => [
        'imagen' => $imagen,
        'headline' => $headline ?? $galeriaCopy[$indice]['headline'],
        'subheadline' => $subheadline ?? $galeriaCopy[$indice]['subheadline'],
    ]);
@endphp

<div class="ag-auth-layout">
    <div class="ag-auth-layout__visual" data-ag-auth-gallery>
        <div class="ag-auth-layout__slides">
            @foreach ($galeria as $indice => $slide)
                <div
                    class="ag-auth-layout__slide{{ $indice === 0 ? ' is-active' : '' }}"
                    data-ag-auth-slide
                    aria-hidden="{{ $indice === 0 ? 'false' : 'true' }}"
                >
                    <img
                        src="{{ asset('images/'.$slide['imagen']) }}"
                        alt=""
                        aria-hidden="true"
                        class="ag-auth-layout__image"
                        loading="{{ $indice === 0 ? 'eager' : 'lazy' }}"
                    >
                    <div class="ag-auth-layout__scrim" aria-hidden="true"></div>

                    <div class="ag-auth-layout__bottom">
                        <div class="ag-auth-layout__divider" aria-hidden="true"></div>
                        <h1 class="ag-auth-layout__headline">{{ $slide['headline'] }}</h1>
                        <p class="ag-auth-layout__subheadline">{{ $slide['subheadline'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Wordmark/chip: fijos, no cambian por slide. --}}
        <div class="ag-auth-layout__top">
            <div class="ag-auth-layout__wordmark">
                <span class="ag-auth-layout__wordmark-agro">AGRO</span><span class="ag-auth-layout__wordmark-com">COM</span>
            </div>

            <div class="ag-auth-layout__chip">
                <x-atoms.icon name="flight_takeoff" size="sm" />
                <span class="ag-auth-layout__chip-text">{{ __('seguridad.auth.tagline') }}</span>
            </div>
        </div>

        <div class="ag-auth-layout__dots" role="group" aria-label="{{ __('seguridad.auth.galeria_aria_label') }}">
            @foreach ($galeria as $indice => $slide)
                <button
                    type="button"
                    class="ag-auth-layout__dot{{ $indice === 0 ? ' is-active' : '' }}"
                    data-ag-auth-dot
                    aria-current="{{ $indice === 0 ? 'true' : 'false' }}"
                    aria-label="{{ __('seguridad.auth.galeria_dot', ['numero' => $indice + 1, 'total' => $galeria->count()]) }}"
                ></button>
            @endforeach
        </div>
    </div>

    <div class="ag-auth-layout__panel">
        <div class="ag-auth-layout__header">
            <x-atoms.logo size="md" />
            <span class="ag-auth-layout__header-end">
                <x-molecules.theme-toggle />
                @isset($headerEnd)
                    {{ $headerEnd }}
                @endisset
            </span>
        </div>

        <div class="ag-auth-layout__content">
            {{ $slot }}
        </div>

        <div class="ag-auth-layout__footer">
            <span>{{ __('ui.footer.copyright', ['year' => date('Y')]) }}</span>
            <span class="ag-auth-layout__version">v1.0</span>
        </div>
    </div>
</div>
