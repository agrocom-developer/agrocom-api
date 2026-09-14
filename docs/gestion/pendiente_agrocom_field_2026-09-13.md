# Pendiente para `agrocom-field` — ronda del dueño (13/9/2026)

**Este documento vive en `agrocom-api` a propósito.** Cuando se retome la
planificación de la app (`docs/gestion/plan_sprints.md` de `agrocom-field`,
Sprint 15 y siguientes), usar esto como base para que los cambios de un
repositorio y otro salgan sincronizados en vez de descubrirse por separado —
el pedido del dueño fue explícito en que quiere un proyecto integral, no dos
que se desalinean. No se tocó el repo `agrocom-field` en esta ronda: tiene su
propio ciclo de automatización (`bin/ciclo`) con una reconciliación de
estados sin commitear al momento de escribir esto, y mezclar cambios ahí sin
que su propio flujo los controle es justo el tipo de interferencia que
`docs/gestion/git-en-working-tree-compartido` (memoria del proyecto) pide
evitar.

Fuente: mismo Word/audios que
`docs/negocio/observaciones_operaciones_comercial_2026-09-13.md` — leer ese
documento primero para el contexto completo de negocio; acá solo se lista lo
que le toca a la app.

## 1. Directo del Word — UI del piloto

- **"SESIONES: OCULTAR" / "PAUSAS: OCULTAR"** en la pantalla "Crear
  Aplicaciones (Piloto)". El dueño pide que esas dos secciones/ítems no se
  vean ahí. **Ojo con una reserva de negocio importante** (ya señalada en la
  clasificación de `agrocom-api`): `ope_sesiones` es donde se valida el
  trabajo y se dispara el devengo (invariante 3 de `CLAUDE.md` del lado
  servidor) — si "ocultar Sesiones" implica que el flujo de validación deja
  de tener alguna superficie alcanzable, hay que confirmar antes de sacar el
  ítem que esa acción siga siendo posible desde otro lado. "Pausas" no está
  en la especificación funcional — es una feature del panel (PR #179 de
  `agrocom-api`) sin equivalente documentado en la app; ocultarla ahí no
  rompe nada del lado servidor.

## 2. Del audio del jefe — asignación visible al iniciar sesión

El jefe pidió (transmitido por el dueño) que la app tenga toda la información
necesaria para el inicio de la aplicación ya cargada al entrar, **para evitar
mandar el trabajo por WhatsApp**. Esto depende de una funcionalidad nueva del
lado servidor que todavía no existe: **HU-70** de `agrocom-api`
(`docs/gestion/plan_sprints.md`, Sprint 16; tarea 85 de `cola_tareas.md`) —
hoy no hay ninguna pantalla en el panel para que el jefe de campo asigne un
equipo/piloto a una orden de aplicación antes de que el piloto la ejecute.

**Cuando HU-70 esté integrada en `agrocom-api`**, el contrato cambia así (hoy
es una intención, no un contrato cerrado — confirmar contra el
`openapi.yaml` real de ese momento antes de implementar):

- `GET /api/sync/catalogo` va a traer, para el equipo del dispositivo que
  pregunta, el/los `Trabajo` ya generados con su lote y hectáreas resueltos
  — no solo órdenes vigentes sueltas como hoy.
- La app debería mostrar eso en la pantalla de inicio/dashboard del piloto
  apenas hace login o sincroniza, sin que tenga que buscar la orden ni
  preguntar por otro medio qué le toca.
- Falta decidir (cuando se planifique del lado `agrocom-field`) si esto es
  una HU nueva propia de ese repo o una ampliación de una ya existente del
  flujo de "lista y detalle offline" (HU-04, marcada "fuera del ciclo
  automático" en `cola_tareas.md` de `agrocom-field` por ser UI de esa app).

## 3. Nuevos tipos de dato que la app va a tener que enviar

Estos tres gaps de `agrocom-api` (Sprint 16) agregan información que **se
captura en el dispositivo** — la app necesita pantalla y tipo de registro de
sync nuevos para cada uno, coordinados con el contrato real que quede
definido del lado servidor:

| Gap en `agrocom-api` | Qué necesita capturar la app |
|---|---|
| **HU-78** (revierte CR-01: registro de mezcla) | Producto y cantidad cargados en el caldo (Glifosato, 24D, litros de agua, Urea, etc.) al crear una aplicación — nuevo tipo de registro de sync, análogo a `recepcion_caldo` |
| **HU-79** (tipo sólido/líquido en la orden) | La pantalla de creación de aplicación necesita mostrar campos distintos según `tipo_insumo` de la orden: **kilos por vuelo** si es sólido, **Ph agua / Ph de la calda / litros por hectárea** si es líquido — hoy la app no distingue esto porque el servidor tampoco lo modela |
| **HU-80** (reporte de equipos) | Cantidad de ciclos de batería y ciclo actual (dato que hoy vive en `man_baterias` del panel, la app solo necesita mostrarlo o dejar que el auxiliar lo confirme), horas de vuelo del dron, y evidencia fotográfica nueva: foto de control, foto de cada ciclo de batería y balanceo, foto del dron limpio — mismo mecanismo de evidencias que ya usa la app (TE-07, `EvidenciaSyncEngine`), con tipos nuevos |
| Detalle menor (mismo Word) | "Ráfagas (km/h)" en Clima, además del viento sostenido que ya se captura |

## 4. Qué NO es de acá

Todo lo del Word/audios sobre Comercial (contratos, clientes, propiedades,
lotes, campañas) es 100% panel web — no toca la app. Se implementa del lado
`agrocom-api`, sin ningún cambio en `agrocom-field`.
