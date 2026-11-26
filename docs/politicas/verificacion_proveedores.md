# Política de verificación de proveedores, pilotos y drones

**Agrocom SRL · Vigente** · Decisión: ADR 0032, ADR 0042, ADR 0046 · Especificación: `docs/especificacion/marketplace.md` §4.3, §5.2, §9.1

## Quién se verifica

Hay dos apps y dos tipos de cuenta: **cliente** y **proveedor** (el piloto). Cada app admite solo su tipo; cada cuenta tiene su propio usuario y contraseña, su propia verificación y su propia aprobación. Quien es cliente y quiere ser proveedor se registra de nuevo en la app del piloto; no hay conversión de una cuenta en otra.

Cualquiera puede registrarse, entrar y ver las pantallas de su app. Pero **sin la cuenta aprobada** el cliente no publica solicitudes ni acepta o contraoferta ofertas, y el proveedor no ofrece, contraoferta ni acepta. El proveedor que aún no está aprobado no ve el mapa, ni lotes, clientes o ubicaciones: ve solo un número, «hay N solicitudes abiertas que no puedes ver hasta que te aprueben», que puede filtrar por departamento, para saber cuántos trabajos está perdiendo.

**Teléfono y correo.** Quien se registra da su **teléfono**, que es su dato de referencia para entrar, y un **correo de respaldo**. El teléfono es único por tipo de cuenta: no hay dos cuentas de cliente con el mismo teléfono ni dos de proveedor; el mismo teléfono sí puede estar en una cuenta de cliente y una de proveedor. Quien se registra con un teléfono (o un documento) que ya tiene cuenta de ese tipo ve «Ya tienes una cuenta de cliente. Inicia sesión; si te falta completar algo, como la selfie o los documentos, la app te lo pide al entrar»; no se crea otra cuenta. Una persona tiene a lo sumo una cuenta de cada tipo y no se registra dos veces en el mismo.

**Qué prueba y qué no prueba el teléfono.** Que el teléfono sea único evita cuentas repetidas, pero **no prueba que quien se registra sea su dueño**: alguien podría registrar primero el teléfono de otra persona. La prueba de posesión es un código por SMS o WhatsApp, que Agrocom sumará cuando tenga un proveedor para enviarlo. Mientras tanto, el control real de identidad es la aprobación de un administrador con la selfie y el documento, y el teléfono cuenta como dato de contacto y de autenticación sin posesión verificada: la revisión lo muestra como «Teléfono sin verificar», y el administrador puede comprobarlo a mano (llamando o escribiendo al número) antes de aprobar, sobre todo a los proveedores, cuyo teléfono ve el cliente tras adjudicar. Quien crea que alguien registró su teléfono o su documento escribe a la oficina: el administrador compara y elimina la cuenta falsa.

**Recuperar el acceso y cambiar el teléfono** se hacen desde la misma app con un código que llega al correo de respaldo (no hay página web de restablecimiento): con el código se fija una contraseña nueva o se confirma el teléfono nuevo, que también debe ser único para su tipo de cuenta.

**Cuentas separadas, verificaciones separadas.** Quien ya es cliente y se registra además como proveedor sube de nuevo **todo**: la selfie con cámara, el documento y lo propio del proveedor. No se reutiliza nada de la otra cuenta; solo pueden compartir el mismo teléfono. Los datos de la persona (nombres, apellidos, documento) se enlazan por el documento y no se duplican.

**Términos y privacidad.** Al registrarse se acepta la versión vigente de los términos y de la política de privacidad; se guarda qué versión y cuándo, por cuenta, y se pide aceptar de nuevo cuando cambian.

## Verificación de identidad (cliente y proveedor)

La persona sube, desde la app de su cuenta:

1. **Una selfie tomada con la cámara en ese momento.** La app no permite elegirla de la galería, y es distinta de la foto de perfil. Solo la ve quien revisa; las otras partes ven únicamente la insignia «Verificado».
2. **Un documento que demuestre que es una persona real:** el carnet de identidad, la licencia de conducir o el pasaporte declarados al registrarse.

Quien revisa compara la selfie con el documento y con los datos declarados, y aprueba o rechaza con motivo. Un rechazo permite reenviar; lo ya revisado no se reescribe, se envía uno nuevo. **Cada cuenta sube su propia selfie y su propio documento**, aunque la persona ya esté verificada con su otra cuenta: así se prueba que quien maneja esta cuenta es la persona. Si la otra cuenta ya está aprobada, la ficha muestra su selfie y su documento al lado de los nuevos para que la comparación sea rápida. La selfie y el documento los ve solo quien tiene el permiso sensible para revisarlos, y cada vez que alguien los abre queda registrado quién, cuándo y de qué verificación.

## Qué más se verifica

| Sujeto | Documentos obligatorios |
|---|---|
| Proveedor | Documento fiscal si es empresa, domicilio de operación, seguro de responsabilidad civil que cubra aplicación de agroquímicos |
| Piloto | Licencia o habilitación de piloto remoto vigente, certificado de aplicador de agroquímicos si la normativa local lo exige, antecedentes |
| Dron | Registro o matrícula ante la autoridad aeronáutica, habilitación para uso agrícola, número de serie, marca y modelo, fotos del equipo (frontal, lateral y placa de serie como mínimo), seguro vigente |

La identidad del titular y del piloto la cubre la verificación de identidad. El piloto además registra nombres, apellidos, teléfono y foto de perfil como parte de los datos de su persona. La normativa local puede añadir requisitos; el catálogo de documentos se ajusta por parámetro.

Un cliente o un proveedor puede ser una empresa y cargar, de forma opcional, logo, NIT, razón social y nombre comercial.

## Proceso

1. El solicitante carga su verificación de identidad y, si es proveedor, sus documentos. La cuenta queda `pendiente`.
2. Un administrador revisa la verificación y cada documento y los aprueba o rechaza con motivo.
3. Un cliente con su verificación aprobada pasa a `aprobado` y puede publicar y aceptar ofertas. Un proveedor con su verificación aprobada, al menos un piloto y un dron aprobados (el dron con sus fotos obligatorias aprobadas) pasa a `aprobado`, ve el mapa de solicitudes abiertas y puede ofertar. Mientras no está aprobado, el proveedor no ve el mapa de solicitudes abiertas.
4. Los proveedores propios pasan por el mismo proceso.

## Alta desde el panel

El administrador puede crear cuentas de app desde el panel y aprobarlas él mismo, con la misma regla de una cuenta de cada tipo por persona; nadie aprueba su propia cuenta desde la app. Si la persona no tiene su documento a la mano, el administrador adjunta el que tenga o deja un archivo marcador que genera el sistema, y escribe el motivo («verificado en persona en la oficina»). La verificación queda marcada como **«cargada por administrador, sin documento de la persona»** en la ficha, la cola de revisión, el historial y la bitácora, y la app le pide a la persona completar su selfie y su documento. La marca existe para que nadie confunda una aprobación por decisión de la oficina con una verificación documental.

## Pilotos de una empresa

Cada piloto de una empresa tiene su **cuenta propia** de proveedor, con su verificación de identidad y su licencia, y la empresa tiene un **encargado** con su propia cuenta, que gestiona el vínculo. El vínculo se crea por invitación del encargado o por solicitud del piloto con el código de la empresa, y necesita el consentimiento de los dos. El encargado puede **liberar** al piloto para que trabaje por su cuenta con la misma cuenta, sin volver a registrarse como piloto ni repetir su selfie y su documento; para ofrecer por su cuenta, el piloto liberado completa lo que pide un proveedor independiente (dron con fotos, seguro, domicilio de operación) y un administrador lo aprueba. El cliente ve el equipo, y quién es el piloto, solo después de adjudicar. Quién ofrece en nombre de la empresa está por decidir (ADR 0046, punto 38); mientras tanto ofrece el encargado con un equipo de la empresa.

## Equipo del servicio

El proveedor puede registrar, de forma opcional, nombre y celular de las personas que forman su equipo. Son datos informativos, sin cuenta ni verificación. El cliente los ve junto al piloto solo después de adjudicar.

## Vigencia

- Avisos 30 y 7 días antes del vencimiento.
- Documento vencido: el sujeto queda suspendido de forma automática hasta que se cargue uno vigente.
- Un dron o piloto suspendido saca a su equipo de las ofertas.

## Reverificación y baja

- Revisión anual de toda la documentación.
- Suspensión inmediata por incidente grave probado, falsedad documental o reclamo de la autoridad.
- Baja definitiva por fraude, daño intencional o reincidencia en suspensión.
- Toda suspensión o baja incluye motivo, autor y vía de apelación.

## Privacidad

Los documentos, la selfie y el documento de identidad se guardan con acceso restringido a quien tiene permiso para revisarlos (ADR 0026) y no se escriben en logs ni en la bitácora (sí queda registrado cada acceso); la app borra su copia local al subirlos. **Se conservan mientras exista la cuenta y 12 meses después de darla de baja**, por temas legales; las verificaciones rechazadas o reemplazadas, 90 días. Cumplido el plazo se eliminan los archivos y, si la persona ya no tiene ninguna cuenta viva ni un reclamo abierto, se anonimizan sus datos de identidad; cada purga queda registrada en la bitácora sin los datos eliminados. El cliente ve el nombre, la foto de perfil, la calificación, el modelo y las fotos aprobadas del dron y que el proveedor está verificado, no los documentos. El teléfono y el documento del piloto, y los datos de su equipo, los ve solo tras adjudicar.
