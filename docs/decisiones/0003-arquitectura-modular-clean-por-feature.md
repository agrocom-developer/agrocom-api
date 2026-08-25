# ADR 0003 — Backend: monolito modular con Clean Architecture por eje ortogonal a los módulos de dominio

**Estado:** Aceptada · **Fuente:** `docs/legacy/decisiones_arquitectura_v2.md`, sección 3.

## Contexto

Un solo desarrollador con agentes de IA como implementadores principales necesita fronteras que la propia suite de tests pueda defender — no reglas que dependan de disciplina o memoria. Al mismo tiempo, la Clean Architecture ortodoxa (entidades independientes del framework, repositorio para cada modelo) es un impuesto que un equipo de una persona no puede pagar y que casi nunca devuelve valor en Laravel.

## Decisión

Dos ejes ortogonales:

- **Arquitectura por feature (módulos de dominio)** decide *dónde vive* el código — cohesión por negocio: `Identidad`, `Comercial`, `Mezclas`, `Operaciones`, `Sync`, `Finanzas`, `Mantenimiento`, `Portal`, `Reportes`, más `Compartido` (no depende de nadie; todos dependen de él).
- **Clean Architecture pragmática** decide *hacia dónde apuntan las dependencias* dentro de cada módulo: `Contratos/` (interfaces + DTOs — la frontera pública), `Aplicacion/` (casos de uso), `Dominio/` (reglas puras, sin Laravel ni base de datos), `Infraestructura/` (Eloquent, HTTP, colas).

Reglas de cohesión fuerte / acoplamiento débil:

1. Un módulo solo escribe sus propias tablas.
2. Entre módulos se viaja por `Contratos/` (síncrono, cuando se necesita respuesta) o por eventos de dominio (cuando es consecuencia — `SesionValidada` → `Finanzas` genera devengos). Los eventos llevan DTOs primitivos, nunca modelos Eloquent.
3. Las referencias cruzadas por ID están permitidas (es una base de datos relacional, no microservicios); lo prohibido es la lógica cruzada.
4. `Eloquent ES el modelo de dominio` dentro del módulo dueño — no se duplica en una entidad pura aparte.
5. Las reglas se verifican con tests de arquitectura (Pest arch), no con disciplina: un agente de IA que importe un modelo ajeno "por comodidad" rompe la suite.

Inyección de dependencias vía el contenedor de Laravel: cada módulo registra su propio `ServiceProvider`; constructor injection en casos de uso, controllers y listeners; nada de `app()` ni facades dentro de `Dominio/`.

## Alternativas descartadas

- **Clean Architecture ortodoxa** (entidad pura + repositorio por modelo): impuesto de mantenimiento que un solo desarrollador no puede sostener, sin retorno de valor proporcional a este tamaño de sistema.
- **Microservicios**: un desarrollador, un dominio cohesionado, transacciones que cruzan módulos (validar sesión toca `Operaciones` y `Finanzas`) — el monolito modular es la respuesta correcta a esta escala.

## Consecuencias

- Los límites de módulo coinciden con las fases de desarrollo de `docs/especificacion/especificacion_funcional_tecnica.md` (sección 15), lo que permite congelar las fases 3–8 sin contaminar la ruta crítica.
- Toda transición de estado con dinero (ver ADR de máquinas de estado en la especificación) pasa por un servicio de dominio explícito, nunca por un `UPDATE estado = ...` suelto en un controlador.
