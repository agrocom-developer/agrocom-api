// Comportamiento de `molecules/color-swatch-field` (16/9/2026, corregido el
// mismo día: popup anclado, no modal — ver el docblock del componente):
// sincroniza el swatch/hex/nombre del control compacto con lo que se elige
// dentro de su propio popup.
//
// Sin este script, el campo sigue siendo funcional (el radio real vive
// adentro del mismo componente y viaja con el POST igual), solo que el
// control compacto no se actualiza hasta recargar la página — mejora
// progresiva, mismo criterio que `file-field.js`.
//
// El popup en sí (abrir/cerrar/cerrar-al-elegir) es 100% Bootstrap nativo
// (`data-bs-toggle="dropdown"`, auto-close default al clickear cualquier
// radio de adentro) — este script no abre ni cierra nada, solo repinta.
//
// Delegación sobre `document` (nunca un init por elemento): funciona igual
// para el control ya renderizado por el servidor que para cualquiera que
// aparezca después.
document.addEventListener('change', (evento) => {
    const radio = evento.target.closest('[data-ag-color-swatch-field-input]');

    if (!radio) {
        return;
    }

    const campo = radio.closest('[data-ag-color-swatch-field]');

    if (!campo) {
        return;
    }

    const swatch = campo.querySelector('[data-ag-color-swatch-field-swatch]');
    const hex = campo.querySelector('[data-ag-color-swatch-field-hex]');
    const nombre = campo.querySelector('[data-ag-color-swatch-field-name]');
    const etiquetaColor = radio.closest('label')?.querySelector('.ag-color-swatch-picker__sr-label')?.textContent ?? '';

    if (swatch) {
        swatch.style.setProperty('--ag-color-swatch-fill', radio.value);
        swatch.classList.remove('ag-color-swatch-field__swatch--empty');
    }

    if (hex) {
        hex.textContent = radio.value.toUpperCase();
        hex.classList.remove('ag-color-swatch-field__placeholder');
        hex.classList.add('ag-color-swatch-field__hex');
    }

    if (nombre) {
        nombre.textContent = etiquetaColor;
    } else if (etiquetaColor) {
        // El control arrancó sin color (no había nada que renderizar): el
        // span sr-only todavía no existe en el DOM, se crea recién ahora.
        const span = document.createElement('span');
        span.className = 'ag-color-swatch-field__sr-name';
        span.dataset.agColorSwatchFieldName = '';
        span.textContent = etiquetaColor;
        hex?.insertAdjacentElement('afterend', span);
    }
});
