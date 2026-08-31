<!-- ciclo: critica=no turno-noche=0 -->
# Tarea 06 — Bitácora de auditoría transversal (invariante 9)

Sesión nueva y aislada. Cargá los skills `verificacion`, `dominio-backend` y
`modelo-datos`.

## Por qué

La invariante 9 de `CLAUDE.md` pide bitácora en toda mutación relevante: quién,
cuándo, qué entidad, qué acción, valores antes/después donde aplique. El ADR
0007 la decidió como **trait/observer de plataforma**, con estas palabras: "no
requiere que cada caso de uso llame manualmente a un servicio de auditoría, así
un agente de IA no puede olvidarlo al escribir un nuevo caso de uso".

Hoy no existe. Solo está `app/Dominios/Compartido/Infraestructura/Eloquent/RegistraAutoria.php`,
que cubre autoría por fila (`created_by`/`updated_by`) y dice en su propio
docblock que la bitácora "es una pieza aparte, todavía pendiente".

Es la última deuda bloqueante de `docs/gestion/automatizacion_desarrollo.md`
antes de tocar devengos, planilla o máquina de estados.

## Qué hacer

1. **La tabla.** El ADR 0011 deja el punto abierto de forma explícita (última
   consecuencia: "queda abierto el prefijo de las tablas transversales de
   `Compartido/` (p. ej. la bitácora de auditoría del ADR 0007): se define al
   implementarlas, ampliando la tabla de este ADR"). Elegí el prefijo, agregá
   **una fila** a la tabla de prefijos del ADR 0011 y una nota corta al ADR
   0007 diciendo que la pieza ya existe y dónde. **No reescribas nada más de
   ningún ADR**: ampliar lo que un ADR dejó abierto es distinto de revisar lo
   que decidió.

   Columnas mínimas: actor (`sec_user.id`, nullable — un seeder o un comando no
   tienen usuario), fecha, entidad afectada (tabla + id), acción, y los valores
   antes/después donde apliquen. Mirá cómo resolvió `sec_permission_log` (ADR
   0004) antes de inventar una forma nueva.

2. **El mecanismo.** Trait + observer de Eloquent en `Compartido/`, colgable por
   modelo. El ADR 0007 deja "a evaluar en la implementación" si conviene
   `spatie/laravel-activitylog` en lugar de un trait propio: decidí con un
   criterio, escribilo en el PR, y tené en cuenta que el paquete trae su propia
   tabla sin prefijo de módulo y su propio modelo, que no extiende
   `ModeloDominio` — el mismo problema que HU-03 resolvió no publicando la
   tabla de Sanctum.

   Guardá los valores antes/después **solo de lo que cambió**, no la fila
   entera, y nunca el contenido de una columna de credenciales (`password`,
   `token`, `remember_token`). Un registro de auditoría que copia un hash de
   contraseña convierte la bitácora en un segundo lugar del que robarlo.

3. **El gate.** Un test que falle cuando un modelo que debería auditar no lo
   hace. La regla de "quién debe auditar" la definís vos y la justificás: el ADR
   0007 dice "empezando por todo lo que toque dinero, roles/permisos y estados
   operativos", así que una regla razonable se puede derivar del esquema (por
   ejemplo, tener columna de estado o de monto) en vez de una lista fija de
   clases. Descubrila recorriendo el árbol, como hacen `ArquitecturaModulosTest`
   y `TransicionesEstadoTest`.

   **Verificá el gate en los dos sentidos**: quitale la bitácora a un modelo que
   debe tenerla, confirmá que falla con `archivo:línea`, y devolvelo. Contá en
   `runs/06.md` qué inyectaste y qué reportó. Sin esa evidencia la tarea no está
   hecha — es la lección de la tarea 04, donde el gate parecía correcto y tenía
   dos falsos negativos.

4. **Aplicalo donde ya corresponde hoy**: los modelos `sec_*` de roles y
   permisos. No inventes modelos nuevos para tener a quién auditar.

## Qué NO hacer

- No toques `CLAUDE.md` ni `.claude/`.
- No revises decisiones ya tomadas en los ADRs; solo ampliá lo que quedó
  abierto (el prefijo) y agregá la nota de que la pieza existe.
- No agregues `ignoreErrors`, baselines ni `skip` para que la cascada pase.
- No hagas que la bitácora se llame desde los casos de uso: si hay que
  acordarse de llamarla, no cumple lo que el ADR 0007 decidió.

## Criterio de aceptación

`./bin/verify` devuelve 0; el gate falla —con `archivo:línea`— cuando se le
quita la bitácora a un modelo que debe tenerla; y existe un test que prueba que
una mutación real deja su registro con actor, acción y el antes/después de las
columnas que cambiaron.

## Máximo de intentos

3.

## Commits

Agrupados por función: la migración y el modelo de la bitácora; el trait y su
observer; el gate; la aplicación a los modelos que ya la necesitan; la
ampliación del ADR. Mensajes en español, imperativo, explicando el porqué.

## Cierre obligatorio

- `runs/06.estado`: `OK` o `BLOQUEADA`.
- `runs/06.md`: qué se implementó, qué prefijo elegiste y por qué, la evidencia
  de la inyección, y qué quedó afuera.
- `runs/06.pr.md`: título en la primera línea, cuerpo debajo.
