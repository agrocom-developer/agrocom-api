import * as bootstrap from 'bootstrap';
import './shared/barra-acciones-dirty.js';
import './atoms/input.js';
import './atoms/select.js';
import './atoms/date.js';
import './atoms/checkbox-group.js';
import './molecules/theme-toggle.js';
import './molecules/timezone-badge.js';
import './molecules/file-field.js';
import './molecules/color-swatch-field.js';
import './molecules/table-search.js';
import './organisms/login-form.js';
import './organisms/topbar.js';
import './organisms/role-selection.js';
import './organisms/module-sidebar.js';
import './templates/auth-layout.js';
import './pages/login.js';
import './pages/clientes-form.js';
import './pages/contratos-form.js';
import './pages/lotes-form.js';
import './pages/lotes-generar.js';
import './pages/propiedades-form.js';
import './pages/usuarios-form.js';
import './pages/gastos-form.js';
import './pages/combustible-form.js';
import './pages/stock-movimiento-form.js';
import './pages/ordenes-mantenimiento-form.js';
import './pages/ordenes-form.js';
import './pages/asignacion-equipos-form.js';
import './pages/roles-permisos.js';
import './pages/configuracion-form.js';
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

// Pre-instanciación de TODO dropdown del panel con `strategy: 'fixed'`
// (movido acá desde organisms/topbar.js el 15/9/2026, cuando dejó de ser
// exclusivo del header: organisms/filter-panel y organisms/row-actions
// también son dropdowns, y viven dentro de `.ag-panel__content`, que igual
// que `.ag-topbar` tiene `overflow` para no romper el alto fijo del layout
// de tres niveles). Con `position: absolute` (default de Bootstrap) ese
// `overflow` recorta el menú apenas se abre — `strategy: 'fixed'` lo
// posiciona respecto del viewport en vez de sus ancestros, fuera de
// cualquier contexto de recorte. Se pre-instancia ANTES de que la data-api
// de Bootstrap cree su propia instancia por defecto al primer click.
// `computeStyles.gpuAcceleration: false` hace que Popper posicione con
// `top`/`left` en vez de `transform: translate3d(...)` (su default) — sin
// esto, CSS no puede sobreescribir la posición con `!important` (un
// `transform` inline gana siempre), que es lo que organisms/filter-panel
// necesita en mobile para anclarse a los bordes del viewport en vez de a la
// posición del botón que lo abre (15/9/2026).
//
// `popperConfig` como función (en vez de objeto plano): Bootstrap arma su
// config final con un merge SUPERFICIAL (`{...defaultBsPopperConfig,
// ...customConfig}`, ver node_modules/bootstrap/js/src/dropdown.js
// `_getPopperConfig`) — un `modifiers` propio reemplaza entero al default
// en vez de combinarse. Un objeto plano acá (como estaba hasta el
// 16/9/2026) tira los modifiers por defecto de Bootstrap, `preventOverflow`
// y `offset`, dejando CUALQUIER dropdown del panel sin clamp contra el
// viewport. Con los 4 dropdowns angostos (campana, menú usuario, 3 puntos,
// filtros) no se notaba porque casi siempre abren cerca de una esquina; se
// hizo visible con molecules/color-swatch-field (Propiedades, grilla de 23
// swatches, botón "Cambiar" cerca del borde derecho del formulario): el
// popup se salía por la derecha y el documento ganaba ancho de scroll
// (espacio vacío a la derecha, contenido corrido). Pasar una función
// recibe `defaultBsPopperConfig` como argumento y permite conservar sus
// modifiers en vez de pisarlos.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach((el) => {
        new bootstrap.Dropdown(el, {
            popperConfig: (defaultBsPopperConfig) => ({
                ...defaultBsPopperConfig,
                strategy: 'fixed',
                modifiers: [
                    ...defaultBsPopperConfig.modifiers,
                    { name: 'computeStyles', options: { gpuAcceleration: false } },
                ],
            }),
        });
    });
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

    // Editor de coordenadas de una propiedad (adenda 16/9/2026 a ADR 0018
    // punto 1 / ADR 0020): marcador + polígonos múltiples, módulo aparte del
    // de Lote (ver su docblock).
    if (document.querySelector('[data-ag-propiedad-mapa]')) {
        import('./organisms/propiedad-mapa-editor.js');
    }

    // atoms/datetime (flatpickr): solo dos usos hoy (pausas), no vale la
    // pena bajarlo en el resto del panel.
    if (document.querySelector('[data-ag-datetime]')) {
        import('./atoms/datetime.js');
    }
});
