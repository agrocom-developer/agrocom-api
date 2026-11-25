# ADR 0043 — Contactos de referencia del cliente

**Estado:** aceptada, revisable · **Fecha:** 2026-11-25 · **Decide:** el dueño

## Contexto

El cliente puede ser una persona o una empresa. Con una empresa, el proveedor no coordina con la razón social sino con una persona: el encargado de campo, el administrador. Hasta ahora el servicio adjudicado entregaba al piloto el nombre de la empresa y su teléfono.

## Decisión

1. **Tabla `sol_cliente_contactos`**: `cliente_id`, `nombre`, `telefono`, `cargo`, `es_principal` y baja lógica. Un cliente persona puede tener contactos; una empresa tiene siempre al menos uno vigente.
2. **Un solo principal vigente** por cliente (índice único parcial). El primer contacto lo es; marcar otro releva al anterior; dar de baja al principal lo releva el más antiguo que quede.
3. **Reglas en un caso de uso** (`GestionarContactos`): la API del cliente, el registro y el panel lo comparten. Una empresa no puede dar de baja a su último contacto ni registrarse sin ninguno; si el registro público no envía `contactos`, queda como contacto principal quien registra la cuenta.
4. **La solicitud elige su contacto** (`sol_solicitudes_servicio.contacto_id`, anulable): el elegido (vigente y del propio cliente) o, sin elegir, el principal de la empresa. Una baja posterior no cambia lo ya elegido.
5. **Qué ve el proveedor.** Solo tras adjudicar (política de datos del cliente), el catálogo de servicios asignados trae `contacto` con el nombre, el teléfono y el cargo de la persona, y la razón social si el cliente es empresa. Una solicitud sin contacto elegido (anterior a esta decisión) sigue entregando el nombre y el teléfono del cliente.
6. **Migración que solo crea.** Cada empresa existente recibe como contacto principal a la persona de su cuenta.

## Alternativas descartadas

- **Restaurar `com_cliente_contactos`**: sus datos se perdieron al retirar el esquema anterior y traía tipos de contacto que el marketplace no usa.
- **Contacto obligatorio en cada solicitud de una empresa**: rompe a las apps instaladas; el principal como valor por defecto es compatible.

## Consecuencias

`GET /api/clientes/mi` devuelve `contactos`; `POST /api/solicitudes` acepta `contacto_id` y la solicitud devuelve `contacto_id` y `contacto`; el campo `contacto` del catálogo suma `cargo` y `empresa`. Los cambios son compatibles hacia atrás. El panel muestra y edita los contactos al registrar a mano a un cliente (alta manual por el panel).
