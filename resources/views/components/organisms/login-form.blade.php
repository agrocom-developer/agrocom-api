{{--
    Organism: login-form (docs/diseno/sistema_diseno_panel.md §4.5)
    Estructura: dos tabs (ingreso / recuperar acceso) con panel de formulario
    + slot de error general.

    Tab "Ingreso": formulario real de autenticación con username/password en
    inputs de línea, checkbox "Recordarme", link "¿Olvidaste tu contraseña?"
    que cambia de tab, botón submit. NO implementa la autenticación (eso es
    frontend+backend, ADR 0004) — ni siquiera intercepta el submit: el POST
    real a `/login` responde JSON (App\Dominios\Seguridad\Infraestructura\Http\
    Controllers\Web\SesionController::store), así que quien conecte este
    organism a datos reales necesariamente lo hace vía fetch/Livewire, no un
    submit clásico con redirect — este componente solo deja el `<form>` con
    los nombres de campo correctos (`username`/`password`, los que espera
    IniciarSesionRequest) para que ese wiring no tenga que inventar nada.

    Tab "Recuperar acceso": panel presentacional SIN form (es fuera de alcance
    de esta tarea, el backend no existe todavía), solo input de correo
    electrónico y botón "Enviar solicitud" de tipo `button`. Link "Volver al
    ingreso" cambia de tab. El selector
    `document.querySelector('[data-ag-login-form] form')` de
    resources/js/pages/login.js sigue resolviendo a UN ÚNICO form (el de
    login) porque no hay `<form>` en este panel — el input+botón de este
    panel se envuelven en un `<div class="ag-login-form__form">` (NO un
    `<form>`: es la misma clase que usa el `<form>` de ingreso, reutilizada
    solo por su CSS de alineación/ancho/gap, cuarta vuelta — antes este panel
    no tenía ningún wrapper con `text-align: left`, por eso el label del
    input quedaba centrado por el `text-align: center` heredado del panel).

    Props:
    - action (requerido): URL del POST.
    - method (default "POST").
    - csrf (nullable): si se pasa, agrega el input oculto `_token`.
    - usernameValue (nullable): valor a repoblar tras un submit fallido.
    - usernameError / passwordError (nullable): error específico de ese
      campo, ya traducido por el llamador.
    - submitting (bool, default false): estado visual del botón (spinner +
      disabled, vía el átomo `button`) — quien conecte el fetch/Livewire
      decide cuándo vale true.

    Slot (default): mensaje de error GENERAL (credenciales inválidas), ya
    traducido por el llamador. Vacío = no se renderiza.

    JS: resources/js/organisms/login-form.js maneja los tabs, roving tabindex,
    keyboard navigation (ArrowRight/ArrowLeft en los tabs cambia/activa el
    otro), y clicks en los links de cambio de tab.
--}}
@props([
    'action',
    'method' => 'POST',
    'csrf' => null,
    'usernameValue' => null,
    'usernameError' => null,
    'passwordError' => null,
    'submitting' => false,
])

<div {{ $attributes->class(['ag-login-form']) }}>
    {{-- Tabs --}}
    <div role="tablist" class="ag-login-form__tabs" aria-label="{{ __('seguridad.login.tabs_aria_label') }}">
        <button
            role="tab"
            id="tab-ingreso"
            aria-controls="panel-ingreso"
            aria-selected="true"
            tabindex="0"
            class="ag-login-form__tab"
        >
            {{ __('seguridad.login.tab_ingreso') }}
        </button>
        <button
            role="tab"
            id="tab-recuperar"
            aria-controls="panel-recuperar"
            aria-selected="false"
            tabindex="-1"
            class="ag-login-form__tab"
        >
            {{ __('seguridad.login.tab_recuperar') }}
        </button>
    </div>

    {{-- Panel Ingreso --}}
    <div
        role="tabpanel"
        id="panel-ingreso"
        aria-labelledby="tab-ingreso"
        data-ag-login-panel="ingreso"
        class="ag-login-form__panel"
    >
        <div class="ag-login-form__header">
            <h1 class="ag-login-form__title">{{ __('seguridad.login.titulo') }}</h1>
            <p class="ag-login-form__subtitle">{{ __('seguridad.login.subtitulo') }}</p>
        </div>

        @if ($slot->isNotEmpty())
            <div class="ag-login-form__error" role="alert">
                <x-atoms.icon name="error" size="sm" />
                <span>{{ $slot }}</span>
            </div>
        @endif

        <form class="ag-login-form__form" method="{{ $method }}" action="{{ $action }}">
            @if ($csrf)
                <input type="hidden" name="_token" value="{{ $csrf }}">
            @endif

            <x-atoms.input
                variant="line"
                type="text"
                name="username"
                :label="__('seguridad.login.campo_usuario')"
                icon="person"
                autocomplete="username"
                :value="$usernameValue"
                :error="$usernameError"
                :required="true"
            />

            <x-atoms.input
                variant="line"
                type="password"
                name="password"
                :label="__('seguridad.login.campo_password')"
                icon="lock"
                autocomplete="current-password"
                :error="$passwordError"
                :required="true"
            />

            <div class="ag-login-form__actions">
                <div class="ag-login-form__checkbox-group">
                    <input
                        type="checkbox"
                        name="remember"
                        id="remember"
                        class="ag-login-form__checkbox"
                    >
                    <label for="remember" class="ag-login-form__checkbox-label">
                        {{ __('seguridad.login.recordarme') }}
                    </label>
                </div>

                <button
                    type="button"
                    class="ag-login-form__link"
                    data-ag-login-switch-tab="recuperar"
                >
                    {{ __('seguridad.login.olvido_password') }}
                </button>
            </div>

            <x-atoms.button
                type="submit"
                variant="primary"
                size="lg"
                :block="true"
                icon="arrow_forward"
                iconPosition="end"
                :loading="$submitting"
            >
                {{ $submitting ? __('seguridad.login.boton_ingresando') : __('seguridad.login.boton_ingresar') }}
            </x-atoms.button>
        </form>
    </div>

    {{-- Panel Recuperar Acceso --}}
    <div
        role="tabpanel"
        id="panel-recuperar"
        aria-labelledby="tab-recuperar"
        data-ag-login-panel="recuperar"
        class="ag-login-form__panel"
        hidden
    >
        <div class="ag-login-form__header">
            <h1 class="ag-login-form__title">{{ __('seguridad.recuperar.titulo') }}</h1>
            <p class="ag-login-form__subtitle">{{ __('seguridad.recuperar.subtitulo') }}</p>
        </div>

        <div class="ag-login-form__form">
            <x-atoms.input
                variant="line"
                type="email"
                name="email_recuperar"
                :label="__('seguridad.recuperar.campo_email')"
                icon="mail"
                autocomplete="email"
                :required="true"
            />

            <x-atoms.button
                type="button"
                variant="accent"
                size="lg"
                :block="true"
                icon="send"
                iconPosition="end"
            >
                {{ __('seguridad.recuperar.boton_enviar') }}
            </x-atoms.button>
        </div>

        <button
            type="button"
            class="ag-login-form__link-back"
            data-ag-login-switch-tab="ingreso"
        >
            {{ __('seguridad.recuperar.volver') }}
        </button>
    </div>

    {{-- Línea de sin alta pública --}}
    <p class="ag-login-form__sin-alta">{{ __('seguridad.auth.sin_alta_publica') }}</p>
</div>
