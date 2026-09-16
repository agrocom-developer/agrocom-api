/**
 * "Crear Lotes" (HU-72 reconstruida, 16/9/2026): un solo juego de
 * atributos de terreno (`_lote-terreno.blade.php`, prefijo `terreno`) que
 * se aplica a todos los lotes generados — sin filas dinámicas. Acá solo
 * hace falta el mismo toggle "limpio" → grado de obstáculos que
 * `lotes-form.js`, sin la complejidad de filas repetibles (esta pantalla
 * no clona nada).
 *
 * Guard de presencia en el DOM (mismo criterio que `login.js`): en
 * cualquier página sin `[data-ag-lotes-generar-form]` este módulo no hace
 * nada.
 */
document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.querySelector('[data-ag-lotes-generar-form]');
    if (!formulario) return;

    const switchLimpio = formulario.querySelector('[data-ag-lote-limpio]');
    const envoltorioGrado = formulario.querySelector('[data-ag-lote-grado-obstaculos-wrap]');

    if (!switchLimpio || !envoltorioGrado) return;

    const textoLabel = switchLimpio.closest('.ag-switch__control')?.querySelector('.ag-switch__label');
    const textoSi = switchLimpio.dataset.agLoteLimpioTextoSi;
    const textoNo = switchLimpio.dataset.agLoteLimpioTextoNo;

    switchLimpio.addEventListener('change', () => {
        envoltorioGrado.hidden = switchLimpio.checked;
        if (textoLabel) {
            textoLabel.textContent = switchLimpio.checked ? textoSi : textoNo;
        }
    });
});
