# Agrocom — Sistema de Gestión de Operaciones de Fumigación

**Agrocom SRL** fumiga cultivos con drones DJI Agras en Santa Cruz, Bolivia. Vende **hectáreas bien aplicadas y bien documentadas**: el cliente no compra horas de dron ni litros de caldo — compra que su cultivo reciba la dosis que su agrónomo ordenó, en la ventana que la plaga exige, con prueba de que ocurrió.

Este sistema existe para tres cosas:

1. **Que cada persona cobre exactamente lo que trabajó.** Pilotos y auxiliares ganan por hectárea validada; el registro por sesión garantiza que nadie cobre hectáreas de otro ni pierda las suyas al entregar un lote a medias.
2. **Que el cliente reciba exactamente lo que se le factura.** Cada hectárea se reconstruye de punta a punta: qué se aplicó, dónde, cuándo, en qué condiciones, con qué mezcla y con qué prueba (la captura del control remoto y el acta firmada).
3. **Que el dueño vea dónde se gana y se pierde la plata.** Gastos por rubro, costo por hectárea, repuestos, pausas explicadas — lo que hoy vive en WhatsApp, Excel y la memoria.

## Cómo trabaja Agrocom (y qué registra el sistema)

El agrónomo del cliente emite la **orden de aplicación** (producto, dosis, litros por hectárea). El jefe de campo planifica el día: qué lotes, qué pareja piloto/auxiliar. En el lote, el piloto mapea la misión y vuela en tandas de 10–12 minutos, mientras el auxiliar sostiene el ritmo: cambia baterías, recarga caldo, alimenta el generador. Al cerrar cada lote, el piloto captura la pantalla del control — la evidencia que hoy ya zanja toda discusión de hectáreas. El jefe **valida** lo volado, el agrónomo **firma el acta**, y de ahí salen el reporte, la factura y la planilla de pagos.

El flujo completo con sus excepciones reales (lluvia, caldo cortado, dron caído, demoras del cliente) está en [`docs/negocio/flujo_base_y_excepciones.md`](docs/negocio/flujo_base_y_excepciones.md).

## Quién usa qué

| Quién | Dónde | Para qué |
|---|---|---|
| **Piloto** | App en el control remoto del dron | Ver órdenes y lotes, cerrar sesiones con evidencia, registrar pausas e incidencias — siempre con el dron en tierra |
| **Auxiliar** | App en su celular | Mezclas, recargas, baterías, combustible del generador |
| **Jefe de campo** | Panel web | Planificar el día, validar lo volado, rendir la caja chica |
| **Encargado de operaciones** | Panel web | Gastos, repuestos, reportes al cliente |
| **Dueño** | Panel web | Aprobar anticipos y planilla, ver el estado de resultados |
| **Cliente** (dueño del campo y su agrónomo) | Portal de solo lectura | Reportes, actas firmadas, historial de sus aplicaciones |

En el lote no hay señal (la conectividad Starlink está en el campamento base), así que las apps de campo funcionan **sin internet** y sincronizan al volver a cobertura — sin duplicar jamás una hectárea.

Una misma persona puede cumplir varios roles con un solo usuario — en la operación real el dueño también vuela y valida (pero nunca sus propias sesiones).

## La lógica de negocio, documento por documento

Todo el conocimiento del negocio salió de la gente que hace el trabajo: en agosto de 2026 los cinco roles operativos respondieron cuestionarios sobre su día real, sus excepciones y sus números. De ahí derivan:

- [`docs/negocio/ventana_al_negocio.md`](docs/negocio/ventana_al_negocio.md) — el negocio de punta a punta: la cadena comercial, la economía del piloto, los conflictos típicos.
- [`docs/negocio/politicas/`](docs/negocio/politicas/) — políticas y reglas por rol: piloto, auxiliar, jefe de campo, encargado, agrónomo, dueño y cliente.
- [`docs/negocio/flujo_base_y_excepciones.md`](docs/negocio/flujo_base_y_excepciones.md) — de la orden al cobro, con las excepciones ancladas a cada etapa.
- [`docs/negocio/automatizacion_sistematizacion.md`](docs/negocio/automatizacion_sistematizacion.md) — qué hace el sistema solo, qué estructura, y qué decisiones siguen siendo humanas.
- [`docs/negocio/alcance_objetivos.md`](docs/negocio/alcance_objetivos.md) — para qué existe el proyecto, con metas medibles.
- [`docs/glosario.md`](docs/glosario.md) — el vocabulario del negocio y del campo (qué es un chaco, la calda, las cortinas, el sereno).
- [`docs/gestion/respuestas_campo/`](docs/gestion/respuestas_campo/) — las respuestas crudas de las encuestas y su análisis consolidado.

## Estado del proyecto

**Fase de fundación**: la documentación de negocio y las decisiones de arquitectura están completas; el código de la aplicación aún no arranca. Lo procesado hasta ahora y lo que sigue (capturas del control remoto, reunión de cierre con el equipo, primer sprint de desarrollo) está siempre al día en [`docs/gestion/estado_proyecto.md`](docs/gestion/estado_proyecto.md).

## Para desarrolladores

- **Stack**: PHP 8.3 + Laravel 12 + PostgreSQL 16 (API REST y panel AdminLTE/Livewire); las apps de campo son Flutter, en el repo hermano [`agrocom-field`](https://github.com/agrocom-developer/agrocom-field). Monolito modular con un módulo por dominio de negocio.
- **Fuente de verdad técnica**: [`docs/especificacion/`](docs/especificacion/) (especificación funcional, requerimientos, insumos del modelo de datos) y [`docs/decisiones/`](docs/decisiones/) (los porqués, como ADRs).
- **Reglas para agentes de IA**: [`CLAUDE.md`](CLAUDE.md) — las invariantes no negociables. Los subagentes especializados viven en [`.claude/agents/`](.claude/agents/).
- **Flujo de trabajo**: [`CONTRIBUTING.md`](CONTRIBUTING.md) — GitFlow simplificado, todo por PR; el CI protege `docs/legacy/` y auto-fusiona con checks en verde (ver `.github/workflows/`).
- **Entorno local**: `docker compose up -d` levanta PHP, PostgreSQL y Mailpit (ver [`docs/gestion/entornos.md`](docs/gestion/entornos.md)).
