# ADR 0042 — Registro del proveedor con datos de la persona y fotos del dron

**Estado:** aceptada, revisable · **Fecha:** 2026-11-24 · **Decide:** el dueño

> **Nota de retiro (26/11/2026):** el auxiliar se eliminó del sistema (rol, permisos `proveedores.auxiliar.*`, página del panel, endpoints `/api/proveedores/auxiliares`, documentos y calificación del auxiliar). Donde este ADR lo menciona, ya no aplica; las tablas `prv_auxiliares` y la columna `flo_equipos.auxiliar_id` siguen en el esquema, sin uso, hasta la reconsolidación de migraciones. Un reemplazo (integrantes del equipo del servicio) se define aparte.

## Contexto

El cliente elige entre pilotos que no conoce, y compara por precio, calificación y equipo. Necesita saber quién es el piloto y ver el dron que irá a su propiedad. El administrador necesita datos y fotos para aprobar.

## Decisión

1. **Datos de la persona.** El registro del proveedor (y el de cada piloto y auxiliar) pide `nombres`, `apellidos`, `documento_identidad`, `telefono` y foto de perfil, además de la licencia del piloto. Se guardan en `prv_pilotos` y `prv_auxiliares`.
2. **Datos del dron.** `marca`, `modelo`, `anio`, `numero_serie`, `capacidad_tanque_l` y `ancho_pasada_m`.
3. **Fotos del dron (`flo_dron_fotos`).** Tipos `frontal`, `lateral`, `placa_serie`, `con_tanque` y `otra`, comprimidas a menos de 300 KB. Las obligatorias para aprobar son un parámetro (por defecto `frontal`, `lateral` y `placa_serie`). El administrador aprueba o rechaza cada foto con motivo.
4. **Qué ve el cliente.** En cada oferta, el nombre y la foto del piloto, la calificación y los servicios del proveedor, y la marca, el modelo, el año, la capacidad y las fotos aprobadas del dron. Teléfono y documento del piloto, solo tras adjudicar.
5. **Aprobación.** Como en el ADR 0032: el proveedor queda `pendiente` y un administrador lo aprueba. Mientras no esté aprobado, ni ve el mapa ni ofrece.

## Alternativas descartadas

- **Fotos opcionales**: el cliente decide sin ver el equipo y el administrador aprueba sin verificar el número de serie.
- **Mostrar todas las fotos sin aprobar**: expone contenido no revisado al cliente.

## Consecuencias

La tabla `flo_dron_fotos` y columnas nuevas en `prv_pilotos` y `prv_auxiliares`; el archivo sigue la ruta por objeto del ADR 0026. Política en `docs/politicas/verificacion_proveedores.md`.
