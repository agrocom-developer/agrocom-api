// Comportamiento del átomo `input` (resources/views/components/atoms/input.blade.php)
// cuando `type="password"`: mostrar/ocultar sin ninguna dependencia (Alpine/
// Livewire todavía no están instalados en el proyecto). Es comportamiento de
// presentación, no lógica de negocio — no valida ni envía nada.

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-ag-toggle-password]');

    if (!toggle) {
        return;
    }

    const control = toggle.closest('.ag-input__control');
    const field = control?.querySelector('.ag-input__field');

    if (!field) {
        return;
    }

    const isCurrentlyText = field.type === 'text';
    field.type = isCurrentlyText ? 'password' : 'text';

    const icon = toggle.querySelector('.ag-icon');
    if (icon) {
        icon.textContent = isCurrentlyText ? 'visibility' : 'visibility_off';
    }

    toggle.setAttribute('aria-pressed', String(!isCurrentlyText));
    toggle.setAttribute(
        'aria-label',
        isCurrentlyText ? toggle.dataset.labelShow : toggle.dataset.labelHide,
    );
});
