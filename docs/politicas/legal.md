# Documentos legales públicos: términos y privacidad

**Agrocom SRL · Borrador, pendiente de revisión legal** · Decisión: ADR 0046 (cuentas y verificación), ADR 0040 (ubicación y contacto) · Política hermana de `seguimiento_y_privacidad.md` y `verificacion_proveedores.md`

Las tiendas de aplicaciones y las apps necesitan dos páginas web públicas que puedan enlazar: los **términos y condiciones del servicio** y la **política de privacidad**. Esta política describe cómo se publican, cómo se versionan y cómo se actualizan.

## Páginas

| Documento | Ruta | Nombre de ruta |
|---|---|---|
| Términos y condiciones del servicio | `GET /terminos` | `legal.terminos` |
| Política de privacidad | `GET /privacidad` | `legal.privacidad` |

- Son públicas: no piden sesión, y quien ya inició sesión también puede abrirlas.
- Usan el sistema de diseño del panel (`templates/legal-layout`, `atoms/texto-legal`, estilos en `resources/css/components/legal-layout.css`, solo con tokens).
- El pie de la página de ingreso enlaza a las dos, y cada página enlaza a la otra.
- El texto vive en `lang/es/legal.php` (ADR 0013), con la misma redacción de tuteo del resto del sistema. Las vistas no llevan texto en duro.

## Versión y vigencia

Todo vive en un solo lugar, `config/legal.php`:

- `documentos.terminos` y `documentos.privacidad`, cada uno con `version` (`AAAA-N`, por ejemplo `2026-1`) y `vigente_desde` (fecha ISO). Cada documento tiene su propia versión: actualizar uno no obliga a aceptar de nuevo el otro.
- `conservacion_verificacion_meses`: los meses que se conservan la selfie y el documento de verificación tras la baja de una cuenta (12). El texto de privacidad lo lee de aquí.
- `borrador`: aviso «BORRADOR: pendiente de revisión legal» (variable de entorno `LEGAL_BORRADOR`, activado por defecto).

La versión es una constante del código y no una variable de entorno: cambiarla es cambiar un texto legal y pasa por un PR. El registro de la aceptación por cuenta (qué versión de cada documento aceptó, y cuándo) toma estos valores de `config/legal.php`; ese registro no existe todavía.

## Cómo se actualiza un documento

1. Se cambia el texto en `lang/es/legal.php`.
2. Si el cambio es de fondo (cambia lo que se recoge, para qué, quién lo ve, cuánto se conserva, o las obligaciones de las partes), se sube la versión del documento en `config/legal.php` (`2026-1` pasa a `2026-2`; al cambiar de año, `2027-1`) y se pone la fecha en que empieza a regir en `vigente_desde`. Una corrección de ortografía o de redacción que no cambia el sentido no sube la versión.
3. Se actualiza la política del repo que respalda el cambio (la tabla de abajo dice cuál) en el mismo PR. El texto legal no dice nada que una política o un ADR no diga.
4. Se revisa con un abogado antes de publicar. Mientras no haya aprobación, `LEGAL_BORRADOR` queda activado y el aviso se ve en las dos páginas.
5. Al publicar la versión aprobada, el entorno que publica pone `LEGAL_BORRADOR=false`. Cómo se avisa a las cuentas existentes y cómo se registra la nueva aceptación está por definir.

## Aviso de borrador

El texto actual es un borrador redactado solo con lo que ya dicen las políticas y los ADR. No afirma validez legal ni cita leyes. Un abogado debe revisar y aprobar los dos documentos antes de enlazarlos desde las fichas de las tiendas.

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

Dos afirmaciones **no** salen de una política o un ADR del repo, sino de una indicación del dueño al encargar las páginas, y están por documentar en la política que corresponda:

- Selfie y documento se conservan 12 meses después de dar de baja la cuenta y luego se eliminan.
- La persona puede pedir acceso, corrección y eliminación de sus datos.

El ADR 0046 está aceptado, pero al redactar este documento todavía no estaba integrado en `develop` (vive en la rama `feature/adr-cuentas-y-verificacion`). Si cambia al integrarse, se ajustan las secciones de cuentas y verificación del texto.

## Lo que está por definir

El texto lleva la marca visible `[POR DEFINIR]` en cada punto sin decisión:

- Cómo y cuándo se registra la aceptación de los términos, y cómo se avisa y se acepta un cambio.
- Edad mínima para usar la app del cliente, y uso de la app por menores.
- Cómo se remunera Agrocom por la intermediación.
- Alcance y límites de la responsabilidad de Agrocom; ley aplicable y forma de resolver diferencias.
- Domicilio y datos de registro de Agrocom SRL como responsable de los datos.
- Terceros que procesan datos (alojamiento, avisos, mapas) y si algún dato sale del país.
- Plazos de conservación de mensajes y fotos de un servicio, y de la eliminación definitiva del resto de los datos.
- Aviso a las personas ante un incidente de seguridad.
- Canal para ejercer los derechos (acceso, corrección, eliminación) y plazo de respuesta.
- Contacto (correo, teléfono o formulario) en los dos documentos.

Cada vez que una decisión cierra un punto, se actualiza la política respaldatoria, se reescribe la frase en `lang/es/legal.php` sin la marca y se sube la versión si cambia el fondo.

## Pruebas

`tests/Feature/Legal/PaginasLegalesTest.php` verifica que las dos rutas existen, responden sin sesión, muestran la versión y la vigencia, y que el aviso de borrador sale con el parámetro activado y desaparece al apagarlo. `ClavesDeIdiomaTest`, `RedaccionNeutraTest` y `TokensColorTest` cubren las claves de idioma, el tuteo y el uso de tokens.
