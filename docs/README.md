# Documentación de `agrocom-api`

Este `docs/` separa dos cosas: lo que fue el camino hasta acá, y lo que rige hoy.

## `legacy/` — histórico, no se edita más

Los documentos originales de la fase de propuesta y primer diseño, tal como se escribieron, uno construido sobre el anterior sin que los previos se corrigieran. Se conservan porque documentan *por qué* se llegó a cada decisión (por ejemplo, el panel web pasó de React → Filament → AdminLTE+Livewire entre tres documentos legacy sucesivos). No son la fuente de verdad — están congelados.

## Estructura oficial vigente

- **`especificacion/especificacion_funcional_tecnica.md`** — qué hace el sistema y cómo se modela: alcance, arquitectura, roles y permisos, modelo de datos, máquinas de estado, endpoints, alertas. Es el contrato funcional+técnico.
- **`decisiones/`** — ADRs (Architecture Decision Records), uno por decisión de arquitectura, formato Contexto → Decisión → Alternativas descartadas → Consecuencias. Explican el *por qué* detrás de cada pieza del stack.
- **`negocio/ventana_al_negocio.md`** — el negocio de punta a punta (cadena comercial, economía del piloto, conflictos típicos). No cambia con las decisiones técnicas.
- **`gestion/`** — plan de sprints/HU/betas y el banco de preguntas por rol para capturar procesos de campo. Son instrumentos activos de trabajo, no decisiones congeladas.
- **`api/`** — contrato de API (`openapi.yaml`) cuando arranque el desarrollo del motor de sync.

## Qué reemplaza a qué

| Legacy | Reemplazado por |
|---|---|
| `Especificacion_Tecnica_Sistema_Fumigacion_v1.0.docx` | `especificacion/especificacion_funcional_tecnica.md` |
| `enfoque_desarrollo_sistema_fumigacion.md` | `especificacion/` (protocolo de sync, sección 2) + `decisiones/` (arquitectura) + `gestion/plan_sprints.md` (plan de ejecución) |
| `definicion_tecnica_repos_modulos_stack.md` | `decisiones/` (stack) + `decisiones/0006-gitflow-simplificado.md` (branching) |
| `decisiones_arquitectura_v2.md` | `decisiones/0001` a `0004` |
| `plan_sprints_hu_betas.md` | `gestion/plan_sprints.md` |
| `ventana_al_negocio_fumigacion.md` | `negocio/ventana_al_negocio.md` |
| `banco_preguntas_por_rol.md` | `gestion/banco_preguntas_por_rol.md` |

Ver también `CLAUDE.md` en la raíz del repo para las invariantes que cualquier agente de IA debe respetar al implementar, y `CONTRIBUTING.md` para el flujo de GitFlow simplificado.
