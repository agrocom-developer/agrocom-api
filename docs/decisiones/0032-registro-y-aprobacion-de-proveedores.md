# ADR 0032 — Registro y aprobación de proveedores, pilotos, auxiliares y drones

**Estado:** aceptada, revisable; complementada por el ADR 0046 (verificación de identidad por cuenta, y el cliente también se aprueba) · **Fecha:** 2026-11-23 · **Decide:** el dueño (autonomía delegada)

## Contexto

Un servicio de fumigación aérea implica riesgo para personas, cultivos vecinos y el ambiente. La plataforma debe verificar quién opera y con qué equipo antes de enviarle trabajo.

## Decisión

1. El proveedor independiente o tercero **se registra por sí mismo** en la app o la web. Su estado inicial es `pendiente`; solo un administrador lo pasa a `aprobado`.
2. El piloto de un proveedor registra a su auxiliar; un administrador lo aprueba.
3. Cada piloto, auxiliar y dron tiene documentos propios con vencimiento (identidad, licencia o habilitación, registro del dron, seguro de responsabilidad civil, certificado de fumigación y antecedentes según la normativa local). El registro pide además los datos de la persona y el modelo y las fotos del dron (ADR 0042).
4. Un documento vencido suspende de forma automática al sujeto; un aviso previo (30 y 7 días) se envía al proveedor.
5. El administrador deja motivo en cada rechazo, suspensión o baja; el sujeto puede reenviar o apelar. Un proveedor que no está `aprobado` no ve el mapa de solicitudes abiertas ni ofrece; ve solo un contador de cuántas hay abiertas (ADR 0046).
6. Los proveedores propios pasan por el mismo flujo.

## Alternativas descartadas

- Autoaprobación con verificación posterior: el riesgo físico no admite operar sin revisión.

## Consecuencias

Hace falta una cola de revisión de documentos en el panel y un proceso diario de vencimientos. Detalle en `docs/politicas/verificacion_proveedores.md`.
