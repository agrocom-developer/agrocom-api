# Glosario del proyecto

**Agrocom SRL · Documento oficial vigente**

Referencia rápida de vocabulario, de negocio y técnico, para que cualquier persona o agente de IA entienda un término sin leer toda la especificación. Cuando un término tiene definición formal en otro documento, se referencia en vez de repetirla.

## Marketplace

| Término | Definición | Referencia |
|---|---|---|
| **Marketplace** | Plataforma donde clientes publican solicitudes de fumigación con drones y proveedores ofertan; el precio se negocia. Agrocom es el nexo y el registro del resultado | `especificacion/marketplace.md` |
| **Cliente** | Tipo de cuenta de app: quien publica la solicitud, elige la oferta, sigue el avance y califica; persona o empresa; debe estar aprobado para publicar y aceptar | especificación §2; ADR 0046 |
| **Cuenta de panel** | Cuenta del personal de Agrocom; tiene un solo rol, Administrador u Operador | ADR 0046 |
| **Cuenta de app** | Cuenta de una persona en una de las dos apps; es de cliente o de proveedor, sin roles ni conversión entre tipos; con su propia verificación y aprobación | ADR 0046 |
| **Administrador** | Rol del panel que ve todo: verifica y aprueba, atiende, modera y configura | ADR 0046 |
| **Operador** | Rol del panel que ve las partes de algunos módulos que el administrador le asigna | ADR 0046 |
| **Persona** (`sec_personas`) | Persona real dueña de una o dos cuentas de app, identificada por su documento (único): nombres, apellidos, documento y celular. Tiene a lo sumo una cuenta de cliente y una de proveedor | ADR 0046 |
| **Verificación de identidad** | Selfie tomada con la cámara y documento (carnet de identidad, licencia de conducir o pasaporte) que cada cuenta de app necesita para ser aprobada | ADR 0046; `politicas/verificacion_proveedores.md` |
| **Selfie** | Foto del rostro tomada en el momento con la cámara de la app, distinta de la foto de perfil; solo la ve quien revisa | ADR 0046 |
| **Cargada por administrador** | Marca de una verificación que el administrador creó desde el panel; si no hay documento de la persona, lleva un archivo marcador generado por el sistema y el motivo | ADR 0046 |
| **Solicitud de servicio** | Pedido del cliente sobre sus lotes: hectáreas, tipo de aplicación, producto, fecha deseada y precio por hectárea propuesto. Es la unidad de negocio | especificación §4.2; ADR 0027 |
| **Terreno** | Superficie del cliente con geometría y hectáreas, sobre la que se pide un servicio | especificación §4.1 |
| **Proveedor** | Entidad que presta el servicio: propio, independiente o tercero | ADR 0028 |
| **Proveedor propio** | Agrocom como proveedor, con sus pilotos y drones; compite como cualquier otro | ADR 0028 |
| **Proveedor independiente** | Piloto que es su propio proveedor; se registra solo (o lo registra la oficina) y lo aprueba un administrador | ADR 0028, 0032, 0046 |
| **Proveedor tercero** | Empresa o dueño de drones con pilotos a su cargo | ADR 0028 |
| **Piloto** | Persona que opera el dron, ve el mapa de solicitudes abiertas, ofrece y marca el avance | especificación §2 |
| **Dron (vehículo)** | Activo que presta el servicio, con dueño y operador; requiere aprobación | ADR 0029 |
| **Dueño del dron** | Proveedor que posee el dron, aunque lo opere otro | ADR 0029 |
| **Equipo** | Combinación operativa de piloto y dron que ejecuta una solicitud | especificación §4.4 |
| **Integrantes del equipo del servicio** | Personas que el proveedor anota, de forma opcional y solo como información, junto al piloto (nombre y celular opcional); sin cuenta ni verificación; el cliente las ve solo tras adjudicar | ADR 0046 |
| **Mapa de solicitudes abiertas** | Vista del piloto con las solicitudes `abierta` y `con_ofertas`, filtros por zona, hectáreas y precio, y resumen por zona | ADR 0041 |
| **Oferta** | Precio por hectárea que una parte propone en una negociación, con vencimiento; se acepta, se contraoferta o se rechaza | especificación §4.5, §5.3 |
| **Negociación** | Conjunto de ofertas entre el cliente y un proveedor sobre una solicitud; hay una sola oferta vigente y un máximo de ofertas | ADR 0030 |
| **Adjudicación** | Aceptar la oferta vigente de la contraparte: fija el precio acordado, el proveedor y el equipo y cierra las demás negociaciones | ADR 0030 |
| **Expirada** | Estado de la solicitud que venció sin adjudicar; el cliente puede renovarla | especificación §5.1 |
| **Precio por hectárea** | Precio que el cliente propone y que se negocia; se congela al adjudicar como precio acordado | ADR 0030, 0034 |
| **Precio final** | Hectáreas ejecutadas por precio acordado; es el monto a pagar, fuera de la app | ADR 0034 |
| **Avance** | Registro del piloto con hectáreas acumuladas o porcentaje, junto con fotos; lo ve el cliente | ADR 0040 |
| **Mensajes** | Chat de texto entre el cliente y el proveedor adjudicado | ADR 0040 |
| **Ubicación del piloto** | Última posición de la app del piloto durante la ejecución; se elimina al finalizar | ADR 0040 |
| **Preferencias de notificación** | Categorías de aviso que cada cuenta activa o desactiva, con zonas de interés para las nuevas solicitudes | ADR 0041 |
| **Evidencia** | Foto comprimida (menos de 300 KB) que respalda un avance, una incidencia o una disputa | especificación §4.6 |
| **Calificación** | Valoración de 1 a 5 estrellas con etiquetas que el cliente da a un proveedor; el proveedor la ve y un administrador la modera | ADR 0031 |
| **Moderación** | Ocultamiento con motivo y autor de una calificación que incumple la política | `politicas/calificacion.md` |
| **Disputa** | Reclamo sobre un servicio finalizado, resuelto por un administrador con evidencia y un monto ajustado | ADR 0033 |
| **Incidencia** | Evento anómalo (deriva, caída, lesión, derrame, conflicto) reportado durante o tras un servicio | `politicas/seguridad.md` |
| **SOS** | Botón de emergencia que avisa a soporte con la ubicación | `politicas/seguridad.md` |
| **Tasa de cancelación** | Proporción de servicios adjudicados que cancela una parte; es visible y alimenta alertas, no sanciones automáticas | ADR 0033 |
| **Suspensión** | Retiro de la cuenta por documentos vencidos, incidencia grave o decisión de un administrador | `politicas/verificacion_proveedores.md` |
| **Ciclos de batería** | Contador conservado dentro del dron | ADR 0029 |
| **Deriva** | Arrastre de la gota fuera del lote por viento; riesgo para vecinos y colmenas | `politicas/seguridad.md` |
| **Tanque del dron** | Depósito líquido del dron; su capacidad se muestra en la oferta | especificación §4.4 |

## Técnico

| Término | Definición | Referencia |
|---|---|---|
| **`uuid_cliente`** | Identificador único generado en el dispositivo antes de sincronizar; garantiza idempotencia | especificación §10.3 |
| **Outbox** | Cola local donde toda escritura offline se encola antes de sincronizar | especificación §13 |
| **Idempotencia** | Reintentar la misma operación no cambia el resultado; se logra con `UNIQUE (uuid_cliente)` | ADR 0001 |
| **`secuencia`** | Contador local de cada dispositivo que ordena sus registros; el orden causal no usa el reloj | especificación §4.6 |
| **Flavor** | Variante de la app móvil: `cliente` o `piloto` | especificación §13 |
| **`sec_permission`** | Código de permiso abstracto que el menú referencia | ADR 0004 |
| **Policy** | Clase de Laravel que responde si un usuario puede actuar sobre un registro concreto | ADR 0004 |
| **ADR** | Documento corto en `docs/decisiones/` con contexto, decisión, alternativas y consecuencias | `README.md` |
| **Parámetro** | Valor de negocio configurable sin desplegar | ADR 0016 |
| **`feature/*`** | Rama de GitFlow simplificado para una tarea | ADR 0006 |
| **Soft delete** | Borrado lógico (`deleted_at`) | ADR 0007 |
| **Bitácora (de auditoría)** | Registro transversal de quién hizo qué, cuándo y sobre qué | ADR 0007 |
| **Atomic Design** | Metodología de componentes del panel Blade | ADR 0002 |
| **Token (de diseño)** | Variable CSS que representa un color, espaciado o tipografía; nunca se hardcodea un valor | ADR 0002 |
