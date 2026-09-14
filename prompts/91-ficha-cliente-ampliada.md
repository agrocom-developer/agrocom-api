<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/ficha-cliente-ampliada etapas=3 -->

# Tarea 91 — HU-75: ficha de cliente ampliada

## Por qué esta tarea

El encargado quiere un directorio de cliente completo: dónde queda su oficina
central, su logo (para mostrarlo en el panel/documentos a futuro) y contactos
que hoy no tienen dónde clasificarse (Gerente General, Finanzas, Secretario).
No es crítica: ALTER aditivo + ampliar un enum ya existente, sin tocar ninguna
guarda de negocio.

## Lo que ya existe

- `com_clientes` (`razon_social`, `nit`, `tipo_persona`): sin `ubicacion_oficina`
  ni `logo_path`.
- `com_cliente_contactos.tipo` — `CHECK IN ('dueno', 'agronomo',
  'encargado_propiedad', 'otro')` + `TipoContactoCliente` (enum PHP,
  `app/Dominios/Comercial/Dominio/TipoContactoCliente.php`). El `<select>` de
  tipo de contacto (`clientes/_contacto-fila.blade.php`) arma sus opciones
  recorriendo `$tiposContacto` (los `cases()` del enum) contra
  `lang/es/comercial.php` (`clientes.contacto_tipo_opcion.<value>`) — agregar
  casos al enum + sus claves de idioma alcanza, **no hace falta tocar el
  blade**.
- **El patrón de logo ya existe en el repo, para la empresa** (ADR 0019,
  tarea del 11/9/2026): `SecDatosEmpresa.logo_path`,
  `Seguridad/Aplicacion/GuardarDatosEmpresa.php` (sube a `Storage::disk('public')`,
  nombre generado por el servidor, borra el archivo viejo al reemplazar o
  eliminar), `ActualizarDatosEmpresaRequest`
  (`'logo' => ['nullable','file','mimes:png,svg','max:2048']`,
  `'logo_eliminar' => ['nullable','boolean']`),
  `OrganizacionController::actualizarEmpresa()`/`logoArchivo()` (arma
  `{nombre, peso, url}` para la vista, `null` si no hay logo) y
  `organizacion/index.blade.php` (`x-molecules.file-field`, con
  `accept=".png,.svg"`, `remove-name="logo_eliminar"`, `:file-name`,
  `:file-size`, `:preview-url`). **Es el molde a replicar acá**, cambiando
  solo la carpeta destino y el dueño del dato (`Cliente` en vez de
  `SecDatosEmpresa`). ADR 0019 acota su alcance explícito al logo DE LA
  EMPRESA — no lo reescribas ni le agregues una sección nueva: el logo del
  cliente es un dato de negocio de `Comercial`, no un "asset de marca" de
  ADR 0009/0019, así que no hace falta ninguna decisión nueva, solo reusar la
  técnica (mismo disco, mismo criterio de nombre generado por el servidor).
- `CrearCliente`/`ActualizarCliente` (`Aplicacion/`) reciben hoy
  `(razonSocial, nit, tipoPersona, contactos)` — van a sumar `ubicacionOficina`
  y el manejo del logo.

## Qué hacer

Cargá los skills `verificacion` y `modelo-datos`.

1. **Migración `ALTER com_clientes`**: `ubicacion_oficina` (`string(255)`
   nullable) y `logo_path` (`string(255)` nullable, nunca input directo del
   formulario — lo fija el caso de uso, igual que `SecDatosEmpresa`).
2. **Migración `ALTER` del `CHECK` de `com_cliente_contactos.tipo`** (pgsql
   only, `DROP CONSTRAINT` + `ADD CONSTRAINT` con la lista ampliada):
   sumá `'gerente_general'`, `'finanzas'`, `'secretario'` a los cuatro
   valores existentes, sin quitar ninguno.
3. **`TipoContactoCliente`**: sumá los tres casos nuevos
   (`GerenteGeneral = 'gerente_general'`, `Finanzas = 'finanzas'`,
   `Secretario = 'secretario'`).
4. **`lang/es/comercial.php`**, `clientes.contacto_tipo_opcion`: las tres
   claves nuevas.
5. **`Cliente` (Eloquent)**: sumá `ubicacion_oficina` y `logo_path` a
   `$fillable`.
6. **`CrearClienteRequest`**: `ubicacion_oficina` => `['nullable', 'string',
   'max:255']`; `logo` => `['nullable', 'file', 'mimes:png,svg', 'max:2048']`
   (mismos límites que `ActualizarDatosEmpresaRequest`, ADR 0019).
   **`ActualizarClienteRequest`** suma además `logo_eliminar` => `['nullable',
   'boolean']` — en alta no hay logo previo que eliminar, ese campo no aplica.
7. **`CrearCliente`/`ActualizarCliente` (`Aplicacion/`)**: sumá el parámetro
   `?string $ubicacionOficina` a ambas firmas, y a `ActualizarCliente` sumá
   `?UploadedFile $logo = null, bool $eliminarLogo = false` (en `CrearCliente`
   alcanza con `?UploadedFile $logo = null`, sin `eliminarLogo`). Métodos
   privados `reemplazarLogo()`/`borrarLogo()` calcados de
   `GuardarDatosEmpresa`, mismo disco (`Storage::disk('public')`), ruta
   `logos/clientes/logo-{timestamp}.{ext}` (no repitas `logos/empresa/`: son
   dueños de dato distintos y no deben poder pisarse).
8. **`ClientesController`**: `store()` pasa `$request->file('logo')`;
   `update()` pasa además `$request->boolean('logo_eliminar')`. Sumá un
   método privado `logoArchivo(?Cliente $cliente)` calcado de
   `OrganizacionController::logoArchivo()`/`pesoLegible()`, y pasalo a las
   vistas `create`/`edit` como `logoArchivo`.
9. **Vista `clientes/_formulario.blade.php`**: campo `ubicacion_oficina`
   (`x-atoms.input`) y un `x-molecules.file-field` para el logo (mismo uso que
   en `organizacion/index.blade.php`, con `remove-name="logo_eliminar"` SOLO
   en la vista de edición — en alta no hay nada que quitar). Ajustá
   `campos_contador`.
10. **`lang/es/comercial.php`**, bloque `clientes`: etiquetas de
    `ubicacion_oficina` y del campo de logo (nombre, ayuda, "reemplazar",
    "quitar").

## Qué NO hacer

- No toques `TipoPersonaCliente` ni ningún otro enum de este módulo.
- No uses `Storage::disk('r2')` para el logo del cliente: es un archivo
  público y estable, no una evidencia privada con URL firmada — mismo
  argumento de ADR 0019, sección 2.
- No reordenes ni renombres los valores string ya existentes de
  `TipoContactoCliente` — son datos ya persistidos en `com_cliente_contactos`.
- No modifiques `docs/decisiones/0019-*.md`: esta tarea REUSA la técnica que
  describe, no amplía su alcance declarado (que es, a propósito, solo el logo
  de la empresa).

## Cómo repartir las etapas

- **Etapa 1**: migraciones (`ubicacion_oficina`/`logo_path` + `CHECK`
  ampliado), enum, `lang` de los tipos de contacto, modelo, los dos Request.
- **Etapa 2**: `CrearCliente`/`ActualizarCliente` con el manejo de logo,
  `ClientesController`, tests de caso de uso y de request.
- **Etapa 3**: vista, `lang` de las etiquetas nuevas, tests
  Feature/Playwright, `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0.
- Test: un contacto con cada uno de los tres tipos nuevos
  (`gerente_general`/`finanzas`/`secretario`) se acepta en alta y en edición;
  un tipo fuera del catálogo completo (siete valores) se sigue rechazando.
- Test: subir un logo en el alta lo persiste en `Storage::disk('public')` bajo
  `logos/clientes/`; reemplazarlo en edición borra el archivo viejo; marcar
  `logo_eliminar` sin subir uno nuevo lo borra y deja `logo_path` en `null`.
- Test: `ubicacion_oficina` se guarda y se lee en alta y edición, y es
  opcional.

## Puede tocar

`app/Dominios/Comercial/**`, migración `ALTER` nueva, `lang/es/comercial.php`,
`tests/**`.

## Cierre obligatorio de cada etapa

`runs/91.estado`, `runs/91.md`, y al `OK` `runs/91.pr.md`. Commits agrupados
por función, español, imperativo, sin `Co-Authored-By`.
