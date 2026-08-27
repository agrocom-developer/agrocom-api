{{--
    Template: auth-layout (docs/diseno/sistema_diseno_panel.md §4.9)
    "Layout de autenticación" (ADR 0002 punto 1): logo + tarjeta centrada +
    slot de contenido, compartido por login y selección de rol.

    Decisión de composición (no estaba cerrada en el catálogo): split-screen
    con la foto real `public/images/drone-hero.jpg` en vez de la tarjeta
    centrada sobre fondo liso — es explícitamente lo que HU-02 pidió para
    dejar de verse "plano". Refinamiento editorial (segunda vuelta, a pedido
    del usuario sobre un mockup de referencia — dirección "1a: editorial
    cálido"): wordmark arriba y headline grande en serif abajo, ambos
    montados sobre la foto con un scrim degradado para legibilidad. Sin
    blobs/gradientes decorativos de color: la profundidad la da la foto, el
    scrim (solo negro a distinta opacidad, nunca un color de marca) y
    `--ag-shadow-lg` en el panel del formulario. Colapsa a imagen-arriba/
    formulario-abajo en tablet y a solo-formulario en mobile (ahí no se
    renderiza wordmark/headline — la foto es decorativa, no compite con el
    formulario en pantallas chicas).

    El wordmark NO usa el átomo `logo` (las imágenes `logo-light.jpeg`/
    `logo-dark.jpeg` traen fondo sólido horneado — ver limitación documentada
    en logo.css; sobre una foto, cualquiera de las dos mostraría un recuadro
    blanco/negro visible). Se resuelve como texto en `--ag-font-family-display`
    reutilizando `ui.logo.alt` — sin duplicar el logo de `login-form` en
    desktop (ese sigue viviendo en la tarjeta, es el único que se ve en
    mobile).

    El color del wordmark/headline y el scrim son tokens CONSTANTES entre
    temas (`--ag-color-text-on-scrim`, `--ag-color-scrim*`) — la foto no
    reasigna por tema, así que lo que va montado sobre ella tampoco. Ver
    tokens/semantic/theme-{light,dark}.css y la verificación de contraste en
    sistema_diseno_panel.md §1.3.

    Incluye su propio `theme-toggle` arriba a la derecha (login y selección
    de rol no tienen sidebar/topbar todavía).

    Slot (default): el organism de esa pantalla (`login-form`, o la lista de
    `role-selector-item` + botón continuar que arme `frontend`).
--}}
<div class="ag-auth-layout">
    <div class="ag-auth-layout__visual">
        <img src="{{ asset('images/drone-hero.jpg') }}" alt="" aria-hidden="true" class="ag-auth-layout__image">
        <div class="ag-auth-layout__scrim" aria-hidden="true"></div>

        <div class="ag-auth-layout__editorial">
            <p class="ag-auth-layout__wordmark">{{ __('ui.logo.alt') }}</p>
            <p class="ag-auth-layout__headline">{{ __('seguridad.auth.headline') }}</p>
        </div>
    </div>

    <div class="ag-auth-layout__panel">
        <div class="ag-auth-layout__topbar">
            <x-molecules.theme-toggle />
        </div>

        <div class="ag-auth-layout__content">
            {{ $slot }}
        </div>
    </div>
</div>
