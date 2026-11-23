# Glosario del proyecto

**Agrocom SRL · Documento oficial vigente**

Referencia rápida de vocabulario, de negocio y técnico, para que cualquier persona o agente de IA entienda un término sin leer toda la especificación. Cuando un término tiene definición formal en otro documento, se referencia en vez de repetirla.

## Marketplace

| Término | Definición | Referencia |
|---|---|---|
| **Marketplace** | Plataforma donde clientes solicitan servicios de fumigación con drones y proveedores los ejecutan. Agrocom capta clientes, es el nexo y también proveedor | `especificacion/marketplace.md` |
| **Cliente** | Quien solicita el servicio, firma la conformidad y califica | especificación §2 |
| **Solicitud de servicio** | Pedido del cliente sobre un terreno: hectáreas, tipo de aplicación, producto, ventana de fechas y tarifa. Es la unidad de negocio | especificación §4.2; ADR 0027 |
| **Terreno** | Superficie del cliente con geometría y hectáreas, sobre la que se pide un servicio | especificación §4.1 |
| **Proveedor** | Entidad que presta el servicio: propio, independiente o tercero | ADR 0028 |
| **Proveedor propio** | Agrocom como proveedor, con sus pilotos y drones; recibe la primera oferta | ADR 0028, 0030 |
| **Proveedor independiente** | Piloto que es su propio proveedor; se registra solo y lo aprueba un administrador | ADR 0028, 0032 |
| **Proveedor tercero** | Empresa o dueño de drones con pilotos y auxiliares a su cargo | ADR 0028 |
| **Piloto** | Persona que opera el dron y escribe la sesión de vuelo | especificación §2 |
| **Auxiliar** | Persona que prepara la mezcla y asiste en el lote; lo registra el piloto o el proveedor y lo aprueba un administrador | ADR 0032 |
| **Dron (vehículo)** | Activo que presta el servicio, con dueño y operador; requiere aprobación | ADR 0029 |
| **Dueño del dron** | Proveedor que posee el dron, aunque lo opere otro | ADR 0029 |
| **Equipo** | Combinación operativa de piloto, dron y auxiliar opcional que ejecuta una solicitud | especificación §4.4 |
| **En línea** | Estado en que un proveedor declara estar disponible para recibir ofertas | especificación §4.4 |
| **Oferta** | Propuesta de una solicitud a un proveedor, con vencimiento; se acepta o se rechaza | especificación §4.5, §5.3 |
| **Ronda** | Tanda de ofertas a un grupo de proveedores (propios primero, luego terceros por radio) | ADR 0030 |
| **Escalado** | Ampliación del radio y del grupo de proveedores cuando una ronda no consigue aceptación | ADR 0030 |
| **Sin proveedor** | Estado de la solicitud cuando se agotaron las rondas | especificación §5.1 |
| **Tarifa por hectárea** | Precio del servicio por hectárea, con recargos explícitos y congelado al publicar | ADR 0027 |
| **Comisión** | Porcentaje del monto final que Agrocom devenga por solicitud completada | ADR 0034 |
| **Acta de conformidad** | Documento que el cliente firma para confirmar la ejecución; el silencio durante el plazo equivale a conformidad | especificación §4.6, §5.1 |
| **Sesión de vuelo** | Unidad continua de trabajo de un equipo en el lote con hectáreas, litros, condiciones y evidencias; se registra sin conexión | especificación §4.6, §5.4 |
| **Mezcla (caldo)** | Tanque de caldo preparado por el auxiliar según la receta de la solicitud, ligado a una sesión | especificación §4.6 |
| **Condición forzada** | Vuelo realizado fuera de los límites de clima, con motivo registrado y visible | `politicas/seguridad.md` |
| **Evidencia** | Foto o registro comprimido (menos de 300 KB) que respalda una sesión, incidencia o disputa | especificación §4.6 |
| **Calificación** | Valoración de 1 a 5 estrellas con etiquetas que el cliente da a un proveedor; el proveedor la ve y un administrador la modera | ADR 0031 |
| **Moderación** | Ocultamiento con motivo y autor de una calificación que incumple la política | `politicas/calificacion.md` |
| **Disputa** | Reclamo sobre un servicio ejecutado, resuelto por un administrador con evidencia | ADR 0033 |
| **Incidencia** | Evento anómalo (deriva, caída, lesión, derrame, conflicto) reportado durante o tras un servicio | `politicas/seguridad.md` |
| **SOS** | Botón de emergencia que avisa a soporte con la ubicación | `politicas/seguridad.md` |
| **Tasa de aceptación y de cancelación** | Proporción de ofertas aceptadas y de solicitudes canceladas por el proveedor; entran en la puntuación y en las restricciones | especificación §6 |
| **Suspensión** | Retiro temporal de ofertas por calificación, documentos vencidos, incidencia grave o comisión vencida | `politicas/verificacion_proveedores.md` |
| **Ciclos de batería** | Contador conservado dentro del dron | ADR 0029 |
| **Deriva** | Arrastre de la gota fuera del lote por viento; riesgo para vecinos y colmenas | `politicas/seguridad.md` |
| **Caldo / calda** | Mezcla líquida de agua y productos que aplica el dron | especificación §4.6 |
| **Tanque del dron** | Depósito líquido del dron; su capacidad entra en la elegibilidad | especificación §4.4 |
| **Boleo** | Aplicación de sólidos al voleo con dron | especificación §4.2 |
| **Captura del RC** | Foto de la pantalla del control remoto al cerrar el lote: hectáreas, tiempo, caudal y altura | especificación §4.6 |
| **Zona GEO** | Geocerca donde el dron no puede volar | `politicas/seguridad.md` |

## Técnico

| Término | Definición | Referencia |
|---|---|---|
| **`uuid_cliente`** | Identificador único generado en el dispositivo antes de sincronizar; garantiza idempotencia | especificación §10.3 |
| **Outbox** | Cola local donde toda escritura offline se encola antes de sincronizar | especificación §13 |
| **Idempotencia** | Reintentar la misma operación no cambia el resultado; se logra con `UNIQUE (uuid_cliente)` | ADR 0001 |
| **`secuencia`** | Contador local de cada dispositivo que ordena sus registros; el orden causal no usa el reloj | especificación §4.6 |
| **Flavor** | Variante de la app móvil: `cliente`, `piloto` o `auxiliar` | especificación §13 |
| **`sec_permission`** | Código de permiso abstracto que el menú referencia | ADR 0004 |
| **Policy** | Clase de Laravel que responde si un usuario puede actuar sobre un registro concreto | ADR 0004 |
| **ADR** | Documento corto en `docs/decisiones/` con contexto, decisión, alternativas y consecuencias | `README.md` |
| **Parámetro** | Valor de negocio configurable sin desplegar | ADR 0016 |
| **`feature/*`** | Rama de GitFlow simplificado para una tarea | ADR 0006 |
| **Soft delete** | Borrado lógico (`deleted_at`) | ADR 0007 |
| **Bitácora (de auditoría)** | Registro transversal de quién hizo qué, cuándo y sobre qué | ADR 0007 |
| **Atomic Design** | Metodología de componentes del panel Blade | ADR 0002 |
| **Token (de diseño)** | Variable CSS que representa un color, espaciado o tipografía; nunca se hardcodea un valor | ADR 0002 |
