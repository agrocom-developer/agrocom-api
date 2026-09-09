# ADR 0008 — Separación backend/frontend dentro del monolito Laravel

**Estado:** Aceptada.

## Contexto

El panel web es Laravel full-stack (AdminLTE + Blade + Livewire, ADR 0002), no una SPA con API pública separada. Eso no significa que backend y frontend dejen de ser responsabilidades distintas: sin una frontera explícita, es fácil que un componente Livewire termine escribiendo lógica de negocio directamente, o que un controller de API y una vista web terminen duplicando la misma regla con matices distintos.

## Decisión

Dos fronteras, ambas obligatorias:

1. **Rutas siempre separadas.** `routes/web.php` sirve el panel (AdminLTE/Livewire, con sesión) y el portal del cliente; `routes/api.php` sirve exclusivamente a las apps de campo (Sanctum, token por dispositivo). Ningún middleware ni guard se comparte entre ambos archivos de rutas.
2. **Dentro de cada módulo de dominio** (`app/Dominios/<Modulo>/Infraestructura/`, ADR 0003):
   - `Http/Controllers/Api/` — controllers REST para las apps de campo.
   - `Http/Livewire/` — componentes Livewire para el panel/portal.
   - Ambos son adaptadores delgados: reciben la petición, invocan un caso de uso de `Aplicacion/`, devuelven la respuesta en el formato que corresponda (JSON vs. render Blade). **Ninguno de los dos contiene lógica de negocio ni la duplica** — la regla de negocio vive una sola vez, en `Aplicacion/` o `Dominio/`.

## Alternativas descartadas

- **Un controller único por recurso que sirva ambas superficies** (API y web): mezcla el contrato de API pública (versionado, consumido por `agrocom-field`) con el ciclo de vida de una sesión web con CSRF y redirects — descartado porque cualquier cambio pensado para uno termina afectando al otro sin que sea intencional.

## Consecuencias

- Un caso de uso nuevo (`Aplicacion/ValidarSesion.php`, por ejemplo) se escribe una sola vez y lo invocan tanto el endpoint `POST /api/sesiones/{id}/validar` como el componente Livewire de la cola de validación del panel — sin reescribir la regla en Blade ni en el controller.
- Los agentes de IA (`CLAUDE.md`) que implementen una HU nueva revisan primero si el caso de uso ya existe antes de escribir la lógica dentro de un controller o componente.
