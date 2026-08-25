# Decisiones de arquitectura v2 — BD, panel, capas, BLoC y modelo de seguridad

**Agrocom SRL · Sistema de Gestión de Operaciones de Fumigación · Complementa la Definición Técnica**

Responde cinco preguntas: qué base de datos; si se puede usar AdminLTE/Material en el panel; cómo mezclar Clean Architecture con arquitectura por feature en Laravel (con DI, cohesión fuerte y acoplamiento débil); qué arquitectura Flutter con BLoC; y el análisis del modelo de seguridad `sec_*`.

---

## 1. Base de datos: MySQL vs. MariaDB vs. PostgreSQL

Los tres funcionan con Laravel sin fricción, y con MySQL ya tenés oficio. La decisión no es de gustos: se juega en cuatro capacidades que **este sistema usa de verdad**:

| Capacidad | Por qué la usa este sistema | PostgreSQL 16 | MySQL 8 | MariaDB 11 |
|---|---|---|---|---|
| **DDL transaccional** (migraciones dentro de una transacción) | Vas a correr cientos de migraciones con agentes de IA. En Postgres, una migración que falla a la mitad revierte completa; en MySQL/MariaDB queda el esquema a medio aplicar y hay que limpiar a mano | ✔ | ✘ | ✘ |
| **`INSERT ... ON CONFLICT DO NOTHING` con `RETURNING`** | Es literalmente el corazón de la idempotencia del sync: reinsertar un `uuid_cliente` repetido debe ser un no-op limpio que además te dice qué pasó | ✔ limpio | Parcial (`INSERT IGNORE` silencia TODOS los errores, no solo el duplicado — peligroso) | Parcial (ídem) |
| **Índices parciales y por expresión** | `UNIQUE` de "una sola orden vigente por lote", índices solo sobre `estado='pendiente'` en colas | ✔ | ✘ parciales | ✘ parciales |
| **CHECK constraints + tipos estrictos** | Rangos (viento ≤ 17, temperatura), enums de estado, que un string no entre en un campo numérico en silencio | ✔ desde siempre | ✔ desde 8.0.16 | ✔ |

A eso se suma JSONB indexable para la geometría GeoJSON de lotes. **Recomendación: PostgreSQL 16.** MySQL 8 es una segunda opción digna si algún día un requisito de hosting lo impone; **MariaDB es la peor de las tres para este caso** — es la que más se aleja de las capacidades listadas y la menos ejercitada por el ecosistema Laravel moderno. El costo de cambio para vos es bajo: con Eloquent el 95% del código no sabe qué motor hay debajo, y el 5% restante (el `ON CONFLICT` del sync, los índices parciales) es justo donde Postgres paga.

---

## 2. Panel web: ¿AdminLTE / Material Design?

Sí, se puede — y la pregunta correcta es qué compra cada opción. AdminLTE es una **plantilla** (layout, menú lateral, cards, look Bootstrap): te da la cáscara. Filament es una **máquina de comportamiento** (tablas con filtros, formularios validados, CRUD, exportación): te da el motor, con su propia estética (que se personaliza en colores/logo, pero no es Material).

| Criterio | AdminLTE + Blade/Livewire | Filament 4 | SPA Material (Vue/Vuetify o React/MUI) |
|---|---|---|---|
| Qué te da hecho | Layout y menú | Layout + CRUD + tablas + formularios + permisos por recurso | Solo componentes visuales |
| Qué escribís vos | Todo el comportamiento (cada tabla, cada form, cada filtro) | Solo lo específico del dominio | Todo + API pública + auth por token |
| Tu modelo `sec_menu`/`sec_module` (menú dinámico desde BD) | **Encaja natural** — el menú se renderiza desde tus tablas | Posible (navegación dinámica por código) pero vas contra su convención | Encaja natural |
| Estética Material | No (Bootstrap) | No (propia, personalizable) | Sí |
| Tiempo del panel de ruta crítica | ~10–12 días | ~5–6 días | ~12–15 días |

**Recomendación honesta:** si el objetivo dominante es llegar antes de campaña, Filament sigue ganando por calendario. Pero si tu módulo de seguridad `sec_*` con menú dinámico es un activo que querés reutilizar entre proyectos (y por cómo lo planteás, lo es), **AdminLTE + Blade + Livewire es una decisión legítima y coherente**: tu menú se arma desde `sec_menu`, tus permisos gobiernan cada botón, y los agentes de IA reducen mucho el costo histórico de escribir CRUDs a mano. El precio real son ~5–6 días más de panel — pagalos conscientemente, no por inercia. La SPA Material queda descartada para v1: es la única opción que agrega una aplicación entera más al proyecto.

Decisión intermedia que también existe: AdminLTE para el panel interno (donde vive tu modelo de menús) y las pantallas más "de máquina" (colas de validación, bandeja de alertas) como componentes Livewire ricos dentro de ese layout. El portal del cliente, que es solo lectura y chico, sale con el mismo stack en un guard aparte.

---

## 3. Laravel: Clean Architecture + arquitectura por feature (sí se mezclan, y así)

No solo es posible: **la mezcla es la forma correcta de usar Clean en Laravel**. La clave es entender qué eje resuelve cada una: la arquitectura **por feature (módulos de dominio)** decide *dónde vive* el código — cohesión por negocio; la **Clean Architecture** decide *hacia dónde apuntan las dependencias* dentro y entre módulos — el dominio no conoce la infraestructura. Se combinan como ejes ortogonales: módulos por fuera, capas por dentro.

### 3.1 Estructura de un módulo

```
app/Dominios/Operaciones/
├── Contratos/            # ← LA FRONTERA PÚBLICA del módulo (interfaces + DTOs)
│   ├── ValidadorDeSesiones.php        (interface)
│   ├── ConsultaDeTrabajos.php         (interface de lectura)
│   └── Datos/SesionValidadaData.php   (DTO inmutable)
├── Aplicacion/           # casos de uso: 1 clase = 1 acción de negocio
│   ├── AbrirTrabajo.php  ├── CerrarSesion.php  ├── ValidarSesion.php
├── Dominio/              # reglas puras: máquina de estados, cálculos, excepciones
│   ├── EstadosTrabajo.php  ├── ReglaDobleConteo.php  ├── Eventos/SesionValidada.php
├── Infraestructura/      # lo que toca el mundo: Eloquent, HTTP, colas
│   ├── Modelos/ (Trabajo, Sesion, Condicion...)   ├── Http/Controllers/
│   └── Persistencia/ (implementaciones de contratos)
└── OperacionesServiceProvider.php     # registra los bindings del módulo
```

### 3.2 El pragmatismo que evita pelearse con Laravel

La Clean ortodoxa exige entidades independientes del framework y repositorios para todo. En Laravel eso significa duplicar cada modelo Eloquent en una entidad pura y mapear a mano — un impuesto enorme que un equipo de una persona no puede pagar y que casi nunca devuelve valor. La versión pragmática que sí paga:

- **Eloquent ES el modelo de dominio** dentro del módulo dueño. No se duplica.
- **Interfaces solo en las fronteras reales**: entre módulos (los `Contratos/`), y hacia infraestructura sustituible o que quieras fingir en tests (almacén de evidencias, generador de PDF, reloj, notificador). No hay `RepositorioDeTrabajos` con un solo implementador viviendo al lado — eso es ceremonia, no arquitectura.
- **La regla de dependencia se conserva donde importa**: `Dominio/` no importa nada de `Infraestructura/` ni de Laravel (son clases puras y testeables sin base de datos); `Aplicacion/` orquesta modelos y dispara eventos; `Infraestructura/` conoce a todos.

### 3.3 Cohesión fuerte, acoplamiento débil: las cinco reglas

1. **Un módulo solo escribe SUS tablas.** `Finanzas` jamás hace `Sesion::create()`; si necesita algo de Operaciones, lo pide por contrato o reacciona a un evento.
2. **Entre módulos se viaja por `Contratos/` o por eventos — nunca por modelos ajenos.** Sincrónico cuando necesitás respuesta (consultar hectáreas validadas), evento cuando es consecuencia (`SesionValidada` → Finanzas genera devengos). Los eventos llevan DTOs con datos primitivos, no modelos Eloquent.
3. **Referencias cruzadas por ID están permitidas** (`gastos.dron_id` apunta a Recursos): es una base de datos relacional, no microservicios — la integridad referencial es un activo, no un acoplamiento. Lo prohibido es la *lógica* cruzada, no la clave foránea.
4. **`Compartido/` no depende de nadie; todos pueden depender de `Compartido/`.** Ahí viven la máquina de estados genérica, auditoría, dinero, evidencias.
5. **Las reglas se verifican con tests de arquitectura**, no con disciplina:

```php
// tests/Arquitectura/ModulosTest.php  (Pest arch)
arch('finanzas no toca los modelos de operaciones')
    ->expect('App\Dominios\Finanzas')
    ->not->toUse('App\Dominios\Operaciones\Infraestructura\Modelos');
arch('el dominio es puro')
    ->expect('App\Dominios\Operaciones\Dominio')
    ->not->toUse(['Illuminate\Database', 'Illuminate\Http']);
```

Con esto, un agente de IA que "por comodidad" importe un modelo ajeno rompe la suite — la arquitectura se defiende sola.

### 3.4 Inyección de dependencias

El contenedor de Laravel es el mecanismo; la disciplina es del proyecto:

```php
// OperacionesServiceProvider.php
public function register(): void {
    $this->app->bind(ValidadorDeSesiones::class, ValidadorDeSesionesEloquent::class);
    $this->app->bind(ConsultaDeTrabajos::class, ConsultaDeTrabajosEloquent::class);
}
// Finanzas consume el CONTRATO, nunca la implementación:
final class GenerarDevengos {
    public function __construct(private ConsultaDeTrabajos $trabajos) {}
}
```

Cada módulo registra su propio ServiceProvider (autodescubierto). Constructor injection en casos de uso, controllers y listeners; nada de `app()` ni facades dentro de `Dominio/`. En los tests, la frontera se sustituye: `$this->mock(ConsultaDeTrabajos::class)` — y podés testear Finanzas sin sembrar una sola tabla de Operaciones. Eso — poder testear y modificar un módulo sin arrastrar a los demás — es la independencia que buscás, y no necesita microservicios: necesita fronteras con nombre.

---

## 4. Flutter: arquitectura por feature con BLoC

BLoC es una elección correcta acá: flujos con estados explícitos (el checklist bloqueante de mezcla, la sesión con sus transiciones) son exactamente lo que BLoC modela bien, y su estructura rígida les sienta bien a los agentes de IA. La arquitectura recomendada es **feature-first con tres capas por feature** — el espejo de la del backend:

```
lib/
├── nucleo/                       # compartido entre flavors (~70%)
│   ├── db/          # drift: tablas espejo + outbox
│   ├── sync/        # motor: cola, push/pull, estados — NO depende de ningún bloc
│   ├── auth/  evidencias/  catalogo/  ui/
│   └── di/          # get_it: registro de todo lo compartido
├── features/
│   ├── sesion_vuelo/             # (flavor piloto)
│   │   ├── presentation/   # pantallas + SesionBloc (eventos/estados)
│   │   ├── domain/         # reglas puras: cálculo de ha de la sesión vs acumulada
│   │   └── data/           # SesionRepository → drift DAO + outbox
│   ├── preparacion_mezcla/       # (flavor auxiliar) — ídem
│   ├── recargas/  incidencias/  cierre_lote/  ...
└── main_piloto.dart / main_auxiliar.dart   # componen features + DI por flavor
```

Las reglas que hacen que funcione offline-first:

1. **Los blocs leen de drift, no de la API.** El repositorio expone `Stream`s de la base local (`watch...()`); el bloc los consume y la UI reacciona. La app entera funciona idéntica con o sin señal, y cuando el sync trae datos nuevos, las pantallas se actualizan solas porque la base local cambió.
2. **Escribir = insertar local + encolar en outbox, en una transacción.** El bloc nunca espera a la red para confirmar una acción del usuario. El estado "sincronizado/pendiente" es un dato más que la UI muestra (el tic gris/verde), no un bloqueo.
3. **El motor de sync es un servicio de `nucleo/`, sin BLoC.** Corre por conectividad, apertura de app y botón manual; publica su estado por un `Stream` que un `SyncCubit` chiquito expone a la UI. Ningún bloc de feature le habla directo.
4. **Cubit para lo simple, Bloc para lo rico.** Pantallas de lista/detalle: `Cubit`. Flujos con secuencia y transiciones (checklist de mezcla paso a paso, sesión abierta→cerrada): `Bloc` con eventos — ahí los estados explícitos valen su ceremonia.
5. **DI con get_it** (registro manual en `di/`; `injectable` si crece): repos y servicios en el contenedor, blocs creados por pantalla vía `BlocProvider` recibiendo sus repos del contenedor. `domain/` de cada feature es puro Dart testeable sin emulador — ahí van la regla del doble conteo y los cálculos de mezcla, con tests unitarios rápidos.

---

## 5. Análisis del modelo de seguridad `sec_*`

El modelo (action, module, menu, menu_action, role, permit, user) es un RBAC clásico orientado a menús: probado, entendible, y bueno para lo que fue diseñado — **pintar la navegación y las acciones visibles según el rol**. Para este sistema sirve como base, con un hallazgo estructural, dos incompatibilidades con tu propia spec y un paquete de ajustes menores.

### 5.1 Hallazgo estructural: el permiso está anclado a la pantalla

Hoy la cadena es `rol → permit → menu_action → menú → módulo`: el permiso **depende de que exista un menú**. Eso tiene dos consecuencias en este proyecto:

- **Las apps Flutter no tienen menús.** El piloto que cierra una sesión por API necesita una autorización que en tu modelo no existe, porque no hay `sec_menu` para el RC. Tendrías que inventar "menús fantasma" para la app — señal de que la dependencia está invertida.
- **Las reglas que dependen del DATO no caben en ninguna tabla de permisos**: "el validador no puede ser el piloto *de esa sesión*", "el jefe solo saca stock *de su base*", "el encargado no crea usuarios *con rol dueño*", "el portal solo ve *su contrato*". Ningún RBAC de pantallas expresa eso, porque no es "¿puede validar sesiones?" sino "¿puede validar ESTA sesión?".

**La corrección es una inversión de dependencia** (la misma idea de la sección 3): el permiso pasa a ser un concepto abstracto con código —

> `sec_permission`: `operaciones.sesion.validar`, `finanzas.gasto.crear`, `seguridad.usuario.gestionar`…

— y el menú lo *referencia* (`sec_menu_action.id_permission`), en lugar de definirlo. Así el mismo permiso gobierna el botón del panel web, el endpoint de la API y la pantalla del RC; el menú vuelve a ser lo que es: presentación. Y las reglas dependientes del dato viven en una **segunda capa**: policies de Laravel en la aplicación (`SesionPolicy::validar($usuario, $sesion)` → verifica el permiso Y que `$usuario->persona_id !== $sesion->piloto_id`). Dos capas, cada una en lo suyo: `sec_*` responde *"¿puede en general?"*; la policy responde *"¿puede sobre este registro?"*.

### 5.2 Incompatibilidades con tu spec

1. **`sec_user.id_role` = un solo rol por usuario.** Tu spec dice: "un usuario puede tener más de un rol (el jefe de campo también es piloto)… permisos por unión de roles". Hace falta la pivote **`sec_user_role`** (M:N) y eliminar `id_role` de `sec_user`. Es el cambio más importante.
2. **Falta el enlace usuario ↔ persona operativa.** `sec_user` es la identidad de login; `personas` (tu 4.2) es quien vuela, cobra por hectárea y tiene base. Sin `sec_user.persona_id` no se puede aplicar "nadie valida su propio trabajo **a nivel de persona**" ni calcular devengos desde la sesión del usuario autenticado. Agregar `persona_id` (FK, nullable — los usuarios-cliente del portal no son personas operativas; para ellos, `contrato_id`).

### 5.3 Ajustes menores

| # | Hallazgo | Ajuste |
|---|---|---|
| 1 | `password String(64)` | 255 y hash Argon2id/bcrypt vía cast `hashed` de Laravel; nunca dimensionar al hash de hoy |
| 2 | `profile_pic byte[]` en la tabla | La foto va al bucket de evidencias; en la tabla, `profile_pic_url` — los blobs en BD engordan respaldos y no se cachean |
| 3 | `state String(2)` con códigos mágicos | Sirve, pero mapearlo a enums con nombre en el código (`Activo/Bloqueado/Baja`); mismo criterio en todas las `sec_*` |
| 4 | `sec_menu_action.id_menu` tipado String y `id_action` "fase" | Erratas del documento: ambos Integer FK — corregir antes de que un agente de IA lo tome literal |
| 5 | Falta gestión de dispositivos | Los RC y celulares autentican con token por dispositivo (Sanctum `personal_access_tokens`); poder revocar el token de UN RC perdido sin bloquear al usuario |
| 6 | Falta el guard del portal | `sec_user.type` ya existe (`interno`/`cliente`): usarlo para dos guards de autenticación separados; los clientes jamás resuelven menús internos |
| 7 | Auditoría de otorgamientos | `registered_by`/fechas cubren el estado actual, no la historia ("¿quién le dio finanzas a X y cuándo?"); una tabla `sec_permission_log` (grant/revoke, autor, fecha) la cubre barato |
| 8 | `sec_action` genérica | Correcta — y en este dominio incluye acciones no-CRUD: `validar`, `aprobar`, `firmar`, `anular`, `autorizar_version`. Tu modelo ya lo permite; solo poblarlas |

### 5.4 El modelo ajustado (resumen)

```
sec_action (id, name, description, icon, state, …)
sec_module (id, name, path, id_module_parent, orden, state, …)
sec_menu   (id, id_module, name, path, icon, orden, state, …)
sec_permission   (id, code UNIQUE, description, id_module)      ← NUEVO: la fuente de verdad
sec_menu_action  (id, id_menu, id_action, id_permission FK)     ← referencia, ya no define
sec_role         (id, name, description, state, …)
sec_role_permission (id_role, id_permission)                    ← reemplaza a sec_permit
sec_user  (id, name, login, password, language, type, persona_id FK NULL,
           contrato_id FK NULL, profile_pic_url, initial_path, state, …)
sec_user_role (id_user, id_role)                                ← NUEVO: multi-rol
sec_permission_log (id, id_user, id_permission|id_role, accion, autor, fecha)
+ personal_access_tokens (Sanctum, por dispositivo)
+ Policies por agregado (Sesion, Trabajo, Planilla, Stock, Usuario…) para las reglas por-registro
```

Con esos cambios, el módulo sigue siendo **reutilizable entre proyectos** (que es su gracia): `sec_*` viaja intacto de rubro en rubro; lo único que cambia por proyecto son los códigos de permiso poblados y las policies — que son, precisamente, el negocio. Nota práctica: `spatie/laravel-permission` implementa ya roles + permisos + pivotes + caché y podría reemplazar la mitad de estas tablas; si preferís conservar tu esquema `sec_` propio por familiaridad y reutilización, es totalmente defendible — pero entonces implementalo con la caché de permisos por usuario en memoria/Redis-de-array, porque la resolución rol→permiso se ejecuta en cada request.
