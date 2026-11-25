# Agrocom — Marketplace de fumigación con drones

**Agrocom SRL** conecta a quien necesita fumigar un cultivo con quien sabe y puede hacerlo. Un cliente publica el servicio sobre su propiedad con un precio por hectárea; los pilotos lo ven en un mapa y aceptan el precio o contraofertan; el cliente elige y, al terminar, califica. El precio nace de la negociación entre las partes. Los drones cumplen el papel del vehículo. Agrocom capta a los clientes, es el nexo entre las partes y el registro del estado y del resultado, y además es proveedor con su propia flota y sus propios pilotos.

Este repositorio es el backend (API REST) y el panel web de administración. Las apps móviles viven en el repo hermano [`agrocom-field`](https://github.com/agrocom-developer/agrocom-field).

## Cómo funciona

1. El cliente marca su propiedad y sus lotes en el mapa y publica una **solicitud** con las hectáreas, los datos previos y un **precio por hectárea**.
2. Los pilotos ven las solicitudes abiertas en un **mapa** (por zona: valles, Chiquitania y otras), revisan el estado de la propiedad y **aceptan el precio o contraofertan**.
3. El cliente compara las ofertas (precio, calificación, dron, distancia), negocia ronda por ronda y **elige una**: la solicitud queda adjudicada y se abren el contacto y el chat.
4. El piloto marca el inicio, **sube fotos y marca su avance**, sin conexión si hace falta, y al final cierra con las hectáreas ejecutadas. El cliente ve las fotos, el chat y la última ubicación del piloto.
5. El sistema muestra el **precio final** (hectáreas ejecutadas por precio acordado). Después el cliente califica al proveedor y el proveedor califica al cliente de forma privada; cualquiera puede abrir una disputa.

La app no cobra: el pago se hace fuera de ella.

## Quién usa qué

| Quién | Dónde | Para qué |
|---|---|---|
| **Cliente** | App móvil `cliente` (online) | Propiedad, solicitudes, comparar y elegir ofertas, seguimiento, chat y calificación |
| **Piloto** | App móvil `piloto` (offline en el lote) | Mapa de solicitudes, ofertas, avance, fotos y chat |
| **Proveedor** | Web y app | Flota, equipo, documentos y reputación |
| **Administrador y soporte** | Panel web | Aprobaciones, moderación, disputas, parámetros y métricas |

Una misma persona puede cumplir varios roles con un solo usuario, y opera bajo un único rol activo por sesión.

## Documentación

- [`docs/README.md`](docs/README.md): mapa de la documentación y orden de lectura.
- [`docs/especificacion/marketplace.md`](docs/especificacion/marketplace.md): la fuente de verdad funcional y técnica.
- [`docs/decisiones/`](docs/decisiones/): los porqués, como ADRs. Los 0027 a 0042 definen el marketplace.
- [`docs/politicas/`](docs/politicas/): negociación, precio y pago, cancelación, calificación, verificación de proveedores, seguimiento y privacidad, seguridad y disputas.
- [`docs/glosario.md`](docs/glosario.md): el vocabulario del negocio y del campo.
- [`docs/api/openapi.yaml`](docs/api/openapi.yaml): el contrato de API que consumen las apps, generado desde el código.
- [`docs/gestion/estado-api.md`](docs/gestion/estado-api.md): qué quedó hecho en la API y qué falta.

## Para desarrolladores

- **Stack**: PHP 8.3 + Laravel 13 + PostgreSQL 16 (API REST y panel AdminLTE/Livewire). Monolito modular con un módulo por dominio.
- **Reglas para agentes de IA**: [`CLAUDE.md`](CLAUDE.md), las invariantes no negociables. Los subagentes especializados viven en [`.claude/agents/`](.claude/agents/).
- **Flujo de trabajo**: [`CONTRIBUTING.md`](CONTRIBUTING.md). GitFlow simplificado, todo por PR; el CI auto-fusiona con checks en verde (ver `.github/workflows/`).
- **Entorno local**: `docker compose up -d` levanta PHP, PostgreSQL y Mailpit (ver [`docs/gestion/entornos.md`](docs/gestion/entornos.md)).
- **Demo para las apps**: `bin/demo up` levanta la API con datos de ejemplo en `0.0.0.0:8000` (ver [`docs/gestion/demo-local.md`](docs/gestion/demo-local.md)).
- **Contrato de API**: `composer openapi` regenera `docs/api/openapi.yaml`; nunca se edita a mano.
