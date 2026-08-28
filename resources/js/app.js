import * as bootstrap from 'bootstrap';
import './atoms/input.js';
import './molecules/theme-toggle.js';
import './organisms/login-form.js';
import './organisms/topbar.js';
import './organisms/role-selection.js';
import './organisms/module-sidebar.js';
import './templates/auth-layout.js';
import './pages/login.js';

// Bootstrap components are now available globally via window
window.bootstrap = bootstrap;

// Tooltips nativos de Bootstrap: la data-api de Bootstrap NO los auto-inicializa
// (a diferencia de collapse/offcanvas/dropdown) — hay que instanciarlos a mano.
// Cross-cutting, no de un componente en particular (hoy: badge de menu-item),
// por eso vive acá y no en el JS de un organism específico.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));
});
