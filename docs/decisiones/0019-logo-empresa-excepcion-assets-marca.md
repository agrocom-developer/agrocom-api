# ADR 0019 — Logo de empresa: excepción puntual a "assets de marca" de ADR 0009, subido en runtime al disco `public`

**Estado:** Aceptada · **Fecha:** 11/9/2026 · **Origen:** pedido del dueño de implementar el campo real "Logo de empresa" en `/panel/organizacion` (hoy mock, botones "Reemplazar"/"Quitar" deshabilitados), pensando explícitamente en el pivot futuro a SaaS multi-tenant (todavía sin ADR, sin tabla `tenant`, sin scoping por organización). **Matiza el punto 3 ("Assets de marca") de ADR 0009 — ver "Nota sobre ADR 0009" al final de "Decisión". El resto de ADR 0009 (evidencias, reportes, y el resto de los assets de marca) queda intacto.**

## Contexto

ADR 0009 clasifica el logo, junto con favicon, iconografía propia del panel y plantillas base de PDF, como "asset de marca": "versionados en el propio repositorio (`public/images/marca/` o `resources/`), no en el bucket. Cambian por deploy, no por operación". Bajo esa regla, el campo "Logo de empresa" de `/panel/organizacion` no debería tener nunca botones "Reemplazar"/"Quitar" funcionales — es, tal como el propio `OrganizacionController` lo documenta hoy, un mock deliberado.

En paralelo se está dando persistencia real al resto de "Datos de empresa" (nombre, rubro, email, teléfono, dirección) en una tabla nueva `sec_datos_empresa`, calcada del patrón ya existente de `sec_datos_fiscales`/`GuardarDatosFiscales`/`ActualizarDatosFiscalesRequest` (fila única, sin multi-tenancy, `RegistraBitacora` + soft delete). Ese trabajo no es objeto de este ADR.

El dueño pidió resolver el logo **en el mismo sentido**, pero señaló la razón por la que este campo puntual no puede seguir la regla general de ADR 0009: el plan es vender este software a otras empresas de fumigación con drones, cada una con sus propios datos aislados. El día que eso exista, "el logo" deja de ser un archivo único y fijo del repositorio — hay uno por empresa cliente del SaaS, y ningún deploy puede cargarlos a todos de antemano. Nada de esa infraestructura multi-tenant se construye en este ADR (sin tabla `tenant`, sin `tenant_id`, sin scoping): se decide únicamente cómo modelar el logo *hoy*, de una forma que no haya que reescribir cuando el tenant real llegue.

Sostener la letra literal de ADR 0009 para el resto de la categoría 3 (favicon, iconografía del panel, plantillas de PDF) sigue siendo correcto: esos assets no cambian por operación de ningún usuario, cambian por decisión de diseño del propio producto en un deploy. El logo de empresa es distinto en naturaleza desde el momento en que existe la intención declarada de que cada empresa tenga el suyo.

## Decisión

### 1. Alcance de la excepción: solo el logo de la propia empresa, nada más de la categoría 3

Esta excepción cubre exclusivamente el campo "Logo de empresa" de `/panel/organizacion` (dato de negocio: la marca de la empresa que opera el sistema — hoy Agrocom SRL, a futuro cada tenant). No toca:

- El wordmark propio del panel (`public/logo.png`, consumido por `resources/views/components/atoms/logo.blade.php` y usado en `module-rail`, `module-drawer`, `mobile-topbar`) — es la identidad del *software* Agrocom, no de la empresa que lo opera. Sigue versionado en el repo, deploy-time, sin upload.
- Favicon, iconografía propia del panel y plantillas base de PDF — sin cambios, siguen bajo ADR 0009 tal como está escrito.

Este ADR **no decide** si el logo de empresa debe reemplazar al wordmark del panel en algún lugar (por ejemplo, como membrete de las plantillas PDF de actas/reportes). Eso es una decisión de producto/diseño para una tarea futura concreta — acá solo se resuelve dónde y cómo se guarda el archivo que el usuario sube desde `/panel/organizacion`.

### 2. Dónde se guarda: disco `public` de Laravel (runtime), no `r2`, no `public/images/marca/`

Ni `r2` ni el patrón de "asset versionado en `public/images/marca/`" de ADR 0009 aplican:

- **No es `r2`.** Ese bucket (ADR 0009, categorías 1 y 2) existe para archivos privados que se sirven con URL firmada y expiración — evidencias y reportes. Un logo de empresa es lo opuesto: tiene que ser públicamente visible y estable en el tiempo (aparece en pantalla y, eventualmente, en documentos entregados a clientes), no algo que expire ni que necesite ocultarse.
- **No es "versionado en el repo".** Aclaración necesaria sobre la propia letra de ADR 0009: cuando ese ADR dice que el disco `public` es "local, versionado en el repo", en la práctica describe archivos puestos directamente bajo el webroot como parte del código fuente (`public/logo.png`, sin pasar por el sistema de discos de Laravel) — no el disco de Laravel *llamado* `public` (`config/filesystems.php`, raíz física `storage_path('app/public')`, servido vía symlink `php artisan storage:link`). Ese disco Storage `public` es, con la configuración por defecto de Laravel, un directorio de **runtime**, no versionado en git. Es exactamente lo que este caso necesita: un archivo que el usuario reemplaza sin deploy.

Se usa entonces el disco Storage **`public`**, ruta `logos/empresa/`, servido por el symlink estándar (`public/storage/logos/empresa/...`, sin necesidad de URL firmada — visibilidad pública, igual que cualquier otro asset del disco `public`).

Nombre de archivo generado por el servidor, nunca el nombre original del upload (evita colisiones entre reemplazos sucesivos y permite invalidar caché de navegador/CDN en cada reemplazo):

```
logos/empresa/logo-{timestamp}.{ext}
```

Al reemplazar o quitar, el caso de uso borra el archivo físico referenciado por el `logo_path` anterior antes de guardar el nuevo (o de dejar la columna en `null`) — mismo criterio de "no acumular huérfanos" que ya aplica el resto del sistema a archivos que se sustituyen.

La ruta ya queda armada para el multi-tenant futuro sin rediseño: agregar un segmento (`logos/{tenant_id}/...`) el día que exista `tenant_id` es un cambio de un parámetro en el caso de uso, no un cambio de disco ni de mecanismo.

### 3. Dónde se persiste la referencia: columna `logo_path` en `sec_datos_empresa`, no tabla aparte

`sec_datos_empresa` gana una columna nullable `logo_path` (string, ruta relativa dentro del disco `public`, no URL absoluta — la URL se resuelve en el momento de render con `Storage::disk('public')->url($path)`, para no hornear `APP_URL` en la base y no romper si el dominio cambia entre entornos).

Se descarta una tabla propia para el logo por la misma razón que ADR 0018 descartó una entidad nueva para "el dueño" de un cliente: no hay ningún caso de uso hoy que necesite historial de logos, versionado propio ni ciclo de vida independiente del resto de los datos de la empresa — es un dato más de la fila única, igual que `razon_social_fiscal` lo es de `sec_datos_fiscales`. Vivir en `sec_datos_empresa` además da gratis, sin trait ni columna adicional:

- **Bitácora de auditoría** (invariante 9 de `CLAUDE.md`): `RegistraBitacora` ya cubre toda la fila, así que un cambio de logo queda registrado (quién, cuándo) igual que cualquier otro campo.
- **Soft delete** (invariante 8): heredado de la tabla, sin lógica propia.

El formulario nunca recibe ni escribe `logo_path` directamente: recibe un campo `logo` (`UploadedFile`), y es el caso de uso el que sube el archivo, borra el anterior si corresponde, y calcula la ruta que se persiste — mismo principio que el resto del sistema aplica a todo dato derivado que no debe aceptarse tal cual lo manda el cliente.

### 4. Validación del archivo

```php
'logo' => ['nullable', 'file', 'mimes:png,svg,jpg,jpeg,webp,gif', 'max:20480'],
```

**Actualización 15/9/2026** (pedido directo del dueño): se amplió de `png,svg` a `png,svg,jpg,jpeg,webp,gif`. La alternativa descartada más abajo ("Aceptar cualquier tipo de imagen") decía explícitamente que ampliar sin que nadie lo pidiera era una decisión de producto fuera de alcance de este ADR — dejó de aplicar en cuanto el dueño lo pidió. La ayuda visual (`campo_logo_ayuda`) y el mensaje de error (`error_logo_tipo`) se actualizaron en el mismo cambio para no prometer menos de lo que el campo acepta.

**Segunda actualización 15/9/2026** (mismo pedido, siguiente vuelta): `max:2048` (2 MB) rechazaba de entrada cualquier foto de cámara moderna — el dueño señaló explícitamente que una foto en full HD no puede quedar bloqueada por el input. En vez de subir el rechazo a un número más grande sin más, se invirtió el criterio: `max:20480` (20 MB) pasa a ser solo un tope técnico de subida (protección de disco/DoS, casi nunca alcanzado por un uso normal), y el peso final liviano lo garantiza `App\Dominios\Compartido\Aplicacion\OptimizarImagenSubida` — redimensiona a un lado máximo de 1600 px y recomprime (JPEG/WEBP con calidad decreciente hasta bajar de 2 MB) antes de guardar en disco, corrigiendo también la rotación EXIF de fotos de celular. SVG no pasa por esto: es vector, no tiene "peso por resolución" que optimizar. `error_logo_tamano` pasó de "no puede superar los 2 MB" a "no puede superar los 20 MB" porque ese mensaje ahora describe el tope técnico, no el peso final esperado.

- **`mimes:png,svg`** (redacción original), no la regla `image` de Laravel: `image` excluye SVG explícitamente (por el mismo riesgo de XSS que se señala abajo), y la ayuda visual ya vigente en pantalla (`campo_logo_ayuda`: *"PNG o SVG, fondo transparente recomendado"*) ya le prometió SVG al usuario — restringir a menos de lo que la pantalla anuncia sería un bug de UX, no una decisión de arquitectura nueva.
- **`max:2048`** (redacción original, 2048 KB = 2 MB): el ejemplo mock actual (`mock_logo_peso`, "240 KB") confirma que un logo real pesa un orden de magnitud menos; 2 MB parecía margen generoso sin dejar de acotar el disco — hasta que una foto de cámara real (no un logo diseñado) mostró que ese margen no alcanzaba. Ver "Segunda actualización" arriba.
- **`nullable`**: "Quitar" es una operación válida — vuelve la columna a `null`, y el panel cae al wordmark versionado por defecto para cualquier lugar que hoy ya muestre un logo.

**Nota de endurecimiento, no bloqueante para esta decisión:** un SVG es XML, y puede llevar `<script>` o manejadores de evento embebidos — es la razón por la que Laravel excluye SVG de la regla `image`. Este ADR fija el tipo de archivo permitido; sanear el contenido del SVG antes de guardarlo (o servirlo siempre como descarga/`<img>`, nunca inline ni por `<object>`/`<iframe>`) queda como tarea de endurecimiento para quien implemente el caso de uso — no cambia la decisión de dónde y cómo se guarda el archivo.

**Nota sobre ADR 0009:** la categoría 3 ("Assets de marca") de ADR 0009 sigue vigente sin cambios para favicon, iconografía propia del panel y plantillas base de PDF. Queda **matizada únicamente** para el logo de la propia empresa: ese archivo puntual deja de ser "versionado en el repo, cambia por deploy" y pasa a ser un archivo de runtime, subido por el usuario, en el disco `public` — por la razón de negocio descrita en "Contexto" (pivot a multi-tenant, un logo fijo del repo no alcanza cuando hay una empresa por tenant). Se deja una nota cruzada en el propio ADR 0009 señalando esta excepción, para que quien lea ADR 0009 sin conocer este ADR no se lleve una idea equivocada de que el logo sigue siendo, sin excepción, un asset fijo del repositorio.

## Alternativas descartadas

- **Guardar el logo en `r2`.** Ese bucket existe para archivos privados con URL firmada y expiración (evidencias/reportes, ADR 0009 categorías 1 y 2). Un logo de empresa no tiene ninguna necesidad de confidencialidad ni de expiración — meterlo ahí obligaría a regenerar URLs firmadas constantemente para un archivo que se quiere permanentemente visible.
- **Mantener la letra literal de ADR 0009** (logo fijo versionado en el repo, reemplazable solo por deploy manual del dueño). Es exactamente lo que este pedido busca dejar de hacer, y en el escenario multi-tenant futuro es directamente inviable: no hay "un" logo que versionar, hay uno por empresa cliente del SaaS.
- **Tabla propia (`sec_logos_empresa` o similar) en vez de columna en `sec_datos_empresa`.** Mismo criterio que ADR 0018 aplicó a "el dueño" de un cliente: no se modela una entidad nueva para un dato que no tiene ciclo de vida ni historial propio pedido por nadie.
- **Persistir una URL absoluta (`logo_url`, con dominio incluido) en vez de una ruta relativa (`logo_path`).** Se descarta porque hornea `APP_URL` en la base — rompe o queda inconsistente ante cualquier cambio de dominio entre entornos (local, producción beta de ADR 0017, y cualquier dominio futuro). Guardar la ruta relativa y resolver la URL en el momento de render (`Storage::disk('public')->url($path)`) es el mismo criterio que ya usa `ope_actas.pdf_path` para el mismo problema.
- **Aceptar cualquier tipo de imagen (jpg, webp, etc.).** La ayuda visual ya vigente en pantalla fija el contrato con el usuario en PNG/SVG; ampliar el tipo de archivo aceptado sin que nadie lo haya pedido es una decisión de producto que no corresponde tomar en este ADR. **Superada 15/9/2026**: el dueño pidió ampliarlo — ver "Actualización 15/9/2026" en la sección "Validación del archivo".
- **Nombre de archivo elegido por el usuario (el nombre original del upload).** Se descarta por colisión entre reemplazos sucesivos y porque no permite invalidar caché — el nombre lo genera el servidor.

## Consecuencias

**A favor**

- El campo "Logo de empresa" deja de ser mock sin contradecir en silencio a ADR 0009: la excepción queda escrita, acotada (solo este campo puntual) y justificada (pivot a multi-tenant).
- El resto de la categoría 3 de ADR 0009 (favicon, `public/logo.png`/`ag-logo`, plantillas PDF) sigue exactamente igual — cero regresión sobre lo ya decidido.
- La ruta relativa (`logos/empresa/...`) y la columna `logo_path` ya están armadas para agregar un segmento de tenant el día que exista, sin tocar el disco, el nombre de columna ni el mecanismo de subida.
- Bitácora y soft delete llegan gratis por vivir en `sec_datos_empresa`, sin trait ni columna adicional.

**En contra, y asumido**

- Dos nociones de "logo" conviven en el sistema hasta que una tarea futura decida si deben fusionarse: el wordmark del propio panel (identidad del software) y el logo de empresa (identidad de quien lo opera). Este ADR no las fusiona ni decide reemplazar una por la otra en ningún lugar de renderizado (por ejemplo, plantillas PDF) — queda fuera de alcance.
- El disco `public` de Laravel no es multi-tenant por sí mismo: hoy sigue siendo una única carpeta compartida (`logos/empresa/`), con un solo archivo vivo a la vez, igual que `GuardarDatosFiscales` reutiliza siempre la misma fila. No se construye ninguna partición por tenant en este ADR.
- Requiere que exista el symlink `public/storage` en cada entorno (`php artisan storage:link`) — hoy no está creado en este checkout. Queda como precondición operativa para quien implemente, mismo tipo de nota que ADR 0017 dejó para la instalación de Docker en el contenedor 119: fuera del ADR, pero necesaria antes de que el flujo funcione de punta a punta.
- Sanitización de contenido SVG (más allá de la validación de tipo/tamaño) queda pendiente como tarea de endurecimiento, no bloqueante para esta decisión (ver nota en "Decisión" punto 4).
