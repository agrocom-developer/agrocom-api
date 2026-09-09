{{--
    Atom: tema-inicial — el único `<script>` inline del sistema, y va en el
    `<head>` de TODA página que sirva panel/portal/auth, antes de `@vite`.

    Qué arregla (9/9/2026): el tema lo aplicaba `molecules/theme-toggle.js` en
    `DOMContentLoaded`, o sea DESPUÉS del primer pintado. Cuando el navegador
    tenía guardado un tema distinto al que sirvió el servidor —que es el caso
    normal apenas alguien usa el toggle en la pantalla de login, donde no hay
    sesión contra la cual persistir— cada navegación del panel se veía primero
    en claro y saltaba a oscuro de golpe. Medido sobre la grabación del
    usuario: ~130 ms de página blanca en cada clic del menú.

    Por eso es inline y no un módulo de `resources/js/`: todo lo que entra por
    `@vite` se carga con `type="module"`, que es diferido por definición y
    corre siempre después del render. La única forma de que `data-bs-theme`
    esté puesto ANTES del primer pintado es un script bloqueante en el head, y
    tiene que ser diminuto para no costar más de lo que ahorra.

    Además deja anotado en `<html>` qué tema venía del servidor
    (`data-ag-tema-servidor`) leyéndolo antes de pisarlo. Con eso
    `theme-toggle.js` sabe si servidor y navegador divergieron y persiste el
    del navegador — sin ese cierre, el servidor seguiría sirviendo el tema
    viejo en cada request y el parpadeo volvería en la próxima navegación.

    Sin props: el tema del servidor ya está en el `data-bs-theme` que escribió
    el `<html>` de la página (`templates/panel-shell` desde
    `sec_user_preferencia.tema`; `light` fijo en las páginas públicas).
--}}
<script>
    (function () {
        var html = document.documentElement;

        html.setAttribute('data-ag-tema-servidor', html.getAttribute('data-bs-theme') || 'light');

        try {
            var guardado = localStorage.getItem('ag-theme');

            if (guardado === 'light' || guardado === 'dark') {
                html.setAttribute('data-bs-theme', guardado);
            }
        } catch (e) {
            // Almacenamiento no disponible (modo privado, cuota): queda el
            // tema que sirvió el servidor, que es un valor válido.
        }
    })();
</script>
