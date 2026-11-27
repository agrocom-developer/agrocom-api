# Documentos legales públicos: términos y privacidad

**Agrocom · Vigente** · Decisión: ADR 0046 (cuentas y verificación), ADR 0040 (ubicación y contacto) · Política hermana de `seguimiento_y_privacidad.md` y `verificacion_proveedores.md`

Las tiendas de aplicaciones y las apps necesitan dos páginas web públicas que puedan enlazar: los **términos y condiciones del servicio** y la **política de privacidad** de DronAgro. Esta política describe cómo se publican, cómo se versionan y cómo se actualizan.

## Páginas

| Documento | Ruta | Nombre de ruta |
|---|---|---|
| Términos y condiciones del servicio | `GET /terminos` | `legal.terminos` |
| Política de privacidad | `GET /privacidad` | `legal.privacidad` |

- Son públicas: no piden sesión, y quien ya inició sesión también puede abrirlas.
- Usan el sistema de diseño del panel (`templates/legal-layout`, `atoms/texto-legal`, estilos en `resources/css/components/legal-layout.css`, solo con tokens).
- El pie de la página de ingreso enlaza a las dos, y cada página enlaza a la otra.
- Cada documento termina con la sección «Contacto y datos del titular», armada con `config/legal.php`; lo que no está configurado se omite, sin marcas visibles.
- El texto vive en `lang/es/legal.php` (ADR 0013), con la misma redacción de tuteo del resto del sistema. Las vistas no llevan texto en duro.

## Producto y titular

El producto se llama **DronAgro** y el titular es **Agrocom**. DronAgro es un servicio que **conecta** a clientes con quienes prestan la fumigación (pilotos propios, pilotos independientes y dueños de drones), al estilo de un marketplace: Agrocom es el nexo y el registro del estado y del resultado, y no fumiga ni presta el servicio operativo. Los textos nunca dicen lo contrario. El sitio del servicio es `https://drones.agrocom.com.bo` y el de la empresa, `https://agrocom.com.bo`.

## Versión, vigencia y datos del titular

Todo vive en un solo lugar, `config/legal.php`:

- `documentos.terminos` y `documentos.privacidad`, cada uno con `version` (`AAAA-N`) y `vigente_desde` (fecha ISO). Cada documento tiene su propia versión: actualizar uno no obliga a aceptar de nuevo el otro. La vigente es la `2026-2`, desde el 2026-11-27.
- Los plazos que el texto cita: `conservacion_verificacion_meses` (12), `conservacion_verificacion_dias_sin_antecedentes` (90), `eliminacion_gracia_dias` (7), `eliminacion_plazo_dias` (30), `respaldos_dias` (30), `conservacion_servicio_meses` (12) y `conservacion_emergencia_dias` (90). Deben coincidir con los de `eliminacion_de_cuenta.md`; si uno cambia allá, cambia aquí y sube la versión.
- `producto`, `titular`, `sitio`, `sitio_empresa` y `direccion`, con sus valores vigentes por defecto.
- Datos que todavía no están definidos y entran por entorno, con valor nulo: `empresa.razon_social` (`LEGAL_RAZON_SOCIAL`), `empresa.nit` (`LEGAL_NIT`), `empresa.representante` (`LEGAL_REPRESENTANTE`), `empresa.jurisdiccion` (`LEGAL_JURISDICCION`), `contacto.correo` (`LEGAL_CONTACTO_CORREO`), `contacto.telefono` (`LEGAL_CONTACTO_TELEFONO`), `contacto.whatsapp` (`LEGAL_CONTACTO_WHATSAPP`) y `redes.facebook`, `redes.instagram`, `redes.tiktok` y `redes.youtube` (`LEGAL_RED_*`). La vista omite lo nulo y los textos que dependen de un dato (la jurisdicción, las redes donde se avisan los cambios) solo aparecen cuando existe.

La versión es una constante del código y no una variable de entorno: cambiarla es cambiar un texto legal y pasa por un PR. El registro de la aceptación por cuenta (qué versión de cada documento aceptó, y cuándo) toma estos valores de `config/legal.php` (`sec_aceptaciones`, `GET /api/legal/vigentes`, `legal_pendiente`; ver `tiendas-y-privacidad.md`, T16).

## Cómo se actualiza un documento

Los documentos se van actualizando versión a versión.

1. Se cambia el texto en `lang/es/legal.php`.
2. Si el cambio es de fondo (cambia lo que se recoge, para qué, quién lo ve, cuánto se conserva, o las obligaciones de las partes), se sube la versión del documento en `config/legal.php` (`2026-2` pasa a `2026-3`; al cambiar de año, `2027-1`) y se pone la fecha en que empieza a regir en `vigente_desde`. Una corrección de ortografía o de redacción que no cambia el sentido no sube la versión.
3. Se actualiza la política del repo que respalda el cambio (la tabla de abajo dice cuál) en el mismo PR. El texto legal no dice nada que una política o un ADR no diga.
4. Los cambios se avisan dentro de la app (con la pantalla de aceptación si son de fondo, por `legal_pendiente`), en la propia página con su versión y fecha, y en las redes sociales oficiales cuando estén configuradas. Una página inicial pública para clientes, con formularios de contacto, está prevista y todavía no existe; cuando exista, enlazará a estos documentos y se agregará como medio de aviso.
5. Un abogado puede revisar y afinar el texto en cualquier momento; cada ajuste es una versión nueva.

## De dónde sale cada afirmación

| Tema del texto | Respaldo |
|---|---|
| Qué es Agrocom, nexo y registro, proveedores propios | `negociacion.md`, `marketplace.md`, ADR 0028 |
| Dos apps, un tipo de cuenta por app, sin conversión, una persona con a lo sumo una cuenta de cada tipo | ADR 0046 §1 y §3 |
| Verificación: selfie con cámara y documento (CI, licencia o pasaporte), cada cuenta sube lo suyo, aprobación previa para operar | ADR 0046 §3, §4 |
| Proveedor: licencia, matrículas, seguros, fotos del dron, no ve el mapa ni oferta sin aprobación, fecha de nacimiento por mayoría de edad | `verificacion_proveedores.md`, ADR 0042, ADR 0046 §3 |
| Sin autocontratación ni autocalificación | ADR 0046 §3 punto 10 |
| Negociación: precio por hectárea, rondas, máximo de ofertas, vigencia de 24 horas, adjudicación, precio congelado, nadie queda obligado | `negociacion.md`, `precio_y_pago.md` |
| Negociar requiere red | `CLAUDE.md` de `agrocom-field` (excepción documentada de la negociación con red) |
| Sin cobro en la app, pago fuera, monto estimado y final, tolerancia, aviso de pago recibido | `precio_y_pago.md`, ADR 0034 |
| Durante el servicio: inicio, fotos, avance, cierre, qué ve el cliente, condiciones acordadas en el lote | `seguimiento_y_privacidad.md`, `seguridad.md`, ADR 0040 |
| Incidencias, botón de auxilio, emergencias | `seguridad.md` |
| Cancelaciones | `cancelacion.md` |
| Calificaciones y moderación | `calificacion.md` |
| Conducta, fotos, mensajes | `seguimiento_y_privacidad.md`, `negociacion.md` |
| Reclamos: plazos, proceso, resultados, apelación, seguro | `disputas.md` |
| Suspensión, baja, apelación, alertas sin restricción automática | `verificacion_proveedores.md`, `calificacion.md`, `cancelacion.md`, ADR 0046 §4 punto 12 |
| Datos que se recogen | ADR 0042, ADR 0043, ADR 0046, `seguimiento_y_privacidad.md`, `verificacion_proveedores.md` |
| Quién ve qué y cuándo | `seguimiento_y_privacidad.md` (tabla «Quién ve qué»), ADR 0040 punto 6, ADR 0042 punto 4, ADR 0046 §4 |
| Ubicación: solo en ejecución, última posición, se elimina al finalizar, sin historial ni logs | `seguimiento_y_privacidad.md`, ADR 0040 punto 5, invariante 13 |
| Conservación de mensajes y fotos, calificación inmutable, sin reescribir lo confirmado | `seguimiento_y_privacidad.md`, `calificacion.md`, invariantes 2 y 8 |
| Protección: acceso restringido, URL firmada, borrado de la copia local de la selfie | ADR 0026, ADR 0046 §4 punto 16 |
| Cuenta falsa con documento ajeno: la oficina compara y elimina | ADR 0046 §3 punto 9 |
| Contenido de usuarios, reportar y bloquear, filtro y moderación | `seguimiento_y_privacidad.md`, ADR 0047 (puntos 15 a 19) |
| Eliminación de cuenta, gracia, plazos, qué se conserva | `eliminacion_de_cuenta.md`, ADR 0047 |
| Retención de la selfie y el documento (90 días o 12 meses), registro antifraude, respaldos de 30 días | `eliminacion_de_cuenta.md`, ADR 0046 §33, ADR 0047 |
| Ubicación de emergencia (90 días tras cerrar la incidencia) | `seguridad.md`, `seguimiento_y_privacidad.md`, ADR 0047 |
| Edad mínima de 18 años | `tiendas-y-privacidad.md` (1.8) |
| Sin costo por ahora; precios con aviso previo | `precio_y_pago.md`, ADR 0048 (exploratorio) |
| Contraseñas y códigos con hash, token revocable de 30 días, límites de intentos, HTTPS | `docs/seguridad/autenticacion.md` |
| Proveedores de infraestructura (correo SMTP, WhatsApp Business y SMS, almacenamiento S3, mapas) | `docs/seguridad/autenticacion.md`, ADR 0009, `tiendas-y-privacidad.md` (1.3, 1.12) |
| Derechos del usuario y canal de contacto | `tiendas-y-privacidad.md` (2.3, T15) y la decisión del dueño del 27/11/2026 |
| Responsabilidades entre las partes | `seguridad.md`, `disputas.md`, `tiendas-y-privacidad.md` (1.4 y 1.9) |

Los textos de responsabilidad, derechos y plazos de respuesta (30 días) salen de la decisión del dueño del 27/11/2026 y de los requisitos de las tiendas descritos en `tiendas-y-privacidad.md`; los ajustes de código que esos textos suponen están en la tabla «términos → ajuste de código» de ese documento.

## Revisión

Los dos documentos salen sin aviso de borrador por decisión del dueño. Un abogado boliviano puede afinarlos (las preguntas están en la sección 3 de `tiendas-y-privacidad.md`); su revisión entra como una versión nueva.

## Pruebas

`tests/Feature/Legal/PaginasLegalesTest.php` verifica que las dos rutas existen, responden sin sesión, muestran la versión y la vigencia, no llevan aviso de borrador ni marcas pendientes, resuelven todos los marcadores y omiten o muestran los datos de contacto según estén configurados. `ClavesDeIdiomaTest`, `RedaccionNeutraTest` y `TokensColorTest` cubren las claves de idioma, el tuteo y el uso de tokens.
