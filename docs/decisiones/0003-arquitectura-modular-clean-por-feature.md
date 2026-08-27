# ADR 0003 — Backend: monolito modular con Clean Architecture por eje ortogonal a los módulos de dominio

**Estado:** Aceptada · **Fuente:** `docs/legacy/decisiones_arquitectura_v2.md`, sección 3.

## Contexto

Un solo desarrollador con agentes de IA como implementadores principales necesita fronteras que la propia suite de tests pueda defender — no reglas que dependan de disciplina o memoria. Al mismo tiempo, la Clean Architecture ortodoxa (entidades independientes del framework, repositorio para cada modelo) es un impuesto que un equipo de una persona no puede pagar y que casi nunca devuelve valor en Laravel.

## Decisión

Dos ejes ortogonales:

- **Arquitectura por feature (módulos de dominio)** decide *dónde vive* el código — cohesión por negocio: `Identidad`, `Comercial`, `Mezclas`, `Operaciones`, `Sync`, `Finanzas`, `Mantenimiento`, `Portal`, `Reportes`, más `Compartido` (no depende de nadie; todos dependen de él).
- **Clean Architecture pragmática** decide *hacia dónde apuntan las dependencias* dentro de cada módulo: `Contratos/` (interfaces + DTOs — la frontera pública), `Aplicacion/` (casos de uso), `Dominio/` (reglas puras, sin Laravel ni base de datos), `Infraestructura/` (Eloquent, HTTP, colas).

Reglas de cohesión fuerte / acoplamiento débil:

1. Un módulo solo escribe sus propias tablas.
2. Entre módulos se viaja por `Contratos/` (síncrono, cuando se necesita respuesta) o por eventos de dominio (cuando es consecuencia — `SesionValidada` → `Finanzas` genera devengos). Los eventos llevan DTOs primitivos, nunca modelos Eloquent.
3. Las referencias cruzadas por ID están permitidas (es una base de datos relacional, no microservicios); lo prohibido es la lógica cruzada.
4. `Eloquent ES el modelo de dominio` dentro del módulo dueño — no se duplica en una entidad pura aparte.
5. Las reglas se verifican con tests de arquitectura (Pest arch), no con disciplina: un agente de IA que importe un modelo ajeno "por comodidad" rompe la suite.

Inyección de dependencias vía el contenedor de Laravel: cada módulo registra su propio `ServiceProvider`; constructor injection en casos de uso, controllers y listeners; nada de `app()` ni facades dentro de `Dominio/`.

## Alternativas descartadas

- **Clean Architecture ortodoxa** (entidad pura + repositorio por modelo): impuesto de mantenimiento que un solo desarrollador no puede sostener, sin retorno de valor proporcional a este tamaño de sistema.
- **Microservicios**: un desarrollador, un dominio cohesionado, transacciones que cruzan módulos (validar sesión toca `Operaciones` y `Finanzas`) — el monolito modular es la respuesta correcta a esta escala.

## Consecuencias

- Los límites de módulo coinciden con las fases de desarrollo de `docs/especificacion/especificacion_funcional_tecnica.md` (sección 15), lo que permite congelar las fases 3–8 sin contaminar la ruta crítica.
- Toda transición de estado con dinero (ver ADR de máquinas de estado en la especificación) pasa por un servicio de dominio explícito, nunca por un `UPDATE estado = ...` suelto en un controlador.

## Revisión (27/8/2026) — feature-first vs. layer-first, reapertura formal a pedido del usuario

**Contexto de la revisión.** El usuario esperaba lo contrario de lo construido: carpeta de más alto nivel por *tipo técnico* (`app/Models/`, `app/Http/Controllers/`, `app/Services/`), con el módulo de dominio como subcarpeta dentro de cada tipo (`app/Models/Comercial/`, `app/Http/Controllers/Comercial/`, etc.) — en vez de módulo arriba y tipo técnico adentro, que es lo que hoy hay en `app/Dominios/{Modulo}/{Contratos,Aplicacion,Dominio,Infraestructura}/`. Pidió reabrir la discusión formalmente antes de decidir si se mantiene o se cambia. Esta sección documenta esa discusión — no implementa ningún cambio de código ni mueve ningún archivo; es análisis puro para que el usuario decida.

### 1. Los dos modelos, lado a lado, anclados a este repo (no en abstracto)

| Punto de comparación | Feature-first (vigente) | Layer-first (lo que el usuario tenía en mente) |
|---|---|---|
| Ver "todos los modelos Eloquent de un vistazo" | No hay una sola carpeta: hay que recorrer `app/Dominios/*/Infraestructura/Eloquent/`. Hoy son 5 carpetas (`Comercial`, `Compartido`, `Operaciones`, `Personal`, `Seguridad`). Es el costo real, y el usuario lo señala con razón. | Un solo `app/Models/` con subcarpetas por módulo — un `ls -R app/Models` alcanza. |
| Onboarding con la convención por defecto de Laravel/artisan | `php artisan make:model` no respeta la estructura (crea en `app/Models/`); cada `make:*` necesita `--path` o mover el archivo a mano después. Fricción menor pero constante. | `make:model`, `make:controller` caen naturalmente en su lugar (salvo el subdirectorio por módulo, que igual hay que mover a mano). |
| Diff de PR legible como "esto es un cambio de `Comercial`" | Un PR que toca solo `Comercial` se ve como un único subárbol en la vista de archivos de GitHub — el alcance del cambio es visualmente obvio. | El mismo PR aparece repartido en `app/Models/Comercial/`, `app/Http/Controllers/Comercial/`, `app/Services/Comercial/` — mismo contenido, pero disperso en 3+ subárboles de primer nivel, intercalado con archivos de otros módulos en el mismo nivel de árbol. |
| Regla 1 ADR 0003 ("un módulo solo escribe sus tablas") | No depende de la estructura de carpetas de `app/` en absoluto — las migraciones ya son planas bajo `database/migrations/` con prefijo de tabla por módulo (ADR 0011). Esta regla es indiferente al esquema que se elija acá. | Igual: indiferente. Ninguno de los dos esquemas mueve ni toca migraciones. |
| Regla 2 y regla "no importar modelos ajenos" — **verificación automática con Pest Arch** | Hoy es **una línea** por módulo: `arch()->expect("App\Dominios\{$modulo}")->not->toUse($modelosAjenos)`, porque el módulo entero (Contratos+Aplicacion+Dominio+Infraestructura) vive bajo un único prefijo de namespace. Cubre controladores, requests, resources, middleware, casos de uso y modelos con una sola expresión. | El módulo deja de ser un único prefijo de namespace: es la *unión* de `App\Models\Comercial`, `App\Http\Controllers\Comercial`, `App\Services\Comercial`, y cualquier tipo técnico nuevo que aparezca (`App\Http\Requests\Comercial`, `App\Http\Resources\Comercial`, `App\Http\Middleware\...`, `App\Events\...`, `App\Listeners\...`). El `arch()->expect([...])` de cada módulo necesita ese array completo, y **hay que mantenerlo a mano** cada vez que el módulo suma un tipo técnico nuevo — exactamente la "disciplina/memoria" que la regla 5 de este ADR existe para eliminar. |
| Descubrimiento automático de módulos nuevos sin tocar el test | `tests/Unit/ArquitecturaModulosTest.php` hace `glob($rutaDominios.'/*', GLOB_ONLYDIR)`: un módulo nuevo (p. ej. `Finanzas` el día que exista) queda protegido por las mismas reglas sin editar una línea del test — es "carpeta de primer nivel = módulo", literal. | No hay una única carpeta que enumerar: "cuáles son los módulos" pasa a ser una lista explícita a mantener a mano (o la intersección, poco confiable, de subcarpetas repetidas en 3+ raíces técnicas que no necesariamente coinciden en nombre ni existen todas para todos los módulos). Es un registro manual — el mismo patrón de "hay que acordarse de actualizar la lista" que el descubrimiento por carpeta evita hoy. |
| `Compartido/` "no depende de nadie" | Una carpeta, excluida por nombre del recorrido (`reject(...'Compartido')`). | Su contenido (`ModeloDominio`, `RegistraAutoria`, excepciones, middleware transversal) no encaja en ningún tipo técnico único — quedaría repartido entre `app/Models/`, `app/Http/Middleware/`, `app/Exceptions/`, perdiendo la unidad que hoy tiene como carpeta. |
| Ubicación de las capas Clean (`Contratos/`, `Dominio/`) | Tienen carpeta propia, explícita, dentro de cada módulo — son el segundo eje del ADR, ortogonal al primero. | **No tienen un tipo técnico nativo de Laravel equivalente.** `Contratos/` (interfaces + DTOs) y `Dominio/` (reglas puras, sin Eloquent) no son "Modelos", "Controladores" ni "Servicios" en el sentido de Laravel — ver punto 2 más abajo. |

### 2. El pedido del usuario no es el eje ortogonal opuesto — es un eje distinto, y eso importa

Este ADR ya definió dos ejes ortogonales: *dónde vive el código* (feature) y *hacia dónde apuntan las dependencias* (capas Clean pragmáticas: `Contratos → Aplicacion → Dominio`, con `Infraestructura` implementando hacia adentro). Lo que el usuario describe (`app/Models/`, `app/Http/Controllers/`, `app/Services/`) no es "las capas Clean puestas arriba en vez de abajo" — es una tercera taxonomía, la de **tipo técnico de Laravel**, que no tiene una casilla para `Contratos/` ni para `Dominio/` puro (un enum de estado como `EstadoContrato` o una excepción de dominio como `PermisoDenegado` no es un "Modelo", un "Controlador" ni un "Servicio" en el vocabulario de Laravel).

Esto importa porque adoptar literalmente la estructura que el usuario tenía en mente no es neutral respecto del segundo eje del ADR: o bien (a) las capas Clean se abandonan y todo colapsa a la convención MVC-ish nativa de Laravel (`Models/Controllers/Services`) — lo que de hecho revertiría también la decisión de "Clean pragmática" de este mismo ADR, no solo la de "feature-first" —, o bien (b) se inventan carpetas de primer nivel nuevas para sostener las capas (`app/Contracts/`, `app/UseCases/`, `app/DomainRules/`), y entonces el problema de fragmentación del punto 1 se multiplica: cada módulo pasa de tener **una** carpeta a tener código repartido en **cinco o seis** raíces técnicas de primer nivel en vez de tres. Ninguna de las dos variantes es "lo mismo con las carpetas dadas vuelta" — cualquiera de las dos es una decisión adicional, no solo un reacomodo.

### 3. Costo de migrar lo ya construido — medido en el repo, no estimado a ojo

Relevado hoy (27/8/2026), con HU-02 en curso:

- **49 archivos fuente** bajo `app/Dominios/**/*.php` cuyo namespace propio (y ruta de archivo) cambiaría: 27 en `Seguridad` (55% del total — es justo el módulo donde `modulos-roles` está escribiendo `sec_menu` ahora mismo), 8 en `Comercial`, 6 en `Operaciones`, 5 en `Compartido`, 3 en `Personal`.
- **18 archivos de test** con casos de prueba (`test()`/`it()`: 84 casos en total), de los cuales **15 importan clases de `App\Dominios\*` directamente** (75 ocurrencias de `use App\Dominios...` a reescribir).
- **`tests/Unit/ArquitecturaModulosTest.php` no admite un simple find-and-replace**: su mecanismo de descubrimiento (`glob($rutaDominios.'/*', GLOB_ONLYDIR)`, "carpeta de primer nivel = módulo") es una asunción estructural, no una ruta de import. Migrar a layer-first obliga a **rediseñar el test**, no a renombrarlo — es el archivo de mayor riesgo de todo el ejercicio porque es el que hoy defiende automáticamente las reglas 1, 2 y 5 del propio ADR 0003.
- `routes/web.php` y `routes/api.php`: 2 archivos, 3 `use` + referencias inline a controladores.
- `database/factories/SecUserFactory.php` + `database/seeders/Catalogo/SeguridadSeeder.php` + `database/seeders/Catalogo/SecMenuSeeder.php` + `database/seeders/Demo/NucleoComercialSeeder.php`: 4 archivos, 21 ocurrencias.
- **Sin costo**: las 19 migraciones (`database/migrations/`) no referencian namespaces de módulo — usan nombres de tabla planos con prefijo (ADR 0011), indiferentes a este esquema. `composer.json` tampoco cambia: el root PSR-4 sigue siendo `App\` → `app/` en ambos esquemas, solo cambia la ruta relativa dentro de `app/`. Hoy no existe ningún `ServiceProvider` por módulo todavía (solo `App\Providers\AppServiceProvider`), así que esa pieza del ADR queda en cero para este cálculo, en cualquiera de los dos esquemas.
- **Total: ~70 archivos con ediciones de import/namespace** (49 propios + 15 de test + 2 de rutas + 4 de factories/seeders), más el rediseño no mecánico del test de arquitectura.

**Riesgo de calendario, no solo de volumen:**
1. `modulos-roles` está escribiendo *ahora* dentro de `Seguridad/Infraestructura/...` para `sec_menu` (HU-02) — el 55% de los archivos afectados por una migración están en el módulo con edición activa en paralelo. Una migración decidida hoy garantiza conflictos de merge si se ejecuta antes de que ese trabajo se integre, o una segunda ronda de movimiento si se ejecuta después.
2. Es una migración 100% mecánica sin funcionalidad nueva — exactamente el tipo de PR de 70 archivos que es difícil de revisar "línea por línea" para confirmar cero cambio de comportamiento (`CONTRIBUTING.md` pide que la suite esté en verde y el criterio de aceptación tenga test; acá no hay criterio de aceptación de negocio, solo riesgo de introducir un error de rename).
3. Riesgo de entorno concreto de este proyecto: desarrollo en macOS (filesystem case-insensitive por defecto) contra CI/Docker en Linux (`docker-compose`, ADR 0010; CI en `ci.yml`). Un rename masivo de rutas de archivo con diferencias sutiles de mayúscula/minúscula puede pasar en local y romper recién en CI o en el contenedor — un modo de falla silencioso propio de este stack, no genérico.
4. El número de módulos de dominio previstos (tabla de prefijos de ADR 0011: `Seguridad, Sync, Comercial, Operaciones, Mezclas, Personal, Finanzas, Inventario, Mantenimiento`, más `Portal`/`Reportes` sin tabla propia — 9 a 11 carpetas de módulo en total, no 5–8) hace que el costo de fragmentación de layer-first (punto 1 y 2) escale con cada módulo nuevo, mientras que el costo de feature-first por módulo nuevo es plano (una carpeta más, cero mantenimiento de test).

### 4. Lo que el pedido del usuario señala correctamente

El síntoma es real y no se descarta: no hay hoy una forma barata de responder "¿cuáles son todos los modelos Eloquent del sistema?" sin recorrer 5 (a futuro, hasta 11) carpetas `Infraestructura/Eloquent/`. Es una fricción de ergonomía legítima, en particular para revisar el invariante 6 de `CLAUDE.md` (dinero/hectáreas en `DECIMAL`, nunca `float`) de un vistazo global.

### 5. Recomendación

**Se ratifica la arquitectura de ADR 0003 sin cambios de estructura.** El argumento decisivo no es de gusto sino de mantenimiento verificable: el valor central del esquema vigente es que las reglas de cohesión/acoplamiento se verifican con **una línea de Pest Arch por módulo**, derivada automáticamente de "carpeta de primer nivel = módulo" (regla 5 del ADR). Layer-first no ofrece un reemplazo de costo equivalente para esa verificación — la alternativa concreta es mantener a mano, por módulo, la lista de namespaces de cada tipo técnico que participa (`Models`, `Http\Controllers`, `Http\Requests`, `Http\Resources`, `Http\Middleware`, y lo que se agregue), que es precisamente el tipo de regla "que depende de que alguien se acuerde" que este ADR fue escrito para eliminar. Migrar ahora, además, cuesta ~70 archivos mecánicos y colisiona en el tiempo con `modulos-roles` escribiendo el 55% de esos archivos en este mismo momento, sin ningún valor de negocio a cambio.

Para resolver la fricción real señalada en el punto 4 **sin tocar la estructura de carpetas**, se propone una herramienta aditiva (a decidir e implementar por el usuario/`backend`, no incluida en esta revisión):

- Un comando Artisan (p. ej. `php artisan dominios:modelos`) que recorra `app/Dominios/*/Infraestructura/Eloquent/*.php` y liste módulo, clase, tabla física y prefijo (ADR 0011) en una sola tabla — autoactualizado en cada corrida, a diferencia de un índice Markdown estático que se desactualiza. Puede además servir de base a futuro para auditar el invariante 6 (columnas `DECIMAL` vs. `float`) desde un único punto de entrada.
- Alternativa de costo cero en código: un "Scope" de PhpStorm (`file:app/Dominios/*/Infraestructura/Eloquent//*.php`) que da una vista virtual plana de todos los modelos sin mover un archivo — configuración local, no versionable, pero válida para un desarrollador único.

### 6. Estado de la decisión

**ADR 0003 queda RATIFICADO**, razonamiento reforzado con el análisis anterior. No se marca como candidato a ADR sucesor. Si en el futuro el número de módulos o el tamaño del equipo cambia de manera que el cálculo de costos de este punto 3 deje de aplicar, esta sección es el punto de partida para reabrir la discusión — pero la decisión de reabrirla, migrar, o construir la herramienta de ergonomía del punto 5, queda en manos del usuario.
