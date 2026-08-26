# Documentación de `agrocom-api`

Este `docs/` contiene solo la documentación vigente. Los documentos originales de la fase de propuesta (el antiguo `docs/legacy/`) se eliminaron el 26/8/2026 al arrancar el desarrollo: ya estaban íntegramente reemplazados por la estructura oficial (ver la tabla al final) y siguen disponibles en el historial de git (`git log --oneline -- docs/legacy/`).

## Estructura oficial vigente

- **`especificacion/`** — el contrato funcional+técnico:
  - `especificacion_funcional_tecnica.md` — qué hace el sistema y cómo se modela: alcance, arquitectura, roles y permisos, modelo de datos, máquinas de estado, endpoints, alertas. Fuente única del *qué*.
  - `requerimientos_sistema.md` — RF/RNF verificables derivados de las respuestas de campo, con prioridad por fase.
  - `insumos_modelo_datos.md` — preparación de la consolidación del modelo: módulos potenciales, procesos críticos, ajustes a máquinas de estado y separación de superficies (pendiente de las capturas del RC).
- **`decisiones/`** — ADRs (Architecture Decision Records), uno por decisión de arquitectura, formato Contexto → Decisión → Alternativas descartadas → Consecuencias. Explican el *por qué* detrás de cada pieza del stack.
- **`negocio/`** — el negocio, no la técnica:
  - `ventana_al_negocio.md` — el negocio de punta a punta (cadena comercial, economía del piloto, conflictos típicos).
  - `politicas/` — políticas y lógica de negocio por rol (piloto, auxiliar, jefe de campo, encargado, agrónomo, dueño, cliente), derivadas de las respuestas de campo del 25/8/2026.
  - `flujo_base_y_excepciones.md` — el flujo operativo de la orden al cobro, con sus excepciones reales ancladas por etapa.
  - `automatizacion_sistematizacion.md` — qué automatiza el sistema, qué sistematiza y qué queda humano.
  - `alcance_objetivos.md` — objetivos medibles del proyecto y alcance v1 ajustado por el campo.
- **`gestion/`** — plan de sprints/HU/betas, el banco de preguntas por rol, y `respuestas_campo/` (los CSV crudos de las encuestas + `analisis_clasificacion.md`, la clasificación CONFIRMADO/CORREGIDO/DESCUBIERTO consolidada con la matriz de límites y la agenda de la reunión de cierre). Son instrumentos activos de trabajo, no decisiones congeladas.
- **`api/`** — contrato de API (`openapi.yaml`) cuando arranque el desarrollo del motor de sync.

## Qué reemplaza a qué

Mapa histórico: cada documento de propuesta (hoy solo en el historial de git) y el documento oficial que lo reemplazó.

| Legacy (eliminado) | Reemplazado por |
|---|---|
| `Especificacion_Tecnica_Sistema_Fumigacion_v1.0.docx` | `especificacion/especificacion_funcional_tecnica.md` |
| `enfoque_desarrollo_sistema_fumigacion.md` | `especificacion/` (protocolo de sync, sección 2) + `decisiones/` (arquitectura) + `gestion/plan_sprints.md` (plan de ejecución) |
| `definicion_tecnica_repos_modulos_stack.md` | `decisiones/` (stack) + `decisiones/0006-gitflow-simplificado.md` (branching) |
| `decisiones_arquitectura_v2.md` | `decisiones/0001` a `0004` |
| `plan_sprints_hu_betas.md` | `gestion/plan_sprints.md` |
| `ventana_al_negocio_fumigacion.md` | `negocio/ventana_al_negocio.md` |
| `banco_preguntas_por_rol.md` | `gestion/banco_preguntas_por_rol.md` |

Ver también `CLAUDE.md` en la raíz del repo para las invariantes que cualquier agente de IA debe respetar al implementar, y `CONTRIBUTING.md` para el flujo de GitFlow simplificado.
