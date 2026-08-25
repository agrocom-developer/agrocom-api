# ADR 0002 — Panel web: AdminLTE + Blade/Livewire con Atomic Design

**Estado:** Aceptada · **Reemplaza:** la propuesta de React de la spec v1.0 legacy y la evaluación de Filament de `enfoque_desarrollo_sistema_fumigacion.md` (legacy).

## Contexto

El panel web no es una superficie secundaria: lo usan jefe de campo, encargado de operaciones y dueño con distintos permisos, y también sirve de base al portal del cliente (agrónomo/dueño del campo) bajo un guard separado. La app Flutter, en cambio, es exclusiva de piloto y auxiliar, y no tiene menús — el modelo de seguridad `sec_*` (ADR 0004) gobierna la navegación de este panel, no la de la app de campo.

Se evaluaron tres caminos:

| Criterio | Filament | AdminLTE + Blade/Livewire | SPA Material (Vue/React) |
|---|---|---|---|
| Qué da hecho | Layout + CRUD + tablas + formularios + permisos | Solo layout (cáscara) | Solo componentes visuales |
| Qué hay que escribir | Solo lo específico del dominio | Todo el comportamiento | Todo + API pública + auth por token |
| Encaje con `sec_menu`/`sec_module` (menú dinámico desde BD) | Contra su convención de navegación propia | Encaja natural — el menú se renderiza desde las propias tablas | Encaja natural |
| Tiempo de panel en ruta crítica | ~5–6 días | ~10–12 días | ~12–15 días |
| Experiencia previa del desarrollador | Ninguna | N/A (se construye a medida) | N/A |
| Piezas móviles añadidas | Ninguna | Ninguna | Aplicación entera adicional, CORS, auth por token |

Filament ahorra calendario, pero el desarrollador nunca lo usó y su sistema de componentes (inputs, tablas, cards, forms) tiene una convención y una estética propias que no calzan con lo que se quiere consolidar. La SPA Material queda descartada para v1 por agregar una aplicación completa. Y el activo real que se quiere construir — `sec_*` como módulo de seguridad reutilizable entre proyectos futuros de Agrocom (ver ADR 0004) — solo rinde si el panel lo deja gobernar la navegación de punta a punta, cosa que Filament dificulta.

## Decisión

**AdminLTE + Blade + Livewire**, con las siguientes reglas de construcción:

1. **Metodología de componentes: Atomic Design.** Catálogo propio de componentes Blade organizado en:
   - `atoms/`: input, button, badge, icon, label.
   - `molecules/`: form-group, card-header, table-cell-actions, menu-item.
   - `organisms/`: data-table (con filtros), formulario completo, sidebar de navegación, navbar.
   - `templates/`: layout base (sidebar + content + footer), layout de autenticación.
   - `pages/`: composiciones concretas (login, 404, dashboard de cada rol).
2. **Mezcla deliberada de sistemas visuales**: la estructura y utilidades de layout vienen de Bootstrap (base de AdminLTE); los componentes de interacción (inputs, cards, forms) y la iconografía usan Material Design. Se define un set de tokens propios (color, espaciado, tipografía) para que ambos sistemas convivan de forma consistente y no se herede la estética por defecto de ninguno de los dos tal cual. **Ningún color se hardcodea** en Blade, CSS ni componentes: todo color se referencia por token (custom property CSS), nunca por valor literal — es lo que hace posible el theming por usuario del punto 4 sin tocar componentes.
3. **Control total** sobre sidebar, layout, content, footer, y sobre las páginas de sistema: login, logout, reset de contraseña, 404, error genérico. Ninguna de estas páginas queda delegada a un scaffold de terceros.
4. **Theming configurable por usuario**: al menos claro/oscuro, aplicado vía custom properties CSS (tokens), con la preferencia persistida por usuario (no por sesión ni a nivel de instalación) — vive en el modelo de usuario del módulo `Identidad`, no en `sec_user` (ADR 0004 mantiene `sec_*` como el RBAC puro).
5. **El menú se renderiza desde `sec_menu`/`sec_permission`** (ADR 0004): un usuario con múltiples roles ve la unión de los menús de sus roles; los botones de acción se ocultan según `sec_permission`, no según el rol crudo.
6. **El portal del cliente** reutiliza el mismo layout con un guard de autenticación separado, sin acceso a los menús internos.

## Alternativas descartadas

- **Filament 4**: 5–6 días más rápido, pero el desarrollador nunca lo usó y su convención de navegación por código no encaja con `sec_menu` dinámico desde BD sin ir contra su patrón.
- **SPA Material (Vue/Vuetify o React/MUI)**: descartada para v1 — agrega una aplicación entera (build propio, CORS, auth por token) que ningún requisito actual justifica.

## Consecuencias

- El panel deja de estar en los ~5–6 días de Filament y pasa a ~10–12 días de construcción mínima — ya contemplado en `docs/gestion/plan_sprints.md`, sin afectar la ruta crítica de las primeras semanas (sync, spike de hardware, app piloto).
- Se necesita, desde el arranque del panel (Sprint 1, ver plan de sprints), el catálogo base de componentes Atomic Design antes de construir la primera pantalla real — es deuda de diseño que hay que pagar temprano para no repetirla en cada CRUD.
- Cualquier funcionalidad nueva del panel (colas de validación, bandeja de alertas) se construye como componente Livewire dentro de este mismo layout, no como vista aislada.
