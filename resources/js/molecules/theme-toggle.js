// Comportamiento de la molécula `theme-toggle`
// (resources/views/components/molecules/theme-toggle.blade.php): alterna
// `data-bs-theme` en <html> y despacha `agrocom:theme-changed` para que quien
// persista la preferencia (un componente Livewire contra
// `sec_user_preferencia.tema`, más adelante) escuche el evento. Presentación
// pura: no llama al backend ni decide el valor por defecto — mismo patrón de
// delegación de eventos que ya usa resources/js/atoms/input.js.
//
// Puede haber más de una instancia del switch en la misma página (topbar +
// pie del sidebar): el click en CUALQUIERA de ellas cambia el único atributo
// global, y acá se sincroniza `aria-checked` de TODAS.

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

document.addEventListener('DOMContentLoaded', sincronizarInterruptores);
