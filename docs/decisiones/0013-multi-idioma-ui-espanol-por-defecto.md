# ADR 0013 — Multi-idioma en la UI: claves de traducción desde el día uno, español como único idioma en v1

**Estado:** Aceptada.

## Contexto

El panel web (ADR 0002) y las apps de campo (`agrocom-field`, ADR 0005) están por construir sus primeras pantallas. Hasta ahora nada decía en qué idioma habla la interfaz ni cómo: el vocabulario del dominio es en español por convención (`CLAUDE.md`, ADR 0011), pero eso gobierna tablas, entidades y enums — no los textos que las pantallas muestran a las personas. Si mañana un cliente, agrónomo o socio comercial requiere otro idioma, y los textos de UI están escritos como literales dentro de Blade, componentes Livewire, mensajes de validación, API Resources y widgets Flutter, el costo es un refactor de toda superficie visible del sistema.

Ya existe un precedente exacto de este problema resuelto por adelantado: la invariante 11 de `CLAUDE.md` prohíbe colores hardcodeados porque los tokens CSS son lo que hace posible el theming por usuario (ADR 0002, punto 2). Los textos de UI tienen la misma estructura: la clave de traducción es al idioma lo que el token es al color.

## Decisión

El sistema es **multi-idioma**: el panel web y las apps soportan cambio de idioma, con **español como idioma por defecto y único disponible en v1**. Otros idiomas se agregan después sin refactor. Reglas:

1. **Ningún texto de interfaz hardcodeado desde el día uno.** Todo string de UI pasa por la capa de traducción de la plataforma correspondiente:
   - **Backend/panel**: la capa de traducción de Laravel — catálogos `lang/es/*.php` y `__()`/`trans()` en Blade, Livewire y controladores; incluye los mensajes de validación (`lang/es/validation.php`) y los textos que emiten los API Resources cuando aplique (etiquetas, mensajes — no los datos del dominio).
   - **Apps Flutter**: el mecanismo i18n de Flutter (paquete `intl` + archivos ARB), dentro de la estructura feature-first del ADR 0005.
2. **Preferencia de idioma por usuario**, persistida junto a la preferencia de tema de color que HU-02 ya contempla (ADR 0002, punto 4): vive en el modelo de usuario del módulo `Identidad`, no en `sec_*` (el ADR 0004 mantiene `sec_*` como RBAC puro).
3. **El vocabulario del dominio NO se traduce.** Tablas, entidades, enums y vocabulario del negocio siguen en español (convención de `CLAUDE.md`, ADR 0011). Lo que se traduce son las etiquetas de UI que los presentan: p. ej. el enum `EstadoOrdenAplicacion` guarda `vigente` en la base; la pantalla muestra el texto resuelto por la clave de traducción correspondiente. Los datos persisten en un solo idioma; la presentación resuelve el idioma del usuario.

Es el paralelo exacto de la invariante 11 de `CLAUDE.md`: los tokens permiten theming sin tocar componentes; las claves de traducción permiten idioma sin tocar pantallas.

## Alternativas descartadas

- **Monolingüe fijo con textos directos en las vistas**: más rápido hoy (cero indirección), pero convierte cualquier requerimiento futuro de idioma en un refactor total de la capa de presentación — y ese requerimiento es plausible (clientes/agrónomos de otros mercados). El costo de escribir `__('clave')` en vez del literal es marginal si se paga desde la primera pantalla; el de retro-instalarlo, no.
- **Traducir también el dominio** (enums, estados, vocabulario del negocio en varios idiomas en la base): crearía dos idiomas para la misma cosa — exactamente lo que la convención de `CLAUDE.md` ya rechazó. El dominio habla un solo idioma; la UI traduce su presentación.

## Consecuencias

- Toda pantalla, componente Blade, mensaje de validación y widget Flutter nace referenciando claves de traducción, nunca literales — un string de UI hardcodeado es detectable a simple vista en el diff del PR, igual que un color hexadecimal.
- Agregar un idioma nuevo se reduce a agregar catálogos (`lang/xx/*.php` en el backend, un archivo ARB en las apps) y habilitarlo en el selector de preferencia — sin tocar componentes ni pantallas.
- El modelo de usuario de `Identidad` suma el campo de preferencia de idioma junto al de tema (misma HU-02, misma migración de preferencia); en v1 el selector ofrece solo español.
- **Pendiente:** proponer al usuario agregar la invariante "ningún texto de UI hardcodeado" a `CLAUDE.md` cuando arranque HU-02 — este ADR no modifica `CLAUDE.md`.
- **Pendiente:** definir el catálogo de claves de traducción (`lang/es/`, convención de nombrado y organización por módulo/pantalla) como parte del sistema de diseño, a cargo del agente `design-ui` junto con el catálogo de componentes Atomic Design (ADR 0002).
