# agrocom-api

Backend y panel administrativo del Sistema de Gestión de Operaciones de Fumigación de **Agrocom SRL**: API REST para las apps de campo, panel web multi-rol (jefe de campo, encargado de operaciones, dueño) y portal de solo lectura para el cliente (agrónomo/dueño del campo).

Repositorio hermano: **[`agrocom-field`](https://github.com/agrocom-developer/agrocom-field)** — app Flutter para el remote control (RC) de los drones DJI (piloto) y celular Android (auxiliar). El contrato entre ambos vive en `docs/api/` de este repo.

## Stack

- **Backend**: PHP 8.3 + Laravel 12 + PostgreSQL 16
- **Panel web**: AdminLTE + Blade + Livewire, componentes propios en Atomic Design (Bootstrap + Material Design), theming configurable por usuario
- **Autenticación**: Sanctum (apps, token por dispositivo) + sesión web (paneles), permisos vía modelo `sec_*` (permiso abstracto + multi-rol)
- **Arquitectura**: monolito modular, Clean Architecture pragmática por módulo de dominio

Detalle completo y el porqué de cada decisión en `docs/`.

## Documentación

- **`docs/especificacion/especificacion_funcional_tecnica.md`** — qué hace el sistema y cómo se modela (fuente de verdad funcional/técnica).
- **`docs/decisiones/`** — ADRs: por qué se eligió cada pieza del stack.
- **`docs/negocio/ventana_al_negocio.md`** — el negocio de punta a punta.
- **`docs/gestion/`** — plan de sprints y captura de procesos de campo.
- **`docs/legacy/`** — documentos históricos de la fase de propuesta, congelados (ver `docs/README.md` para el mapa de qué reemplaza a qué).
- **`CLAUDE.md`** — invariantes no negociables para cualquier agente de IA que implemente en este repo.
- **`CONTRIBUTING.md`** — flujo de GitFlow simplificado.

## Desarrollo

Un solo desarrollador con agentes de IA como implementadores principales. Ver `CONTRIBUTING.md` para el flujo de ramas y `docs/gestion/plan_sprints.md` para el plan de sprints vigente.
