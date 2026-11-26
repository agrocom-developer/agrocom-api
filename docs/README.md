# Documentación de `agrocom-api`

Este `docs/` contiene la documentación vigente de Agrocom, un marketplace de servicios de fumigación con drones.

## Estructura

- **`especificacion/marketplace.md`**: la fuente de verdad funcional y técnica. Actores, flujo, modelo de datos, máquinas de estado, negociación, endpoints, eventos, notificaciones, métricas y alcance de la primera versión. **`especificacion/negociacion-contrato.md`** lista con precisión los estados, las reglas y los recursos para implementar.
- **`decisiones/`**: ADRs (contexto, decisión, alternativas descartadas, consecuencias). Los ADR 0027 a 0046 definen el marketplace:
  - 0027 solicitud de servicio
  - 0028 proveedores propio, independiente y tercero
  - 0029 dron como vehículo con dueño
  - 0030 negociación oferta-demanda
  - 0031 calificación
  - 0032 registro y aprobación de proveedores
  - 0033 cancelación sin penalidad automática y reclamos
  - 0034 precio acordado y pago fuera de la app
  - 0035 retiro de la operación interna (finanzas, inventario y mantenimiento de flota)
  - 0036 dominio de proveedores y flota: módulos, roles, registro público y aprobación
  - 0037 solicitud de servicio y ejecución: la ejecución cuelga de una solicitud adjudicada y se retira el modelo de contratos
  - 0038 motor de negociación: ofertas, rondas, adjudicación atómica y vencimientos
  - 0039 calificaciones, reclamos y métricas: cierre del servicio, reputación ponderada, moderación, monto ajustado y panel de métricas
  - 0040 seguimiento: fotos de avance, mensajes y ubicación del piloto
  - 0041 mapa de solicitudes abiertas y notificaciones con preferencias
  - 0042 registro del proveedor con datos de la persona y fotos del dron
  - 0043 contactos de referencia del cliente
  - 0044 alta manual en el panel
  - 0045 mercado sin zonas: filtros combinados, avisos por oleadas, etapa de negociación y solicitud sin vencimiento (reemplaza en parte 0030, 0034, 0038 y 0041)
  - 0046 cuentas separadas por app (cliente y proveedor), persona única, verificación de identidad y roles del panel (Administrador y Operador)
- **`politicas/`**: una política por tema, que operan lo decidido: `negociacion.md`, `precio_y_pago.md`, `cancelacion.md`, `calificacion.md`, `verificacion_proveedores.md`, `seguimiento_y_privacidad.md`, `seguridad.md`, `disputas.md`.
- **`gestion/`**: cola de tareas del ciclo automático, `estado-api.md` (qué está hecho en la API y qué falta), entornos, automatización del desarrollo y el plan de homogeneización del panel; `respuestas_campo/capturas_rc/` guarda las capturas del control remoto del dron.
- **`diseno/`**: sistema de diseño del panel (tokens CSS, catálogo Atomic Design) y guía de construcción de pantallas.
- **`api/openapi.yaml`**: contrato de API generado desde el código (ADR 0014); no se edita a mano.
- **`api/ejemplos/`**: intercambios reales de la API (petición y respuesta) que las apps usan como fixtures de contrato; se regeneran con `bin/generar-ejemplos`.
- **`glosario.md`**: vocabulario de negocio y técnico.

## Cómo leer

1. `especificacion/marketplace.md` para el producto.
2. `decisiones/0027` a `0039`, `0045` y `0046` para el porqué de cada pieza.
3. `politicas/` para las reglas de operación.
4. `glosario.md` ante cualquier término.

Las invariantes para agentes están en `CLAUDE.md` en la raíz; el flujo de ramas, en `CONTRIBUTING.md`.
