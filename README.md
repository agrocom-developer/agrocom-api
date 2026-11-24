# Agrocom — Marketplace de fumigación con drones

**Agrocom SRL** conecta a quien necesita fumigar un cultivo con quien sabe y puede hacerlo. Un cliente solicita el servicio sobre su terreno; un proveedor (piloto con su dron y, si hace falta, su auxiliar) lo ejecuta; el cliente califica y el proveedor construye su reputación. Los drones cumplen el papel del vehículo. Agrocom capta a los clientes, es el nexo entre las partes y además es proveedor con su propia flota y sus propios pilotos.

Este repositorio es el backend (API REST) y el panel web de administración. Las apps móviles viven en el repo hermano [`agrocom-field`](https://github.com/agrocom-developer/agrocom-field).

## Cómo funciona

1. El cliente registra sus **terrenos** y crea una **solicitud de servicio**; el sistema le muestra una tarifa estimada antes de confirmar.
2. El motor de asignación ofrece el trabajo primero a los pilotos y drones **propios** de Agrocom y después a **terceros**, por zona, radio y calificación.
3. Un proveedor acepta (requiere red) y se forma el equipo: piloto, dron y auxiliar opcional.
4. En el lote no hay señal: el piloto y el auxiliar registran la sesión de vuelo, la mezcla y las evidencias **sin conexión** y sincronizan al volver a cobertura, sin duplicar jamás una hectárea.
5. El cliente firma el acta de conformidad o abre una disputa; después califica al proveedor, y el proveedor califica al cliente de forma privada.

En la primera versión no hay cobro dentro de la app: la comisión se liquida fuera de ella.

## Quién usa qué

| Quién | Dónde | Para qué |
|---|---|---|
| **Cliente** | App móvil `cliente` (online) | Terrenos, solicitudes, seguimiento, conformidad y calificación |
| **Piloto** | App móvil `piloto` (offline en el lote) | Ofertas, sesión de vuelo, incidencias y evidencias |
| **Auxiliar** | App móvil `auxiliar` (offline en el lote) | Mezclas y evidencias |
| **Proveedor** | Web y app | Flota, equipo, documentos y reputación |
| **Administrador y soporte** | Panel web | Aprobaciones, moderación, disputas, parámetros y métricas |

Una misma persona puede cumplir varios roles con un solo usuario, y opera bajo un único rol activo por sesión.

## Documentación

- [`docs/README.md`](docs/README.md): mapa de la documentación y orden de lectura.
- [`docs/especificacion/marketplace.md`](docs/especificacion/marketplace.md): la fuente de verdad funcional y técnica.
- [`docs/decisiones/`](docs/decisiones/): los porqués, como ADRs. Los 0027 a 0039 definen el marketplace.
- [`docs/politicas/`](docs/politicas/): cancelación, calificación, verificación de proveedores, seguridad, disputas y comisión.
- [`docs/glosario.md`](docs/glosario.md): el vocabulario del negocio y del campo.
- [`docs/api/openapi.yaml`](docs/api/openapi.yaml): el contrato de API que consumen las apps, generado desde el código.
- [`docs/gestion/estado-api.md`](docs/gestion/estado-api.md): qué quedó hecho en la API y qué falta.

## Para desarrolladores

- **Stack**: PHP 8.3 + Laravel 13 + PostgreSQL 16 (API REST y panel AdminLTE/Livewire). Monolito modular con un módulo por dominio.
- **Reglas para agentes de IA**: [`CLAUDE.md`](CLAUDE.md), las invariantes no negociables. Los subagentes especializados viven en [`.claude/agents/`](.claude/agents/).
- **Flujo de trabajo**: [`CONTRIBUTING.md`](CONTRIBUTING.md). GitFlow simplificado, todo por PR; el CI auto-fusiona con checks en verde (ver `.github/workflows/`).
- **Entorno local**: `docker compose up -d` levanta PHP, PostgreSQL y Mailpit (ver [`docs/gestion/entornos.md`](docs/gestion/entornos.md)).
- **Contrato de API**: `composer openapi` regenera `docs/api/openapi.yaml`; nunca se edita a mano.
