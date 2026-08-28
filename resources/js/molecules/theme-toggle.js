// Comportamiento de la molécula `theme-toggle`
// (resources/views/components/molecules/theme-toggle.blade.php): alterna
// `data-bs-theme` en <html>, despacha `agrocom:theme-changed` y — quinta
// vuelta — PERSISTE la preferencia contra `sec_user_preferencia.tema` vía
// POST a la URL que la página autenticada expone en
// `<meta name="ag-preferencias-tema-url">` (templates/panel-shell). En
// páginas sin esa meta (login, sin usuario) el cambio queda solo en el DOM,
// como antes. Fire-and-forget: si el POST falla, el tema del DOM ya cambió
// y el usuario no pierde nada más que la persistencia entre recargas.
//
// Puede haber más de una instancia del switch en la misma página: el click
// en CUALQUIERA de ellas cambia el único atributo global, y acá se
// sincroniza `aria-checked` de TODAS.

const OSCURO = 'dark';
const CLARO = 'light';

function temaActual() {
    return document.documentElement.getAttribute('data-bs-theme') === OSCURO ? OSCURO : CLARO;
}

function sincronizarInterruptores() {
    const esOscuro = temaActual() === OSCURO;

    document.querySelectorAll('[data-ag-theme-toggle]').forEach((boton) => {
        boton.setAttribute('aria-checked', String(esOscuro));
    });
}

document.addEventListener('click', (event) => {
    const boton = event.target.closest('[data-ag-theme-toggle]');

    if (!boton) {
        return;
    }

    const siguiente = temaActual() === OSCURO ? CLARO : OSCURO;
    document.documentElement.setAttribute('data-bs-theme', siguiente);
    sincronizarInterruptores();

    window.dispatchEvent(new CustomEvent('agrocom:theme-changed', { detail: { theme: siguiente } }));
});

window.addEventListener('agrocom:theme-changed', (event) => {
    const url = document.querySelector('meta[name="ag-preferencias-tema-url"]')?.content;

    if (!url) {
        return; // página sin usuario autenticado (login) — nada que persistir
    }

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content,
            Accept: 'application/json',
        },
        body: JSON.stringify({ tema: event.detail.theme }),
    }).catch(() => {
        // Fire-and-forget: el DOM ya refleja el tema elegido.
    });
});

document.addEventListener('DOMContentLoaded', sincronizarInterruptores);
