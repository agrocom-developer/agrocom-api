<?php

/*
 * Texto de las páginas públicas «Términos y condiciones del servicio» y
 * «Política de privacidad» (`docs/politicas/legal.md`; ADR 0013: ningún texto
 * visible va en duro en una vista).
 *
 * BORRADOR: el texto lo revisa y lo aprueba un abogado antes de publicarse en
 * las tiendas. Cada afirmación sale de una política o ADR del repo (la tabla de
 * trazabilidad está en `docs/politicas/legal.md`); lo que todavía no está
 * decidido lleva la marca literal `[POR DEFINIR]`, que la vista resalta. No
 * se inventan compromisos ni se citan leyes.
 *
 * Estructura de cada documento: `titulo`, `introduccion` (lista de párrafos) y
 * `secciones`, cada una con `titulo`, `parrafos` e `items` (ambos opcionales).
 * El marcador `:meses` se reemplaza con `config('legal.conservacion_verificacion_meses')`.
 * Versión y vigencia no viven acá: están en `config/legal.php`.
 */

return [

    'comun' => [
        'borrador_titulo' => 'BORRADOR: pendiente de revisión legal',
        'borrador_detalle' => 'Este texto todavía no fue revisado ni aprobado por un profesional legal y puede cambiar. Las marcas [POR DEFINIR] señalan lo que falta decidir.',
        'version' => 'Versión :version',
        'vigente_desde' => 'Vigente desde el :fecha',
        'indice' => 'Contenido',
        'indice_aria_label' => 'Secciones de este documento',
        'volver' => 'Volver al inicio',
        'otro_documento' => 'También puedes leer',
        'pie_aria_label' => 'Otros documentos legales',
    ],

    // Enlaces del pie del login y de las páginas legales.
    'enlaces' => [
        'terminos' => 'Términos',
        'privacidad' => 'Privacidad',
        'aria_label' => 'Información legal',
    ],

    'terminos' => [
        'titulo_pestana' => 'Términos y condiciones',
        'titulo' => 'Términos y condiciones del servicio',
        'enlace_otro' => 'Política de privacidad',
        'introduccion' => [
            'Estos términos explican cómo funciona Agrocom y qué se espera de quienes la usan, sean clientes o proveedores. Para usar las apps y el sitio de Agrocom debes aceptarlos.',
            'Cómo y en qué momento se registra tu aceptación: [POR DEFINIR].',
        ],
        'secciones' => [
            [
                'titulo' => 'Qué es Agrocom',
                'parrafos' => [
                    'Agrocom es un marketplace de servicios de fumigación con drones. Un cliente publica una solicitud sobre su terreno con un precio por hectárea; los proveedores la ven, aceptan ese precio o proponen el suyo, y el cliente elige.',
                    'Agrocom SRL es el nexo entre las partes y el registro del estado y del resultado del servicio. Algunos proveedores son pilotos propios de Agrocom: pasan por la misma verificación que los demás, y el cliente decide libremente con quién trabaja.',
                    'Cómo se trabaja en el lote, cuándo se vuela y con qué condiciones lo acuerdan en persona el cliente y el piloto.',
                ],
            ],
            [
                'titulo' => 'Cuentas y verificación de identidad',
                'parrafos' => [
                    'Agrocom tiene dos apps, y cada una admite un solo tipo de cuenta: la app del cliente, para quien tiene el terreno, y la app del proveedor, para quien presta el servicio con un dron (pilotos independientes, dueños de drones y pilotos propios de Agrocom). Cada cuenta tiene su propio usuario, su propia contraseña, su propia verificación y su propia aprobación. Una cuenta no se convierte en la otra: quien es cliente y quiere ser proveedor se registra de nuevo en la app del proveedor.',
                    'Una misma persona puede tener, como máximo, una cuenta de cliente y una de proveedor.',
                ],
                'items' => [
                    'Puedes registrarte y recorrer la app sin que te hayan aprobado. Para operar en el mercado, es decir, publicar solicitudes y aceptar ofertas como cliente, u ofertar como proveedor, tu cuenta debe estar aprobada por Agrocom.',
                    'Para aprobarte, Agrocom verifica tu identidad con una selfie tomada con la cámara de la app en ese momento y una foto de tu documento (cédula de identidad, licencia de conducir o pasaporte). Cada cuenta sube su propia selfie y su propio documento.',
                    'Si eres proveedor, además se revisan tu licencia o habilitación de piloto, los registros o matrículas del dron, los seguros vigentes y las fotos del equipo. Mientras no estés aprobado, la app no te muestra el mapa de solicitudes ni te deja ofertar. Si eres piloto, el registro pide tu fecha de nacimiento porque quien vuela debe ser mayor de edad.',
                    'Los datos y documentos que cargas deben ser verdaderos y estar vigentes. Una falsedad documental puede llevar a la suspensión o a la baja de la cuenta.',
                    'Una cuenta no puede ofertar sobre una solicitud publicada por ella misma ni por otra cuenta de la misma persona, y tampoco puede calificarse a sí misma.',
                    'Edad mínima para usar la app del cliente: [POR DEFINIR].',
                ],
            ],
            [
                'titulo' => 'Cómo se negocia el precio',
                'parrafos' => [
                    'El precio por hectárea lo acuerdan el cliente y el piloto. No hay un precio de referencia: lo ponen ellos. El sistema solo rechaza un precio por encima de un máximo, para evitar errores de digitación.',
                ],
                'items' => [
                    'El cliente publica la solicitud con su precio. El piloto lo acepta o propone el suyo, indicando el dron con el que trabajará. Después el cliente y el piloto pueden aceptar, contraofertar o rechazar, por rondas, con una sola oferta vigente por negociación.',
                    'Una negociación admite hasta 5 ofertas; la última es definitiva y solo se acepta o se rechaza. Cada oferta vence a las 24 horas. Estos valores son parámetros de Agrocom y pueden cambiar.',
                    'Cuando una parte acepta la oferta vigente de la otra, la solicitud queda adjudicada con ese precio. El precio acordado no cambia después, salvo por la resolución de un reclamo.',
                    'Nadie queda obligado por una oferta que no aceptó. Rechazar, retirar o dejar vencer una oferta no tiene consecuencia por sí solo.',
                    'Publicar, ofertar, contraofertar, aceptar, rechazar y retirar una oferta requieren conexión a internet, porque el acuerdo entre las dos partes lo confirma el servidor.',
                ],
            ],
            [
                'titulo' => 'Precio y pago',
                'parrafos' => [
                    'Agrocom no procesa cobros ni cobra comisión dentro de la app. El cliente le paga al proveedor fuera de la app, por transferencia o en efectivo; la app solo muestra el monto a pagar.',
                ],
                'items' => [
                    'Al adjudicar, la app muestra un monto estimado: las hectáreas solicitadas por el precio acordado.',
                    'Al finalizar el servicio, el monto final es las hectáreas ejecutadas por el precio acordado, redondeado a 2 decimales. Las hectáreas ejecutadas no pueden superar las solicitadas más una tolerancia que fija Agrocom (hoy, 5 %). Cliente y proveedor ven el mismo monto.',
                    'El proveedor puede registrar que recibió el pago. Es un aviso informativo y no cambia el estado del servicio.',
                    'Los reembolsos que resulten de un reclamo se liquidan entre las partes, fuera de la app.',
                    'Cómo se remunera Agrocom por su servicio de intermediación: [POR DEFINIR].',
                ],
            ],
            [
                'titulo' => 'Durante el servicio',
                'items' => [
                    'Después de adjudicar, el piloto marca el inicio, sube fotos de su avance, marca el progreso y cierra el servicio con las hectáreas ejecutadas. El cierre exige las fotos mínimas de avance.',
                    'El cliente ve las fotos, el avance, la última ubicación de la app del piloto mientras el servicio está en ejecución y los mensajes con el piloto adjudicado.',
                    'Las condiciones de vuelo, la mezcla y los resguardos los acuerdan y los asumen el piloto y el cliente en el lote; Agrocom no los controla. El cliente declara los colindantes sensibles de su terreno (apiarios, cultivos orgánicos, viviendas, fuentes de agua) y el piloto los ve antes de ofertar.',
                    'Si hay personas o animales en el lote, o se ve deriva del producto, el piloto detiene la aplicación y reporta una incidencia. Cualquier participante puede pedir ayuda con el botón de auxilio de la app, que avisa a soporte con la ubicación. Si hay peligro para la vida, llama primero a los servicios de emergencia.',
                ],
            ],
            [
                'titulo' => 'Cancelaciones',
                'parrafos' => [
                    'Cancelar debe ser fácil. Agrocom no impone penalidades en dinero: registra quién cancela y deja que la reputación y la moderación actúen.',
                ],
                'items' => [
                    'Antes de adjudicar, el cliente cancela sin ningún efecto.',
                    'Después de adjudicar y antes de iniciar, el cliente o el proveedor cancelan indicando un motivo. La cancelación queda registrada y el otro recibe el aviso. Si cancela el proveedor, la solicitud vuelve a estar abierta y ese proveedor no puede ofertar de nuevo en ella.',
                    'Una vez en ejecución, el servicio no se cancela: se cierra con las hectáreas realmente ejecutadas y, si hay desacuerdo, se abre un reclamo.',
                    'No se registra la cancelación cuando hay una causa de fuerza mayor declarada con motivo y reconocida por soporte (clima fuera de lo operable, emergencia o incidencia de seguridad, falla comprobada del equipo, error en los datos de la solicitud), ni cuando la solicitud expira sin ofertas.',
                ],
            ],
            [
                'titulo' => 'Calificaciones',
                'items' => [
                    'El cliente califica al proveedor y al piloto durante los 7 días siguientes a que el servicio finaliza, con estrellas de 1 a 5, etiquetas de un catálogo cerrado y un comentario opcional.',
                    'El proveedor califica al cliente en privado: esa calificación no se publica y sirve para la moderación y el soporte. El proveedor ve las calificaciones que recibe.',
                    'Una calificación no se edita. Un administrador puede ocultarla, con motivo y autor, si tiene insultos o datos personales, si responde a factores ajenos al proveedor, si es una represalia por un reclamo o si viene de una cuenta sin servicio real. Ocultar no borra el registro: la calificación deja de contar para el promedio.',
                    'Ninguna calificación restringe una cuenta de forma automática.',
                ],
            ],
            [
                'titulo' => 'Conducta esperada',
                'items' => [
                    'Los mensajes y las ofertas respetan a la contraparte. Se prohíben los insultos, las amenazas y los datos de terceros.',
                    'Las fotos de avance muestran lo trabajado: no deben mostrar personas identificables ni viviendas de terceros más de lo necesario. Un administrador puede ocultar una foto indebida o un precio claramente fuera de lo razonable.',
                    'Se prohíbe calificar o presionar a alguien por haber abierto un reclamo.',
                    'Pactar por fuera de la plataforma lo ya adjudicado no está prohibido, pero la calificación y el reclamo solo cubren lo que quedó registrado en ella.',
                ],
            ],
            [
                'titulo' => 'Reclamos',
                'parrafos' => [
                    'Si el servicio no salió como se acordó, puedes abrir un reclamo. Hay uno por solicitud.',
                ],
                'items' => [
                    'El cliente puede abrirlo desde que el servicio finaliza y hasta 72 horas después. El proveedor puede abrirlo por cobro pendiente, por acceso negado al lote o por una calificación abusiva.',
                    'Al abrirlo indicas el motivo y el monto reclamado, y agregas tus pruebas. Agrocom conserva las fotos de avance con su fecha, los registros de avance y cierre, y los mensajes. La ubicación no se conserva.',
                    'Soporte pide información a la otra parte, que responde en 48 horas. Un administrador resuelve a favor del cliente, a favor del proveedor, de forma parcial o sin lugar, y deja la decisión por escrito, con su autor y su motivo.',
                    'Cualquiera de las partes puede apelar una vez, dentro de los 7 días siguientes, con pruebas nuevas.',
                    'Si la resolución fija un monto ajustado, ese pasa a ser el precio final. Si hay un daño probado, se deriva al seguro del proveedor y Agrocom facilita el expediente.',
                    'Soporte está disponible desde la app y la web. La primera respuesta a reclamos e incidencias no urgentes llega en 24 horas hábiles (parámetro de Agrocom). Los mensajes de una conversación solo los lee soporte si hay un reclamo abierto.',
                ],
            ],
            [
                'titulo' => 'Suspensión y baja de cuentas',
                'items' => [
                    'Un administrador de Agrocom puede suspender una cuenta de forma inmediata por un incidente grave probado, por falsedad documental o por un reclamo de la autoridad. Un documento vencido suspende de forma automática hasta que se cargue uno vigente.',
                    'La baja definitiva procede por fraude, daño intencional o reincidencia en suspensión.',
                    'Toda suspensión o baja trae el motivo, el autor y la vía para apelar. Antes de restringir ofertas por calificaciones bajas se avisa por escrito, con las calificaciones que pesan; puedes apelar en 7 días con información nueva y revisa un administrador distinto del que decidió.',
                    'Una calificación baja o una tasa de cancelación alta generan una alerta para que un administrador revise; nunca una restricción automática. A un cliente con calificaciones bajas de parte de los proveedores se le avisa y, si persiste, se limita su publicación hasta una revisión.',
                    'Un cambio de estado de la cuenta no interrumpe por sí solo lo ya adjudicado: lo que pasa con un servicio en curso lo decide un administrador caso por caso.',
                ],
            ],
            [
                'titulo' => 'Responsabilidad entre las partes',
                'items' => [
                    'Lo que se hace en el lote lo acuerdan y lo asumen el cliente y el piloto. El pago se acuerda y se hace entre ellos, fuera de la app.',
                    'El proveedor responde por los documentos y los datos que carga. Un daño probado se deriva al seguro del proveedor y Agrocom facilita el expediente.',
                    'Alcance y límites de la responsabilidad de Agrocom frente a clientes y proveedores: [POR DEFINIR].',
                    'Ley aplicable y forma de resolver las diferencias que no se resuelvan por un reclamo: [POR DEFINIR].',
                ],
            ],
            [
                'titulo' => 'Cambios en estos términos y contacto',
                'parrafos' => [
                    'Cada versión de este documento tiene un número y una fecha de vigencia, que aparecen al inicio de la página.',
                    'Cómo se avisa un cambio y cómo se registra la nueva aceptación: [POR DEFINIR].',
                    'Contacto para consultas sobre estos términos (correo, teléfono o formulario): [POR DEFINIR].',
                ],
            ],
        ],
    ],

    'privacidad' => [
        'titulo_pestana' => 'Política de privacidad',
        'titulo' => 'Política de privacidad',
        'enlace_otro' => 'Términos y condiciones',
        'introduccion' => [
            'Esta política explica qué datos recoge Agrocom, para qué los usa, quién los ve y por cuánto tiempo los conserva. Agrocom solo pide lo que necesita para que el servicio funcione.',
            'Responsable de los datos: Agrocom SRL. Domicilio y datos de registro de la empresa: [POR DEFINIR].',
        ],
        'secciones' => [
            [
                'titulo' => 'Qué datos recogemos',
                'items' => [
                    'Tu cuenta y tu persona: nombres, apellidos, tipo y número de documento (cédula de identidad, licencia de conducir o pasaporte), celular, usuario y correo electrónico, que sirve de respaldo para recuperar tu acceso. Si eres proveedor y piloto, también tu fecha de nacimiento.',
                    'Tu verificación: la selfie que tomas con la cámara de la app y la foto de tu documento. Es distinta de tu foto de perfil, que sí ven las otras partes.',
                    'Si eres una empresa (opcional): razón social, documento fiscal, nombre comercial, logo y personas de contacto de referencia, con su nombre, teléfono y cargo.',
                    'Si eres proveedor: licencia o habilitación de piloto, registros o matrículas, seguros, certificados, y los datos y las fotos de tu dron. Opcionalmente, el nombre y el celular de las personas que forman tu equipo.',
                    'Si eres cliente: tus terrenos con los lotes dibujados, las hectáreas, el cultivo, el departamento, la provincia, el municipio y la comunidad, los accesos, las observaciones, los colindantes sensibles, las fotos del terreno y la referencia de tu dirección.',
                    'Tu actividad en el mercado: solicitudes, ofertas y su historial, calificaciones y reclamos con sus pruebas, incidencias con sus fotos, y tus preferencias de aviso y filtros.',
                    'Durante el servicio: las fotos de avance (comprimidas a menos de 300 KB), el avance, las hectáreas ejecutadas y los mensajes de texto entre cliente y proveedor adjudicado.',
                    'La ubicación del piloto, solo mientras el servicio está en ejecución (ver más abajo), y la ubicación que se adjunta a una incidencia o a un aviso de auxilio.',
                    'Datos técnicos: un identificador de tu dispositivo para mantener tu sesión, y un registro de las acciones relevantes sobre cuentas y servicios (quién, cuándo y qué).',
                ],
            ],
            [
                'titulo' => 'Para qué los usamos',
                'items' => [
                    'Crear tu cuenta, verificar que eres quien dices ser y evitar cuentas repetidas o fraudes. Por eso una misma persona tiene, como máximo, una cuenta de cliente y una de proveedor.',
                    'Mostrar solicitudes a los pilotos habilitados, avisarles de las nuevas según sus filtros y preferencias, y permitir la negociación entre las partes.',
                    'Registrar el estado y el resultado de cada servicio: avance, fotos, mensajes, monto final, calificaciones.',
                    'Atender reclamos e incidencias, moderar calificaciones y fotos, y decidir sobre suspensiones y bajas.',
                    'Avisarte de los vencimientos de tus documentos de proveedor, 30 y 7 días antes.',
                    'Darte soporte desde la app y la web.',
                ],
            ],
            [
                'titulo' => 'Quién ve tus datos y cuándo',
                'parrafos' => [
                    'Antes de ofertar, el piloto ve los lotes dibujados, el departamento, la provincia, el municipio y la comunidad, las hectáreas, el cultivo, los accesos, las observaciones y las fotos del terreno, para decidir su precio. No ve quién es el cliente.',
                ],
                'items' => [
                    'Los datos de contacto del cliente (nombre, teléfono, documento y dirección; en una empresa, la persona de contacto y la razón social) se entregan solo al proveedor adjudicado, y solo después de adjudicar. Un proveedor nunca ve datos de clientes que no le fueron adjudicados.',
                    'El teléfono y el documento del piloto los ve el cliente solo después de adjudicar. Antes, en cada oferta el cliente ve el nombre y la foto del piloto, la calificación, los servicios y la tasa de cancelación del proveedor, los datos y las fotos aprobadas del dron, y que el proveedor está verificado. Después de adjudicar ve también a las personas del equipo.',
                    'Un proveedor nunca ve las ofertas de otros proveedores. Los demás pilotos no ven las fotos, el avance, los mensajes ni la ubicación de un servicio que no es suyo.',
                    'Los mensajes los ven solo el cliente y el proveedor adjudicado. Soporte puede leer una conversación únicamente si hay un reclamo abierto.',
                    'Tu selfie y tus documentos los ven solo los administradores y el soporte de Agrocom que revisan tu cuenta. Las otras partes ven únicamente la insignia «Verificado», sin los documentos.',
                    'La calificación que el cliente escribe la ve el proveedor; la que el proveedor escribe sobre el cliente es privada y solo la usan la moderación y el soporte.',
                    'Qué otras empresas procesan datos por cuenta de Agrocom (alojamiento de los datos, avisos, mapas) y si algún dato sale del país: [POR DEFINIR].',
                ],
            ],
            [
                'titulo' => 'Tu ubicación',
                'items' => [
                    'La app te pide permiso de ubicación solo cuando una función la necesita, y antes te explica para qué.',
                    'La app del piloto comparte su última posición con el cliente únicamente mientras el servicio está en ejecución y la app está abierta. El piloto puede desactivarla; entonces el cliente ve «sin ubicación reciente».',
                    'El servidor guarda solo la última posición, no la ruta, y la elimina cuando el servicio finaliza o se cancela. El cliente no ve ruta, velocidad ni tiempo estimado.',
                    'La ubicación nunca se escribe en historiales, registros técnicos, reportes de error ni analíticas. El mapa de solicitudes no recibe la posición del piloto.',
                ],
            ],
            [
                'titulo' => 'Cuánto tiempo conservamos tus datos',
                'items' => [
                    'La ubicación del piloto no se conserva: se elimina al finalizar o cancelar el servicio.',
                    'La selfie y el documento de verificación se conservan :meses meses después de dar de baja la cuenta y luego se eliminan.',
                    'Los mensajes y las fotos de un servicio se conservan mientras sea posible un reclamo y luego según la política de retención de Agrocom. Plazo exacto: [POR DEFINIR].',
                    'Las cuentas y los registros no se borran de inmediato: se dan de baja. Plazo y condiciones de la eliminación definitiva del resto de los datos: [POR DEFINIR].',
                    'Una calificación no se edita, y ocultarla no la borra. Un registro ya confirmado por el servidor no se reescribe: una corrección es un registro nuevo, con su motivo y su autor.',
                ],
            ],
            [
                'titulo' => 'Cómo protegemos tus datos',
                'items' => [
                    'Los documentos del proveedor y la verificación se guardan con acceso restringido. Se sirven con enlaces temporales solo a quien tiene permiso, y no entran a los registros técnicos ni a la bitácora. La app borra su copia local de tu selfie y de tu documento cuando los sube.',
                    'La sesión de tu dispositivo se guarda en el almacenamiento seguro del teléfono. Al cerrar sesión se borra y se revoca el dispositivo en el servidor.',
                    'Aviso a las personas afectadas ante un incidente de seguridad con sus datos: [POR DEFINIR].',
                ],
            ],
            [
                'titulo' => 'Tus derechos',
                'parrafos' => [
                    'Puedes pedir a Agrocom acceso a los datos que tiene sobre ti, la corrección de los que estén equivocados y la eliminación de tu cuenta y de tus datos.',
                    'Algunos datos se conservan por el plazo indicado más arriba, por ejemplo mientras sea posible un reclamo. Una corrección sobre un registro ya confirmado se hace con un registro nuevo.',
                    'Si alguien se registró con tu documento sin que seas tú, escríbenos: un administrador compara la selfie con el documento y elimina la cuenta falsa, con su motivo.',
                ],
                'items' => [
                    'Cómo pedir el ejercicio de estos derechos (canal y datos para identificarte): [POR DEFINIR].',
                    'Plazo en que Agrocom responde: [POR DEFINIR].',
                    'Uso de la app por menores de edad: [POR DEFINIR].',
                ],
            ],
            [
                'titulo' => 'Cambios en esta política y contacto',
                'parrafos' => [
                    'Cada versión de este documento tiene un número y una fecha de vigencia, que aparecen al inicio de la página.',
                    'Cómo se avisa un cambio de esta política: [POR DEFINIR].',
                    'Contacto para consultas sobre tus datos (correo, teléfono o formulario): [POR DEFINIR].',
                ],
            ],
        ],
    ],

];
