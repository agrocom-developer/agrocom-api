// Comportamiento de la molécula `theme-toggle`
// (resources/views/components/molecules/theme-toggle.blade.php): switch
// binario claro/oscuro. Aplica `data-bs-theme` en <html>, lo persiste
// contra `sec_user_preferencia.tema` (POST a la URL de
// `<meta name="ag-preferencias-tema-url">`) y despacha
// `agrocom:theme-changed`.
//
// Octava vuelta (29/8/2026): se retira "sistema" (auditoría visual externa
// obs. #9 lo había agregado como tercer estado) por pedido explícito del
// usuario — el enum del backend siempre fue Claro|Oscuro únicamente, así
// que "sistema" vivía enteramente en localStorage con su propio atributo
// `data-ag-theme-preference` para distinguirlo del `data-bs-theme` resuelto.
// Sin "sistema", esa indirección no hace falta: `data-bs-theme` es otra vez
// la única fuente de verdad, tanto para los tokens de color como para qué
// ícono muestra el botón.
//
// Puede haber más de una instancia del botón en la misma página (topbar +
// auth-layout): el click en CUALQUIERA cambia el único `data-bs-theme`
// global, y como el ícono visible de cada instancia depende solo de ese
// atributo (ver theme-toggle.css), todas quedan sincronizadas sin código de
// sincronización explícito.

const OSCURO = 'dark';
const CLARO = 'light';
const CLAVE_LOCALSTORAGE = 'ag-theme';

function temaActual() {
    const valor = document.documentElement.getAttribute('data-bs-theme');

    return valor === OSCURO ? OSCURO : CLARO;
}

function aplicarTema(tema) {
    document.documentElement.setAttribute('data-bs-theme', tema);
}

function persistirTema(tema) {
    try {
        localStorage.setItem(CLAVE_LOCALSTORAGE, tema);
    } catch {
        // Almacenamiento no disponible (modo privado, cuota) — el DOM ya
        // refleja el tema, solo se pierde que sobreviva a un reload.
    }

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
        body: JSON.stringify({ tema }),
    }).catch(() => {
        // Fire-and-forget: el DOM ya refleja el tema elegido.
    });
}

document.addEventListener('click', (event) => {
    const boton = event.target.closest('[data-ag-theme-toggle]');

    if (!boton) {
        return;
    }

    const siguiente = temaActual() === OSCURO ? CLARO : OSCURO;

    aplicarTema(siguiente);
    persistirTema(siguiente);

    window.dispatchEvent(new CustomEvent('agrocom:theme-changed', { detail: { theme: siguiente } }));
});

document.addEventListener('DOMContentLoaded', () => {
    let guardado = null;

    try {
        guardado = localStorage.getItem(CLAVE_LOCALSTORAGE);
    } catch {
        // Almacenamiento no disponible — se queda con lo que trajo el servidor.
    }

    if ((guardado === CLARO || guardado === OSCURO) && guardado !== temaActual()) {
        aplicarTema(guardado);
    }
});
