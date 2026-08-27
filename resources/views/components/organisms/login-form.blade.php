{{--
    Organism: login-form (docs/diseno/sistema_diseno_panel.md §4.5)
    Composición: logo + dos input (usuario, contraseña) + button (submit) +
    slot de error general. NO implementa la autenticación (eso es
    frontend+backend, ADR 0004) — ni siquiera intercepta el submit: el POST
    real a `/login` responde JSON (App\Dominios\Seguridad\Infraestructura\Http\
    Controllers\Web\SesionController::store), así que quien conecte este
    organism a datos reales necesariamente lo hace vía fetch/Livewire, no un
    submit clásico con redirect — este componente solo deja el `<form>` con
    los nombres de campo correctos (`username`/`password`, los que espera
    IniciarSesionRequest) para que ese wiring no tenga que inventar nada.

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
    <x-atoms.logo size="lg" class="ag-login-form__logo" />

    <h1 class="ag-login-form__title">{{ __('seguridad.login.titulo') }}</h1>
    <p class="ag-login-form__subtitle">{{ __('seguridad.login.subtitulo') }}</p>

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
            type="password"
            name="password"
            :label="__('seguridad.login.campo_password')"
            icon="lock"
            autocomplete="current-password"
            :error="$passwordError"
            :required="true"
        />

        <x-atoms.button
            type="submit"
            variant="primary"
            :block="true"
            :loading="$submitting"
        >
            {{ $submitting ? __('seguridad.login.boton_ingresando') : __('seguridad.login.boton_ingresar') }}
        </x-atoms.button>
    </form>
</div>
