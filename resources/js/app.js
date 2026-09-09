import * as bootstrap from 'bootstrap';
import './atoms/input.js';
import './atoms/select.js';
import './atoms/date.js';
import './atoms/checkbox-group.js';
import './molecules/theme-toggle.js';
import './molecules/timezone-selector.js';
import './organisms/login-form.js';
import './organisms/topbar.js';
import './organisms/role-selection.js';
import './organisms/module-sidebar.js';
import './templates/auth-layout.js';
import './pages/login.js';
import './pages/clientes-form.js';
import './pages/contratos-form.js';
import './pages/campos-form.js';
import './pages/lotes-form.js';
import './pages/usuarios-form.js';
import './pages/gastos-form.js';
import './pages/combustible-form.js';
import './pages/stock-movimiento-form.js';
import './pages/ordenes-mantenimiento-form.js';
import './pages/roles-permisos.js';
import './pages/reportes-comerciales.js';
import './pages/personas-desempeno.js';

// Bootstrap components are now available globally via window
window.bootstrap = bootstrap;

// Tooltips nativos de Bootstrap: la data-api de Bootstrap NO los auto-inicializa
// (a diferencia de collapse/offcanvas/dropdown) — hay que instanciarlos a mano.
// Cross-cutting, no de un componente en particular (hoy: badge de menu-item),
// por eso vive acá y no en el JS de un organism específico.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));
});

// Carga diferida de ApexCharts/Leaflet (dashboard): un solo entrypoint Vite
// para todo el panel, así que un import estático acá los bajaría hasta en el
// login. `import()` dinámico + guard de presencia en el DOM hace que Vite
// genere chunks separados que el navegador solo pide cuando la página
// realmente tiene un gráfico o un mapa.
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('[data-ag-chart]')) {
        import('./organisms/dashboard-charts.js');
    }

    if (document.querySelector('[data-ag-map]')) {
        import('./organisms/dashboard-map.js');
    }

    // Editor del perímetro de un lote (formulario de campos): Leaflet más el
    // plugin de dibujo, que no entra en ninguna otra pantalla.
    if (document.querySelector('[data-ag-lote-mapa]')) {
        import('./organisms/lote-mapa-editor.js');
    }
});
