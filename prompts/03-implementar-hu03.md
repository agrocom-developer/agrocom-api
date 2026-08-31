# Tarea 03 — HU-03: token por dispositivo para la app de campo

Sesión nueva y aislada. Esta tarea **solo corre si `runs/02.estado` dice
`CERRADA`**. Si no, no empieces: escribí `runs/03.estado` con `OMITIDA` y parás.

Cargá los skills `dominio-backend`, `seguridad-roles`, `modelo-datos` y
`verificacion`.

## La historia

"Como **piloto o auxiliar**, quiero iniciar sesión en la app con token propio
del dispositivo, para operar sin volver a loguearme."

Criterios de aceptación (`docs/gestion/plan_sprints.md`, fila `HU-03`):
- Token Sanctum **por dispositivo**.
- **Revocable desde el panel**.
- Sesión persistente offline.

## Antes de escribir código

Escribí en `runs/03-diseno.md`, en no más de una carilla: dónde va cada pieza
según la arquitectura modular (qué módulo, qué capa, qué tabla y con qué
prefijo), y cómo se revoca un token desde el panel sin romper la frontera entre
módulos. **Si eso no te cierra, PARÁ**: escribí `runs/03.estado` con
`BLOQUEADA` y la pregunta concreta. No inventes un módulo nuevo.

## Después

Rama `feature/*` nueva desde `develop`, nombre de 2–3 palabras. PR separado de
cualquier otro: un PR por objetivo.

Test obligatorio del scoping: un token de un dispositivo no sirve para leer
recursos de otro usuario.

## Criterio de aceptación

`./bin/verify` devuelve 0 y los tres CA tienen test.

## Cierre obligatorio

`runs/03.estado` con una palabra: `OK`, `BLOQUEADA` u `OMITIDA`.
`runs/03.md` con qué se implementó, el número de PR, y lo que quedó afuera.
