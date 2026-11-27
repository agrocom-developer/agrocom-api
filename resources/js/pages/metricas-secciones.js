/**
 * pages/metricas-secciones.js — pestañas de «Métricas del mercado». El mecanismo es el `tab` de
 * Bootstrap; acá solo se recuerda la pestaña activa en el hash de la URL (`#ag-tab-negociacion`)
 * para que recargar, compartir el enlace o cambiar el período (los botones de arriba) no vuelva
 * a la primera. Sin JavaScript todo sigue funcionando: se ve la primera pestaña.
 */

function iniciar() {
    if (!document.querySelector('[data-ag-metricas-secciones]')) {
        return;
    }

    const botones = Array.from(document.querySelectorAll('.ag-tabs [data-bs-toggle="tab"]'));
    const enlacesPeriodo = Array.from(document.querySelectorAll('a[href*="/panel/metricas"][href*="dias="]'));

    const recordar = (id) => {
        history.replaceState(null, '', `#${id}`);
        enlacesPeriodo.forEach((enlace) => {
            const url = new URL(enlace.href);
            url.hash = id;
            enlace.href = url.toString();
        });
    };

    const inicial = botones.find((boton) => boton.dataset.bsTarget === window.location.hash);
    if (inicial && window.bootstrap) {
        window.bootstrap.Tab.getOrCreateInstance(inicial).show();
        recordar(window.location.hash.slice(1));
    }

    botones.forEach((boton) => {
        boton.addEventListener('shown.bs.tab', () => recordar(boton.dataset.bsTarget.slice(1)));
    });
}

// `window.bootstrap` se asigna al final de app.js: se espera a que el documento esté listo.
document.addEventListener('DOMContentLoaded', iniciar);
