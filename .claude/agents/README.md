# Agentes especializados de `agrocom-api`

Doce subagentes, dos grupos según el tipo de trabajo — para no gastar tokens de un modelo grande en tareas mecánicas.

## Ejecución / instrucciones (modelo económico: `claude-haiku-4-5-20251001`)

Trabajo que sigue un patrón ya definido en un ADR o convención — no requiere decidir nada nuevo, solo aplicarlo correctamente.

| Agente | Por qué es "ejecución" |
|---|---|
| `frontend` | Ensambla pantallas con el catálogo de componentes que `design-ui` ya definió (ADR 0002) |
| `estandares-programacion` | Verifica estilo/convención/tests contra reglas ya fijas en `CLAUDE.md` |
| `distribucion` | Configura CI/CD siguiendo los patrones ya establecidos en `.github/workflows/` |
| `memoria-contexto` | Lee y actualiza `docs/gestion/estado_proyecto.md`, tarea mecánica de snapshot |

## Juicio / diseño / coordinación (hereda el modelo de la sesión — el más capaz)

Trabajo que implica decidir algo nuevo, evaluar trade-offs, o coordinar entre partes.

| Agente | Por qué necesita más criterio |
|---|---|
| `arquitectura` | Decide encaje de código nuevo y redacta ADRs — decisiones que se congelan y cuesta revertir |
| `backend` | Implementa reglas de negocio reales (dinero, estados, sync) — errores acá cuestan caro |
| `design-ui` | Define el sistema de diseño en sí (tokens, mezcla Bootstrap/Material) — lo que `frontend` después ejecuta |
| `modelo-datos` | Integridad del esquema, decisiones que son costosas de deshacer una vez hay datos reales |
| `modulos-roles` | Seguridad — un error de criterio acá es un agujero de permisos |
| `negocio` | Clasificar respuestas de campo (CONFIRMADO/CORREGIDO/DESCUBIERTO) requiere criterio, no solo lectura |
| `orquestador` | Decide qué agentes delegar y en qué orden para una tarea que cruza capas |
| `validador` | Revisa el trabajo de los demás contra `CLAUDE.md`/ADRs antes de cerrar un cambio |

## Cuándo usar `orquestador` vs. ir directo al agente

- Tarea claramente de una sola capa (ej. "agregá un índice a la tabla X"): invocá el agente correspondiente directo (`modelo-datos`).
- Tarea que cruza capas (ej. "implementá la HU de validación cruzada", que toca `modelo-datos` + `backend` + `modulos-roles` + `frontend`): pasá primero por `orquestador` para que ordene la delegación.
- Después de que cualquier agente termine un cambio no trivial, antes de darlo por cerrado: `validador`.
