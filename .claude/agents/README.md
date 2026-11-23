# Agentes especializados de `agrocom-api`

Diecisiete subagentes del motor y el panel web del marketplace de fumigación con drones. Tres grupos según el tipo de trabajo, para no gastar tokens de un modelo grande en tareas mecánicas.

La decisión de modelo vive en el frontmatter de cada agente. Ninguno fija un modelo superior a `claude-sonnet-5` salvo decisión explícita del dueño. El mismo criterio rige las sesiones que lanza `bin/ciclo`, fase por fase (ver `docs/gestion/automatizacion_desarrollo.md`).

Skills que los agentes cargan: `marketplace-politicas`, `contrato-api`, `ciclo-segundo-plano`, `dominio-backend`, `modelo-datos`, `seguridad-roles`, `panel-design-ui`, `redaccion-neutra`, `verificacion` y `flujo-git-pr`.

## Producto y confianza (modelo mediano: `claude-sonnet-5`)

| Agente | Cuándo usarlo |
|---|---|
| `producto-marketplace` | Reglas del marketplace: roles, solicitud, calificaciones, comisión, aprobaciones, backlog |
| `asignacion-matching` | Despacho: propios primero, terceros por radio y calificación, ofertas y tiempos |
| `confianza-seguridad` | Verificación de proveedores, abuso, privacidad, seguridad de API, panel y apps |
| `negocio` | Operación de campo, banco de preguntas, economía del piloto |

## Juicio técnico (modelo mediano: `claude-sonnet-5`)

| Agente | Cuándo usarlo |
|---|---|
| `arquitectura` | Encaje de código nuevo y redacción de ADRs |
| `backend` | Casos de uso, eventos, endpoints: reglas con dinero, estados y asignación |
| `modelo-datos` | Esquema PostgreSQL, índices e integridad |
| `migracion-esquema` | Migraciones destructivas: respaldo, `down`, prueba con datos reales |
| `modulos-roles` | Seguridad `sec_*`: permisos, roles, policies |
| `design-ui` | Sistema de diseño del panel |
| `orquestador` | Decide qué agentes delegar y en qué orden |
| `validador` | Revisa el trabajo contra `CLAUDE.md` y los ADRs antes de cerrar |

## Ejecución (modelo económico: `claude-haiku-4-5-20251001`)

| Agente | Cuándo usarlo |
|---|---|
| `frontend` | Ensambla pantallas con el catálogo de componentes ya definido |
| `qa-visual` | Capturas, contraste y estados de las pantallas del panel |
| `estandares-programacion` | Estilo, convenciones y tests |
| `distribucion` | CI/CD, entornos y versionado coordinado con las apps |
| `memoria-contexto` | Estado de continuidad entre sesiones |

## Cómo delegar entre ellos

- Tarea de una sola capa: agente directo (un índice nuevo va a `modelo-datos`).
- Tarea que cruza capas: pasá primero por `orquestador`, que arma la cadena. Una HU típica recorre `producto-marketplace` → `modulos-roles` → `modelo-datos` → `backend` → `frontend` → `qa-visual` → `confianza-seguridad` → `validador`.
- Migración con borrado o transformación: `modelo-datos` y `migracion-esquema` juntos, antes de integrar.
- Todo cambio no trivial termina en `validador`; todo cambio visual, además, en `qa-visual`; todo cambio que toca datos personales, autorización o archivos, en `confianza-seguridad`.
- Un cambio de API se publica con el skill `contrato-api` y se coordina con `agrocom-field`.

## Lo que estos agentes nunca deciden solos

Una regla de negocio nueva (cómo se asigna un servicio, qué calificación ve quién, cuánto cobra la plataforma) la propone `producto-marketplace` como decisión revisable y se documenta; ningún agente de implementación la inventa. Lo mismo vale para el despacho (`asignacion-matching`).
