# ADR 0026 — Convención general de ruta y nombre para archivos por objeto

**Estado:** Aceptada.

## Contexto

ADR 0009 fijó tres categorías de archivo (evidencias, reportes generados, assets de marca), cada una con su disco y su regla de vida. ADR 0019 abrió una excepción puntual dentro de la tercera categoría para el logo de la propia empresa: disco `public`, nombre generado por el servidor (`logos/empresa/logo-{timestamp}.{ext}`), justificada explícitamente en que `sec_datos_empresa` es una fila única — no hay "otro" logo de empresa con el que pueda chocar.

Sin que ningún ADR lo registrara, el mismo patrón de nombre se extendió por código al logo de cliente (`CrearCliente`, `ActualizarCliente`, HU-75/tarea 91): `logos/clientes/logo-{timestamp}.{ext}`. La diferencia con el caso de ADR 0019 es que acá SÍ hay muchos objetos — un cliente por fila de `com_clientes` — compartiendo una sola carpeta plana, distinguidos solo por el timestamp de subida. Dos subidas de clientes distintos en el mismo segundo colisionan de nombre, y no hay forma de aislar "los archivos del cliente X" a nivel de directorio — solo leyendo `logo_path` en la base.

El dueño pidió además dejar sentado el criterio para lo que todavía no existe: exportaciones de reportes (Excel u otro formato — hoy no hay ninguna implementada, no hay `maatwebsite/excel` en el proyecto) y, en general, cualquier archivo futuro que un caso de uso escriba a disco. Este ADR no construye esa funcionalidad; deja la ruta decidida para cuando se construya.

## Decisión

### 1. Regla general: anidar por el id del objeto dueño cuando puede haber muchos

Cuando una categoría de archivo tiene (o puede llegar a tener) más de un objeto dueño posible, el path siempre anida por su id **antes** del nombre de archivo:

```
{categoría}/{id_del_objeto}/{nombre-corto}-{timestamp}.{ext}
```

Esto ya es lo que hacen evidencias (`evidencias/{tipo}/{yyyy}/{mm}/{uuid_cliente}-{id}.{ext}`) y los PDF generados (`actas/{trabajo_id}/{acta_id}.pdf`, `reportes-tecnicos/{trabajo_id}/{reporte_id}.pdf`, `planillas/{planilla_id}/{detalle_id}.pdf`, todos por ADR 0009). Este ADR solo lo hace explícito como regla general y cierra la única categoría que no la seguía: el logo de cliente.

### 2. La excepción de "fila única" queda acotada al logo de empresa de ADR 0019

Nada más se exime de anidar por id. Aunque una categoría nueva tenga pocos registros al principio, se trata como "muchos objetos" desde el día uno — no hay forma de saber si va a seguir siendo pocos.

### 3. Corrección: logo de cliente pasa a anidar por `cliente_id`

`logos/clientes/logo-{timestamp}.{ext}` (sin ADR propio, ver "Contexto") pasa a:

```
logos/clientes/{cliente_id}/logo-{timestamp}.{ext}
```

Los archivos ya subidos con la ruta vieja no se mueven ni se reescriben: `logo_path` guarda la ruta completa por fila, así que un cliente con logo subido antes de este ADR sigue resolviendo bien. Solo las subidas nuevas (`CrearCliente::reemplazarLogo`, `ActualizarCliente::reemplazarLogo`) usan la ruta anidada.

### 4. Nombre de archivo: máximo dos palabras + timestamp del servidor

`{nombre-corto}-{timestamp}.{ext}`, nunca el nombre original del archivo subido por el usuario — mismo criterio que ya aplican ADR 0009 y ADR 0019 (evita colisión entre reemplazos sucesivos, invalida caché de navegador/CDN, no expone nombres de archivo ajenos al sistema). El nombre corto describe **qué** es el archivo dentro de esa carpeta (`logo`, `reporte`, `planilla`); de **quién** es lo dice la carpeta, no el nombre.

### 5. Exportaciones futuras (Excel u otro formato)

Cuando se implemente una exportación de reportes o listados, sigue el mismo patrón que los PDF de ADR 0009:

```
exportaciones/{tipo_reporte}/{entidad_id}/{nombre-corto}-{timestamp}.{ext}
```

Disco según sensibilidad: `r2` si es un archivo privado que debe expirar (mismo criterio que evidencias/reportes), `public` si no. Este ADR no implementa ninguna exportación — deja la ruta decidida para no reabrir esta discusión ese día.

### 6. Fuera de alcance: archivos de respaldo

Hoy no existe una funcionalidad de respaldo (backups de base de datos u otro archivo) ni un ADR que la defina — cuándo se dispara, dónde vive, cuánto se retiene son preguntas de una decisión futura y más grande que esta. Queda anotado acá solo para que, cuando se aborde, siga el mismo principio de "categoría propia con su propia regla" que ya fijó ADR 0009 — nunca una carpeta `uploads/` genérica con de todo adentro.

## Alternativas descartadas

- **Carpeta genérica `uploads/` con subcarpetas por tipo de archivo** (imágenes, excel, pdf): agrupar por tipo de archivo en vez de por objeto dueño no resuelve el problema real — aislar los archivos de un objeto — y mezcla en una misma carpeta archivos de negocio con reglas de vida distintas (evidencia inmutable vs. logo reemplazable), que es exactamente lo que ADR 0009 ya había descartado al definir categorías por propósito.
- **Prefijar el id en el nombre de archivo en vez de anidar en carpeta** (`logos/clientes/logo-42-{timestamp}.{ext}`): evita la colisión de nombre, pero no da lo mismo para aislar por directorio según propósito y objeto — con carpeta por id, listar, mover o respaldar "todo lo del cliente 42" es una operación de directorio, no un filtro de nombre de archivo.

## Consecuencias

- `CrearCliente` y `ActualizarCliente` (`app/Dominios/Comercial/Aplicacion/`) escriben ahora bajo `logos/clientes/{cliente_id}/...`. No hace falta backfill de los logos existentes.
- Queda como regla explícita para cualquier módulo que en el futuro suba o genere un archivo con más de un objeto dueño posible: anidar por id, nunca carpeta plana compartida.
- No se toca la letra de ADR 0009 ni ADR 0019 — este ADR los generaliza y cierra el caso no documentado que había quedado suelto en el código.
