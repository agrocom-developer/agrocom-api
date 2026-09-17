/**
 * Login handler — intercepta el submit del login-form y lo convierte en un
 * fetch POST (JSON) a la URL de `form.action`. Sirve tanto al login del
 * panel interno (/login) como al del portal del cliente (/portal/login,
 * HU-41) — el mismo organism `login-form` se reutiliza para los dos (ADR
 * 0002 punto 6), así que este handler no asume guard. Maneja la respuesta:
 * - Si hay error de validación (422), muestra el mensaje de error en la UI.
 * - Si requiere selección de rol (solo el panel interno lo responde),
 *   redirige a /panel/seleccionar-rol.
 * - Si no, redirige a `data.destino` (primer ítem visible del menú del rol
 *   activo recién fijado, tarea 62 — nunca un `/panel/dashboard` fijo: un rol
 *   sin ese permiso aterrizaría en un 403); a falta de `destino` (el portal
 *   del cliente no lo responde), cae a `data-ag-login-redirect` del
 *   contenedor ([data-ag-login-form]) o a /panel/dashboard (el default
 *   histórico, para no romper la página de login del panel).
 *
 * Dos cosas que el fetch tiene que hacer a mano porque no son un submit
 * clásico (9/9/2026):
 * - Mandar la casilla "Recordarme" (`remember`) en el cuerpo: el backend la
 *   traduce a la cookie de sesión persistente de Laravel.
 * - Ofrecerle las credenciales al gestor de contraseñas del navegador
 *   (`navigator.credentials.store`). Con `preventDefault()` + `fetch` no hay
 *   submit que Chrome/Edge puedan observar, así que sin esto nunca aparece
 *   el "¿Guardar contraseña?" y el autocompletado del navegador queda vacío
 *   para siempre. La API es de Chromium (Safari/Firefox no la exponen): todo
 *   va detrás de un guard y su fallo nunca bloquea el ingreso.
 */

document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-ag-login-form] form');
    if (!form) return;

    const loginFormRoot = document.querySelector('[data-ag-login-form]');
    const redirectPorDefecto = loginFormRoot?.dataset.agLoginRedirect || '/panel/dashboard';
    const labelErrorCredenciales = loginFormRoot?.dataset.labelErrorCredenciales || '';
    const labelErrorGenerico = loginFormRoot?.dataset.labelErrorGenerico || '';

    // Detectar y fijar la zona horaria del navegador en el input oculto
    const zonaHorariaInput = form.querySelector('input[name="zona_horaria"]');
    if (zonaHorariaInput) {
        try {
            zonaHorariaInput.value = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
        } catch {
            // Navegador sin soporte de Intl — el campo queda vacío, el backend lo tolera
        }
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const usernameInput = form.querySelector('input[name="username"]');
        const passwordInput = form.querySelector('input[name="password"]');
        const rememberInput = form.querySelector('input[name="remember"]');
        const submitBtn = form.querySelector('button[type="submit"]');
        const errorContainer = document.querySelector('[data-ag-login-form] .ag-login-form__error');

        // Limpiar error anterior
        if (errorContainer) {
            errorContainer.remove();
        }

        // Deshabilitar submit durante el envío
        if (submitBtn) {
            submitBtn.disabled = true;
        }

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || form.querySelector('input[name="_token"]')?.value;

            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    username: usernameInput.value,
                    password: passwordInput.value,
                    zona_horaria: zonaHorariaInput?.value || null,
                    remember: rememberInput?.checked ?? false,
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                // Error de validación o credenciales inválidas
                if (response.status === 422) {
                    const errorMsg = data.message || labelErrorCredenciales;
                    showError(errorMsg);
                } else {
                    showError(labelErrorGenerico);
                }
            } else {
                // Login exitoso
                await ofrecerCredencialAlNavegador(usernameInput.value, passwordInput.value);

                if (data.requiere_seleccion_rol) {
                    window.location.href = '/panel/seleccionar-rol';
                } else {
                    window.location.href = data.destino || redirectPorDefecto;
                }
            }
        } catch (error) {
            console.error('Error during login:', error);
            showError(labelErrorGenerico);
        } finally {
            // Rehabilitar submit
            if (submitBtn) {
                submitBtn.disabled = false;
            }
        }
    });

    /**
     * Le ofrece al gestor de contraseñas del navegador las credenciales que
     * acaban de funcionar, para que aparezca el "¿Guardar contraseña?" y el
     * autocompletado tenga algo que ofrecer la próxima vez. Se espera antes
     * de redirigir: si la navegación arranca primero, el navegador descarta
     * el pedido.
     *
     * Silencioso por diseño — sin la API (Safari, Firefox), con el usuario
     * negándose, o en un contexto no seguro, el login ya está hecho y no hay
     * nada que reportar.
     */
    async function ofrecerCredencialAlNavegador(username, password) {
        if (typeof window.PasswordCredential !== 'function' || !navigator.credentials) {
            return;
        }

        try {
            await navigator.credentials.store(
                new window.PasswordCredential({ id: username, password, name: username })
            );
        } catch {
            // El gestor de contraseñas es un extra: nunca bloquea el ingreso.
        }
    }

    /**
     * Muestra un mensaje de error en el slot `ag-login-form__error`.
     * El slot debe existir, creamos el elemento si no está.
     */
    function showError(message) {
        const formContainer = document.querySelector('[data-ag-login-form]');
        if (!formContainer) return;

        // Crear o reutilizar el contenedor de error
        let errorContainer = formContainer.querySelector('.ag-login-form__error');
        if (!errorContainer) {
            errorContainer = document.createElement('div');
            errorContainer.className = 'ag-login-form__error';
            errorContainer.setAttribute('role', 'alert');
            formContainer.querySelector('.ag-login-form__form').insertAdjacentElement('beforebegin', errorContainer);
        }

        // Limpiar y establecer el nuevo mensaje
        errorContainer.innerHTML = `
            <i class="material-symbols-rounded" style="font-size: 1.25rem;">error</i>
            <span>${message}</span>
        `;
    }
});
