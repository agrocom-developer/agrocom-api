# Política de verificación de proveedores, pilotos y drones

**Agrocom SRL · Vigente** · Decisión: ADR 0032 · Especificación: `docs/especificacion/marketplace.md` §4.3, §5.2, §9.1

## Qué se verifica

| Sujeto | Documentos obligatorios |
|---|---|
| Proveedor | Identidad del titular, documento fiscal, domicilio de operación, seguro de responsabilidad civil que cubra aplicación de agroquímicos |
| Piloto | Nombres, apellidos, teléfono y foto de perfil; identidad, licencia o habilitación de piloto remoto vigente, certificado de aplicador de agroquímicos si la normativa local lo exige, antecedentes |
| Dron | Registro o matrícula ante la autoridad aeronáutica, habilitación para uso agrícola, número de serie, marca y modelo, fotos del equipo (frontal, lateral y placa de serie como mínimo), seguro vigente |

La normativa local puede añadir requisitos; el catálogo de documentos se ajusta por parámetro.

## Proceso

1. El solicitante carga los documentos. Estado `pendiente`.
2. Un administrador revisa cada documento y lo aprueba o rechaza con motivo.
3. Con proveedor, al menos un piloto y un dron aprobados (el dron con sus fotos obligatorias aprobadas), el proveedor pasa a `aprobado` y ve el mapa de solicitudes abiertas y puede ofertar.
4. Los proveedores propios pasan por el mismo proceso.

## Vigencia

- Avisos 30 y 7 días antes del vencimiento.
- Documento vencido: el sujeto queda suspendido de forma automática hasta que se cargue uno vigente.
- Un dron o piloto suspendido saca a su equipo de las ofertas.

## Reverificación y baja

- Revisión anual de toda la documentación.
- Suspensión inmediata por incidente grave probado, falsedad documental o reclamo de la autoridad.
- Baja definitiva por fraude, daño intencional o reincidencia en suspensión.
- Toda suspensión o baja incluye motivo, autor y vía de apelación.

## Privacidad

Los documentos se guardan con acceso restringido a administradores y soporte (ADR 0026). El cliente ve el nombre, la foto de perfil, la calificación, el modelo y las fotos aprobadas del dron y que el proveedor está verificado, no los documentos. El teléfono y el documento del piloto los ve solo tras adjudicar.
