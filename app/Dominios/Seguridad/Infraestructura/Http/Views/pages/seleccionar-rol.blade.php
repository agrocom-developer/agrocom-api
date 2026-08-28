{{--
    Page: seleccionar-rol (GET /panel/seleccionar-rol, panel.rol-activo.selector)
    Estructura: layout HTML + auth-layout + selector de rol + JS de wiring.

    Datos esperados (ver RolActivoController::create()):
    - roles (Collection<SecRole>): opciones vivas del usuario. Puede llegar
      vacía (usuario sin ningún rol asignado) — se maneja como estado propio,
      no como error.
    - accionActualizar (string): URL de POST /panel/rol-activo, expuesta como
      data-attribute para que el JS la ejecute.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Agrocom') }} — Seleccionar rol</title>

    @vite('resources/css/app.css')
</head>
<body>
    <x-templates.auth-layout>
    <section class="ag-role-selector" data-ag-rol-activo-accion="{{ $accionActualizar }}">
        <h1>{{ __('seguridad.rol.seleccion_titulo') }}</h1>
        <p>{{ __('seguridad.rol.seleccion_subtitulo') }}</p>

        <div class="ag-role-list">
            @forelse ($roles as $rol)
                <x-molecules.role-selector-item
                    variant="pick"
                    :id="$rol->id"
                    :label="$rol->name"
                    :description="$rol->description"
                />
            @empty
                <p>{{ __('seguridad.rol.seleccion_vacia') }}</p>
            @endforelse
        </div>

        @if ($roles->isNotEmpty())
            <x-atoms.button type="button" variant="primary" :block="true" disabled data-ag-rol-activo-continuar>
                {{ __('seguridad.rol.seleccion_boton_continuar') }}
            </x-atoms.button>
        @endif
    </section>
    </x-templates.auth-layout>

    <script>
        /**
         * Selector de rol inicial (tras login con 2+ roles).
         *
         * Los botones de role-selector-item pueden seleccionarse (aria-pressed).
         * El botón de continuar se habilita cuando hay al menos uno seleccionado.
         * Al hacer click en continuar, postea el rol seleccionado a POST /panel/rol-activo.
         */
        document.addEventListener('DOMContentLoaded', () => {
            const section = document.querySelector('[data-ag-rol-activo-accion]');
            if (!section) return;

            const accion = section.getAttribute('data-ag-rol-activo-accion');
            const botones = section.querySelectorAll('[data-ag-role-id]');
            const btnContinuar = section.querySelector('[data-ag-rol-activo-continuar]');
            let rolSeleccionado = null;

            // Sin roles asignados (ver el bloque forelse/empty arriba): ni
            // los botones de rol ni el botón "Continuar" existen en el DOM —
            // no hay nada que wirear.
            if (!btnContinuar) return;

            // Click en un botón de rol: toggle aria-pressed
            botones.forEach((btn) => {
                btn.addEventListener('click', () => {
                    // Deseleccionar todos
                    botones.forEach((b) => b.setAttribute('aria-pressed', 'false'));
                    // Seleccionar este
                    btn.setAttribute('aria-pressed', 'true');
                    rolSeleccionado = btn.getAttribute('data-ag-role-id');

                    // Habilitar el botón de continuar
                    btnContinuar.disabled = false;
                });
            });

            // Click en continuar: POST el rol seleccionado
            btnContinuar.addEventListener('click', async () => {
                if (!rolSeleccionado) return;

                btnContinuar.disabled = true;

                try {
                    const response = await fetch(accion, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ id_role: parseInt(rolSeleccionado) }),
                    });

                    if (response.ok) {
                        // Redirigir al dashboard
                        window.location.href = '/panel/dashboard';
                    } else {
                        // Rehabilitar e intentar nuevamente
                        btnContinuar.disabled = false;
                        alert('Error al cambiar de rol. Intenta nuevamente.');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    btnContinuar.disabled = false;
                    alert('Error de red. Intenta nuevamente.');
                }
            });
        });
    </script>

    @vite('resources/js/app.js')
</body>
</html>

