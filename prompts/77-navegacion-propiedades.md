<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/navegacion-propiedades etapas=3 -->

# Tarea 77 — HU-54: propiedades y lotes con menú propio, y "Personas" pasa a "Personal"

## Por qué esta tarea

Dos correcciones de navegación que pidió el dueño el 7/9/2026: *"la separación
de los menús de lotes y campos (propiedad)"* y *"en vez del menú Personas, debe
llamarse Personal"*.

Lo que hay hoy:

- `lang/es/menu.php` tiene **un solo ítem** `comercial.items.campos` = "Campos y
  lotes", y los lotes solo existen **dentro** del formulario de campo
  (`CrearCampo` los crea en la misma transacción, `_lote-fila.blade.php`). No
  hay listado de lotes, no se puede buscar un lote, y para tocar uno hay que
  saber a qué campo pertenece y abrir el campo entero.
- `recursos.items.personas` = "Personas". El módulo se llama `Personal`, la
  especificación §4.2 lo agrupa como personal, y el negocio dice "el personal".
  "Personas" era el nombre de la tabla filtrándose a la interfaz.

Ese hueco se nota más ahora: la tarea 71 carga el cultivo **por lote y
campaña**, y sin pantalla de lotes ese dato solo se puede tocar entrando por el
campo.

Depende de la tarea 76 (los selects de estas pantallas usan los átomos nuevos).

## Lo que ya existe

- `lang/es/menu.php` — los rótulos; `database/seeders/Catalogo/SecMenuSeeder.php`
  — el árbol sembrado y sus permisos.
- `Comercial/Aplicacion/{Crear,Actualizar,Eliminar}Campo.php`,
  `CamposController`, y las vistas `pages/campos/` (`_formulario`,
  `_lote-fila`, `index`, `create`, `edit`).
- `Comercial/Infraestructura/Eloquent/Lote.php`,
  `Comercial/Contratos/{LecturaLotes,LotePanel,LoteCatalogo}.php` — ya hay
  contrato de lectura de lotes, no hace falta inventarlo.
- `Comercial/Dominio/Excepciones/LoteConHistorialAsociado.php` — la regla de
  que un lote con historial no se borra ya existe; reusala.
- `Personal/**` y `lang/es/personal.php`.

## Qué hacer

1. **Dos ítems de menú donde había uno**: `comercial.items.propiedades`
   ("Propiedades") y `comercial.items.lotes` ("Lotes"), con sus permisos
   (`comercial.campo.*` y `comercial.lote.*`) sembrados en `SeguridadSeeder` y
   el árbol de `SecMenuSeeder` actualizado. La palabra del negocio para el campo
   es **propiedad** (y "hacienda" como sinónimo, ver glosario); el nombre de
   tabla `com_campos` **no cambia**.
2. **Pantalla propia de lotes**: listado con filtro por cliente y por propiedad,
   búsqueda por código, y las hectáreas; ficha con alta, edición y baja lógica
   de un lote suelto, incluido su perímetro en el mapa. Un lote con historial
   asociado no se borra (`LoteConHistorialAsociado`).
3. **El alta de propiedad conserva sus lotes en la misma transacción.** No
   rompas `CrearCampo`: dar de alta una propiedad con sus lotes de una sola vez
   sigue siendo el camino cómodo. Lo que se agrega es poder entrar por el lote,
   no se quita poder entrar por la propiedad.
4. **`recursos.items.personas` → `recursos.items.personal`**, rótulo "Personal",
   con la ruta, el permiso y las claves de `lang/es/` acompañando. Actualizá la
   descripción del grupo `recursos` ("Drones, baterías, vehículos, bases y
   personas" → "…y personal").
5. **Los selects de estas pantallas usan los átomos de la tarea 76** — cliente,
   propiedad, base, rol. Es el primer consumidor real del catálogo nuevo.
6. **Migración de `sec_menu`** para que las instalaciones existentes queden con
   el árbol nuevo sin re-sembrar a mano, y **sin pisar cambios manuales** de
   permisos por rol (mismo criterio que la tarea 64).

## Qué NO hacer

- No renombres `com_campos` ni ninguna columna: "propiedad" es rótulo de
  interfaz, el vocabulario técnico ya está fijado en el glosario y la
  especificación.
- No dupliques la lógica de alta de lote: el caso de uso es uno, lo llamen desde
  el formulario de propiedad o desde el de lote.
- No toques el cultivo por lote: es la tarea 71.
- No dejes `<select>` crudos en las pantallas que toques.

## Cómo repartir las etapas

- **Etapa 1**: menú (los dos ítems, el renombre a Personal), permisos, migración
  de `sec_menu`, tests.
- **Etapa 2**: listado y ficha de lotes, con su filtro y su mapa; tests Feature.
- **Etapa 3**: migración de selects a los átomos nuevos, traducciones,
  snapshots, `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0 (en este Mac, `./bin/verify --sin-assets`).
- Test: un rol sin `comercial.lote.ver` no ve el ítem "Lotes" en el sidebar y
  recibe 403 al entrar por URL.
- Test: crear una propiedad con dos lotes desde el formulario de propiedad sigue
  funcionando igual que antes.
- Test: borrar un lote con trabajos asociados se rechaza con
  `LoteConHistorialAsociado`.
- Test: `migrate --seed` dos veces deja el mismo árbol de menú, y los permisos
  quitados a mano a un rol siguen quitados.
- `grep -rn "'personas' =>" lang/es/menu.php` no devuelve nada.

## Puede tocar

`app/Dominios/Comercial/**`, `app/Dominios/Personal/**` (solo rótulos y rutas),
`app/Dominios/Seguridad/**` (solo `SeguridadSeeder` y `SecMenuSeeder`),
`database/migrations/**`, `lang/es/**`, `routes/web.php`, `resources/**`,
`tests/**`, `tests/Visual/**`.

Fuera de alcance: `cpn_campanias`, `com_lote_campania`, `per_equipos_trabajo`,
`fin_*`, `ope_*`.

## Cierre obligatorio de cada etapa

`runs/77.estado`, `runs/77.md`, y al `OK` `runs/77.pr.md`. Commits agrupados por
función, en español, imperativo, sin `Co-Authored-By`.
