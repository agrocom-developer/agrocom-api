# Verificación independiente — tarea {{ID}}

Sesión nueva y aislada. Vos **no** implementaste esto: tu trabajo es decidir si
se puede integrar, no convencerte de que está bien. Quien lo escribió ya se
convenció; por eso esta verificación corre aparte.

Los tests están congelados (`AGROCOM_TURNO_NOCHE=1`): si un test está mal, eso
es un hallazgo, no algo que arregles.

Cargá los skills `verificacion` y los que correspondan al área que se tocó
(`dominio-backend`, `seguridad-roles`, `modelo-datos`, `panel-design-ui`).

## Qué mirar

1. **El pedido**: leé `prompts/{{ID}}-*.md` y `runs/{{ID}}.md`. ¿Se hizo lo que
   se pidió, todo, y nada más? Alcance de menos y alcance de más son ambos
   hallazgos.
2. **El criterio de aceptación** que declara ese prompt: corrélo y reportá el
   exit code textual. Si el criterio es `./bin/verify`, corré `./bin/verify`.
3. **Cobertura real**: por cada criterio de aceptación de la tarea, el test que
   lo cubre, con `archivo:línea`. Un test que pasaría igual con el código roto
   no cuenta como cobertura — decilo si lo ves.
4. **Invariantes de `CLAUDE.md`**: las once, pero mirá con lupa las que toca el
   cambio. Las que más caro salen: 2 (nunca se sobrescribe un registro
   validado), 3 (devengo solo al validar), 5 (scoping desde el contrato o el
   usuario autenticado, nunca un `where` agregado después), 7 (transiciones por
   el servicio de estados), 8 (soft delete), 9 (bitácora), 10 (rol activo, no
   la unión de roles), 11 (ningún color hardcodeado).
5. **ADRs de `docs/decisiones/`** que el cambio toque.
6. **Los commits**: ¿están agrupados por función — una pieza coherente por
   commit — con mensajes en español, imperativo, que expliquen el porqué? Un
   commit único con todo, o un commit por archivo, es un hallazgo menor pero se
   reporta.
7. **Trampas**: ¿se tocó algún test, gate o config de análisis para que la
   cascada pase? Un `ignoreErrors`, un baseline nuevo, un test debilitado o un
   `skip` agregado son hallazgos graves. Un stub o una excepción documentada
   que corrige una firma de una dependencia **no** lo es, siempre que no oculte
   ningún error del código del proyecto: verificalo, no lo asumas.

## Qué NO hacer

No corrijas nada. No commitees, no pushees, no abras PR. No edites tests.

## Cierre obligatorio

`runs/{{ID}}.veredicto` con **una sola palabra**:
- `APROBADO` — se puede abrir el PR.
- `RECHAZADO` — hay al menos un hallazgo que hay que corregir antes.

`runs/{{ID}}-veredicto.md` con: la tabla criterio → test → veredicto, el exit
code del criterio de aceptación, y los hallazgos ordenados por gravedad. Cada
hallazgo con `archivo:línea`, qué invariante o ADR viola, y qué habría que
cambiar. Si no hay hallazgos, decilo explícitamente — un veredicto sin
evidencia no sirve.
