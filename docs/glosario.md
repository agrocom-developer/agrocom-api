# Glosario del proyecto

**Agrocom SRL · Documento oficial vigente**

Referencia rápida de vocabulario — de negocio y técnico — para que cualquier persona o agente de IA nuevo en el proyecto entienda un término sin tener que leer toda la especificación. Cuando un término tiene definición formal en otro documento, se referencia en vez de repetirla completa.

## Negocio y operación

| Término | Definición | Referencia |
|---|---|---|
| **Lote** | Superficie física dentro de un campo, con hectáreas y geometría propias, unidad sobre la que se aplica una orden | `docs/especificacion/...` §4.1 |
| **Trabajo** | Un lote en una aplicación concreta; agrupa una o más sesiones hasta cubrir sus hectáreas | especificación §4.3 |
| **Sesión** | Unidad de trabajo continua de un piloto con un dron dentro de un trabajo; se abre, se ejecuta, se cierra y se valida | especificación §4.3, §5 |
| **Orden de aplicación** | Instrucción del agrónomo (producto, dosis, L/ha) que habilita abrir un trabajo; sin orden vigente no se vuela | especificación §4.3 |
| **Receta de mezcla** | Secuencia de incorporación de productos definida por el agrónomo para una orden; Agrocom la ejecuta y documenta, no la modifica | especificación §4.3, §7 |
| **Mezcla** | Un tanque de caldo preparado según la receta; cada mezcla queda ligada a una sesión y a las hectáreas que cubrió | especificación §4.3, §7 |
| **Recarga** | Evento de reabastecimiento de caldo y cambio de batería durante una sesión | especificación §4.3 |
| **Incidencia** | Evento anómalo registrado durante una sesión (caldo, ESC, batería, mecánica, clima) con evidencia | especificación §4.3 |
| **Acta (de conformidad)** | Documento firmado por el agrónomo, por lote, que certifica lo aplicado — habilita la factura | especificación §4.3, §9 |
| **Devengo** | Monto que un piloto o auxiliar gana por una sesión validada; se genera solo al validar, nunca al cerrar | especificación §4.4, §5 |
| **Validación** | Acto de un jefe de campo o encargado (nunca el propio piloto de esa sesión) que confirma una sesión y dispara el devengo | especificación §5 |
| **Rendición** | Registro de gastos de campo con comprobante, presentado por el jefe de campo | especificación §4.4 |
| **Planilla** | Liquidación mensual de pagos para los cuatro roles operativos | especificación §11 |
| **Base** | Punto físico de operación (campamento, conectividad Starlink) desde donde se despliega al lote | `docs/negocio/ventana_al_negocio.md` §3 |
| **Hectárea acumulada (de partida)** | Valor que registra el piloto entrante en un relevo — evita el doble conteo cuando la misión de DJI se retoma | especificación §5 |
| **Solape** | Superposición real entre pasadas de vuelo; tolerado hasta un parámetro configurable antes de disparar `observado` | especificación §5, §16 |
| **EPP** | Equipo de protección personal del auxiliar (guantes, respirador, antiparras, ropa impermeable); se confirma al iniciar cada mezcla | especificación §7.3 |

## Técnico

| Término | Definición | Referencia |
|---|---|---|
| **`uuid_cliente`** | Identificador único generado en el dispositivo de campo antes de sincronizar; garantiza idempotencia | especificación §2.1 |
| **Outbox** | Patrón de cola local (`cola_sync`) donde toda escritura offline se encola antes de sincronizar | especificación §2.1 |
| **Idempotencia** | Propiedad de que reintentar la misma operación no cambia el resultado; se logra con `UNIQUE (uuid_cliente)` en base, no en el código | ADR 0001, especificación §2.1 |
| **`sec_permission`** | Código de permiso abstracto (`operaciones.sesion.validar`) que el menú referencia — no al revés | ADR 0004 |
| **Policy** | Clase de Laravel que responde "¿puede este usuario, sobre ESTE registro?" — complementa a `sec_permission`, que responde "¿puede en general?" | ADR 0004 |
| **ADR** (Architecture Decision Record) | Documento corto en `docs/decisiones/` que registra una decisión de arquitectura: contexto, decisión, alternativas descartadas, consecuencias | `docs/README.md` |
| **`feature/*`** | Rama de GitFlow simplificado para una historia de usuario o tarea técnica; nace y muere en `develop` | ADR 0006 |
| **Soft delete** | Borrado lógico (`deleted_at`); ningún módulo hace `DELETE` físico salvo excepción justificada | ADR 0007 |
| **Bitácora (de auditoría)** | Registro transversal de quién hizo qué, cuándo, sobre qué entidad — no limitado a un módulo | ADR 0007 |
| **Atomic Design** | Metodología de componentes del panel: atoms/molecules/organisms/templates/pages en Blade | ADR 0002 |
| **Token (de diseño)** | Variable CSS (custom property) que representa un color/espaciado/tipografía — nunca se hardcodea un valor literal | ADR 0002 |
