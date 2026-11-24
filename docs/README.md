# Documentación de `agrocom-api`

Este `docs/` contiene la documentación vigente de Agrocom, un marketplace de servicios de fumigación con drones.

## Estructura

- **`especificacion/marketplace.md`**: la fuente de verdad funcional y técnica. Actores, flujo, modelo de datos, máquinas de estado, asignación, endpoints, eventos, notificaciones, métricas y alcance de la primera versión.
- **`decisiones/`**: ADRs (contexto, decisión, alternativas descartadas, consecuencias). Los ADR 0027 a 0036 definen el marketplace:
  - 0027 solicitud de servicio
  - 0028 proveedores propio, independiente y tercero
  - 0029 dron como vehículo con dueño
  - 0030 asignación con prioridad a propios
  - 0031 calificación
  - 0032 registro y aprobación de proveedores
  - 0033 cancelación y disputas
  - 0034 comisión y pagos
  - 0035 retiro de la operación interna (finanzas, inventario y mantenimiento de flota)
  - 0036 dominio de proveedores y flota: módulos, roles, registro público y aprobación
- **`politicas/`**: una política por tema, que operan lo decidido: `cancelacion.md`, `calificacion.md`, `verificacion_proveedores.md`, `seguridad.md`, `disputas.md`, `comision.md`.
- **`gestion/`**: cola de tareas del ciclo automático, entornos, automatización del desarrollo y el plan de homogeneización del panel; `respuestas_campo/capturas_rc/` guarda las capturas del control remoto del dron.
- **`diseno/`**: sistema de diseño del panel (tokens CSS, catálogo Atomic Design) y guía de construcción de pantallas.
- **`api/openapi.yaml`**: contrato de API generado desde el código (ADR 0014); no se edita a mano.
- **`glosario.md`**: vocabulario de negocio y técnico.

## Cómo leer

1. `especificacion/marketplace.md` para el producto.
2. `decisiones/0027` a `0036` para el porqué de cada pieza.
3. `politicas/` para las reglas de operación.
4. `glosario.md` ante cualquier término.

Las invariantes para agentes están en `CLAUDE.md` en la raíz; el flujo de ramas, en `CONTRIBUTING.md`.
