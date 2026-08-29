// Comportamiento de la molécula `theme-toggle`
// (resources/views/components/molecules/theme-toggle.blade.php): resuelve y
// aplica `data-bs-theme` en <html>, despacha `agrocom:theme-changed` y
// persiste la preferencia.
//
// Auditoría visual externa, obs. #9 (28/8/2026): pasa de 2 estados
// (claro/oscuro) a 3 (claro/oscuro/sistema). Dos atributos en <html>, cada
// uno con un rol distinto:
// - `data-bs-theme` ("light"|"dark"): el tema RESUELTO — el único que
//   consumen los tokens CSS (theme-light.css/theme-dark.css). "Sistema"
//   nunca es un valor válido acá.
// - `data-ag-theme-preference` ("light"|"dark"|"system"): lo que el usuario
//   ELIGIÓ — la fuente de verdad de qué celda del segmented control se pinta
//   activa (ver theme-toggle.css).
//
// Persistencia: `sec_user_preferencia.tema` (POST a la URL de
// `<meta name="ag-preferencias-tema-url">`) es un enum Claro|Oscuro — no
// puede guardar "sistema". Por pedido explícito del plan de auditoría
// (no tocar ese enum sin coordinar con backend), "sistema" se persiste SOLO
// en localStorage de este navegador: sobrevive a recargas en el mismo
// dispositivo, pero no viaja entre dispositivos ni aparece en el valor
// server-rendered de data-bs-theme/data-ag-theme-preference (ambos arrancan
// en claro/oscuro — panel-shell.blade.php). Por eso la resolución de
// "sistema" ocurre acá, en un script diferido (bundle de Vite, no bloqueante
// en <head>): hay un margen breve donde el HTML se pinta con el tema
// servidor antes de que este script lo corrija a lo que el SO pide. Aceptado
// como costo de no tocar el backend en esta vuelta.
//
// Puede haber más de una instancia del switch en la misma página: el click
// en CUALQUIERA de ellas cambia el único estado global, y acá se sincroniza
// TODAS.

const OSCURO = 'dark';
const CLARO = 'light';
const SISTEMA = 'system';
const CLAVE_LOCALSTORAGE = 'ag-theme-preference';

const consultaOscuro = window.matchMedia?.('(prefers-color-scheme: dark)');

function resolverSistema() {
    return consultaOscuro?.matches ? OSCURO : CLARO;
}

function preferenciaActual() {
    const valor = document.documentElement.getAttribute('data-ag-theme-preference');

    return valor === SISTEMA || valor === OSCURO || valor === CLARO ? valor : CLARO;
}

function sincronizarInterruptores(preferencia) {
    document.querySelectorAll('[data-ag-theme-toggle]').forEach((grupo) => {
        grupo.querySelectorAll('[data-ag-theme-option]').forEach((boton) => {
            boton.setAttribute('aria-checked', String(boton.dataset.agThemeOption === preferencia));
        });
    });
}

/**
 * Aplica una preferencia (claro/oscuro/sistema): resuelve el tema real,
 * actualiza los dos atributos de <html> y sincroniza todas las instancias
 * del control. NO persiste — eso lo decide cada llamador (ver abajo).
 */
function aplicarPreferencia(preferencia) {
    const resuelto = preferencia === SISTEMA ? resolverSistema() : preferencia;

    document.documentElement.setAttribute('data-bs-theme', resuelto);
    document.documentElement.setAttribute('data-ag-theme-preference', preferencia);
    sincronizarInterruptores(preferencia);

    return resuelto;
}

function persistirPreferencia(preferencia, resuelto) {
    try {
        localStorage.setItem(CLAVE_LOCALSTORAGE, preferencia);
    } catch {
        // Almacenamiento no disponible (modo privado, cuota) — el DOM ya
        // refleja la preferencia, solo se pierde que sobreviva a un reload.
    }

    if (preferencia === SISTEMA) {
        return; // el enum del backend no admite "sistema" — ver cabecera.
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
        body: JSON.stringify({ tema: resuelto }),
    }).catch(() => {
        // Fire-and-forget: el DOM ya refleja el tema elegido.
    });
}

document.addEventListener('click', (event) => {
    const boton = event.target.closest('[data-ag-theme-option]');

    if (!boton) {
        return;
    }

    const preferencia = boton.dataset.agThemeOption;

    if (preferencia === preferenciaActual()) {
        return;
    }

    const resuelto = aplicarPreferencia(preferencia);
    persistirPreferencia(preferencia, resuelto);

    window.dispatchEvent(new CustomEvent('agrocom:theme-changed', { detail: { theme: resuelto, preference: preferencia } }));
});

// Mientras la preferencia activa sea "sistema", re-resolver en vivo si
// cambia la preferencia del SO (sin volver a persistir: la preferencia del
// usuario sigue siendo "sistema", solo cambió a qué resuelve).
consultaOscuro?.addEventListener('change', () => {
    if (preferenciaActual() !== SISTEMA) {
        return;
    }

    const resuelto = aplicarPreferencia(SISTEMA);
    window.dispatchEvent(new CustomEvent('agrocom:theme-changed', { detail: { theme: resuelto, preference: SISTEMA } }));
});

document.addEventListener('DOMContentLoaded', () => {
    let guardada = null;

    try {
        guardada = localStorage.getItem(CLAVE_LOCALSTORAGE);
    } catch {
        // Almacenamiento no disponible — se queda con lo que trajo el servidor.
    }

    if (guardada && guardada !== preferenciaActual() && [CLARO, OSCURO, SISTEMA].includes(guardada)) {
        aplicarPreferencia(guardada);

        return;
    }

    sincronizarInterruptores(preferenciaActual());
});
