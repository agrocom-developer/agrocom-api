<?php

/*
 * Texto de las páginas públicas «Términos y condiciones del servicio» y
 * «Política de privacidad» (`docs/politicas/legal.md`; ADR 0013: ningún texto
 * visible va en duro en una vista).
 *
 * Cada afirmación sale de una política o ADR del repo o de una decisión del
 * dueño (la tabla de trazabilidad está en `docs/politicas/legal.md`). El texto
 * se actualiza versión a versión; la versión y la vigencia viven en
 * `config/legal.php`.
 *
 * Estructura de cada documento: `titulo`, `introduccion` (lista de párrafos) y
 * `secciones`, cada una con `titulo`, `parrafos`, `items` y `condicionales`
 * (todos opcionales). Un condicional es `['si' => clave, 'texto' => ...]`: se
 * muestra solo si el dato de esa clave está configurado (`jurisdiccion`,
 * `redes`, `correo`, `telefono`, `whatsapp`) y reemplaza `:valor` por él.
 *
 * Marcadores que la vista reemplaza con `config/legal.php`: `:producto`,
 * `:titular`, `:sitio`, `:sitio_empresa`, `:direccion`, `:meses`,
 * `:dias_sin_antecedentes`, `:gracia`, `:plazo`, `:respaldos`,
 * `:servicio_meses` y `:emergencia_dias`. La vista agrega al final de cada
 * documento la sección «Contacto y datos del titular» con lo que esté
 * configurado.
 */

return [

    'comun' => [
        'version' => 'Versión :version',
        'vigente_desde' => 'Vigente desde el :fecha',
        'indice' => 'Contenido',
        'indice_aria_label' => 'Secciones de este documento',
        'volver' => 'Volver al inicio',
        'otro_documento' => 'También puedes leer',
        'pie_aria_label' => 'Otros documentos legales',

        // Sección final de contacto, armada con `config/legal.php`.
        'contacto' => [
            'titulo' => 'Contacto y datos del titular',
            'introduccion' => 'Para consultas sobre este documento, para ejercer tus derechos o para pedir soporte, puedes comunicarte con el titular por estos medios.',
            'titular' => 'Titular',
            'razon_social' => 'Razón social',
            'nit' => 'NIT',
            'representante' => 'Representante legal',
            'direccion' => 'Dirección',
            'correo' => 'Correo electrónico',
            'telefono' => 'Teléfono',
            'whatsapp' => 'WhatsApp',
            'sitio' => 'Sitio del servicio',
            'sitio_empresa' => 'Sitio de la empresa',
            'redes' => 'Redes sociales oficiales',
        ],

        'redes' => [
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'tiktok' => 'TikTok',
            'youtube' => 'YouTube',
        ],
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
            ':producto es un servicio de :titular que conecta a quienes necesitan una fumigación con drones (clientes) con quienes prestan ese servicio (proveedores: pilotos propios, pilotos independientes y dueños de drones). Estos términos explican cómo funciona, qué puedes esperar de :titular y qué se espera de ti.',
            'Al crear una cuenta o usar las apps y el sitio de :producto aceptas estos términos y la política de privacidad. Si no estás de acuerdo, no uses el servicio.',
        ],
        'secciones' => [
            [
                'titulo' => 'Qué es :producto y qué no es',
                'parrafos' => [
                    ':producto es una plataforma de intermediación: un cliente publica una solicitud sobre su terreno con un precio por hectárea; los proveedores la ven, aceptan ese precio o proponen el suyo con su dron, y el cliente elige. :titular es el nexo entre las partes y el registro del estado y del resultado del servicio.',
                    ':titular no fumiga ni presta el servicio operativo en el lote: lo prestan los proveedores, de forma independiente y bajo su propia responsabilidad. Algunos proveedores tienen un vínculo propio con :titular (pilotos propios); en la plataforma pasan por la misma verificación y se rigen por las mismas reglas que los demás, no tienen prioridad y el cliente decide libremente con quién trabaja.',
                    'Cómo se trabaja en el lote, cuándo se vuela y con qué condiciones lo acuerdan en persona el cliente y el proveedor. El servicio de fumigación es un acuerdo entre ellos; :titular no es parte de ese acuerdo.',
                ],
            ],
            [
                'titulo' => 'Aceptación y edad mínima',
                'items' => [
                    'Para usar :producto debes tener 18 años cumplidos o más. Al registrarte declaras que cumples esa edad.',
                    'Al registrarte aceptas la versión vigente de estos términos y de la política de privacidad. :titular guarda qué versión aceptaste, con su fecha, como prueba de tu aceptación.',
                    'Si estos documentos cambian de fondo, la app te pide aceptar la nueva versión antes de seguir operando en el mercado.',
                ],
            ],
            [
                'titulo' => 'Cuentas y verificación de identidad',
                'parrafos' => [
                    ':producto tiene dos apps, y cada una admite un solo tipo de cuenta: la app del cliente, para quien tiene el terreno, y la app del proveedor, para quien presta el servicio con un dron (pilotos independientes, dueños de drones y pilotos propios). Cada cuenta tiene su propio usuario, su propia contraseña, su propia verificación y su propia aprobación. Una cuenta no se convierte en la otra: quien es cliente y quiere ser proveedor se registra de nuevo en la app del proveedor.',
                    'Una misma persona puede tener, como máximo, una cuenta de cliente y una de proveedor. Eres responsable de guardar tu contraseña y de lo que se haga con tu cuenta.',
                ],
                'items' => [
                    'Puedes registrarte y recorrer la app sin que te hayan aprobado. Para operar en el mercado, es decir, publicar solicitudes y aceptar ofertas como cliente, u ofertar como proveedor, tu cuenta debe estar aprobada por :titular.',
                    'Para aprobarte, :titular verifica tu identidad con una selfie tomada con la cámara de la app en ese momento y una foto de tu documento (cédula de identidad, licencia de conducir o pasaporte). Cada cuenta sube su propia selfie y su propio documento. Una persona del equipo de :titular compara la selfie con el documento; el sistema no hace reconocimiento facial automático.',
                    'Si eres proveedor, además se revisan tu licencia o habilitación de piloto, los registros o matrículas del dron, los seguros vigentes y las fotos del equipo. Mientras no estés aprobado, la app no te muestra el mapa de solicitudes ni te deja ofertar. Si eres piloto, el registro pide tu fecha de nacimiento porque quien vuela debe ser mayor de edad.',
                    'Los datos y documentos que cargas deben ser verdaderos y estar vigentes. Una falsedad documental puede llevar a la suspensión o a la baja de la cuenta.',
                    'Una cuenta no puede ofertar sobre una solicitud publicada por ella misma ni por otra cuenta de la misma persona, y tampoco puede calificarse a sí misma.',
                ],
            ],
            [
                'titulo' => 'Cómo se negocia el precio',
                'parrafos' => [
                    'El precio por hectárea lo acuerdan el cliente y el proveedor. No hay un precio de referencia: lo ponen ellos. El sistema solo rechaza un precio por encima de un máximo, para evitar errores de digitación.',
                ],
                'items' => [
                    'El cliente publica la solicitud con su precio. El proveedor lo acepta o propone el suyo, indicando el dron con el que trabajará. Después el cliente y el proveedor pueden aceptar, contraofertar o rechazar, por rondas, con una sola oferta vigente por negociación.',
                    'Una negociación admite hasta 5 ofertas; la última es definitiva y solo se acepta o se rechaza. Cada oferta vence a las 24 horas. Estos valores son parámetros de :titular y pueden cambiar.',
                    'Cuando una parte acepta la oferta vigente de la otra, la solicitud queda adjudicada con ese precio. El precio acordado no cambia después, salvo por la resolución de un reclamo.',
                    'Nadie queda obligado por una oferta que no aceptó. Rechazar, retirar o dejar vencer una oferta no tiene consecuencia por sí solo.',
                    'Publicar, ofertar, contraofertar, aceptar, rechazar y retirar una oferta requieren conexión a internet, porque el acuerdo entre las dos partes lo confirma el servidor. Si no hay conexión, la acción no se realiza y la app te lo avisa.',
                ],
            ],
            [
                'titulo' => 'Precio, pago y costos de uso',
                'parrafos' => [
                    ':producto no procesa cobros ni cobra comisión por los trabajos. El cliente le paga al proveedor fuera de la app, por transferencia o en efectivo; la app solo muestra el monto a pagar. Dentro de la app no se hacen compras ni pagos.',
                    'Por ahora, usar :producto no tiene costo para clientes ni para proveedores. Si más adelante :titular define costos por usar la plataforma, los precios se informarán con anticipación por los medios que indica la sección «Cambios en estos términos», antes de que empiecen a aplicarse, y no se te cobrará nada que no se te haya avisado antes.',
                ],
                'items' => [
                    'Al adjudicar, la app muestra un monto estimado: las hectáreas solicitadas por el precio acordado.',
                    'Al finalizar el servicio, el monto final es las hectáreas ejecutadas por el precio acordado, redondeado a 2 decimales. Las hectáreas ejecutadas no pueden superar las solicitadas más una tolerancia que fija :titular (hoy, 5 %). Cliente y proveedor ven el mismo monto.',
                    'El proveedor puede registrar que recibió el pago. Es un aviso informativo y no cambia el estado del servicio.',
                    'Los reembolsos que resulten de un reclamo se liquidan entre las partes, fuera de la app. Los impuestos y la facturación del servicio de fumigación corresponden a quien lo presta.',
                ],
            ],
            [
                'titulo' => 'Durante el servicio',
                'items' => [
                    'Después de adjudicar, el piloto marca el inicio, sube fotos de su avance, marca el progreso y cierra el servicio con las hectáreas ejecutadas. El cierre exige las fotos mínimas de avance.',
                    'El cliente ve las fotos, el avance, la última ubicación de la app del piloto mientras el servicio está en ejecución (si el piloto la tiene activada) y los mensajes con el piloto adjudicado.',
                    'Las condiciones de vuelo, la mezcla y los resguardos los acuerdan y los asumen el piloto y el cliente en el lote; :titular no los controla. El cliente declara los colindantes sensibles de su terreno (apiarios, cultivos orgánicos, viviendas, fuentes de agua) y el piloto los ve antes de ofertar.',
                    'Si hay personas o animales en el lote, o se ve deriva del producto, el piloto detiene la aplicación y reporta una incidencia. Cualquier participante puede pedir ayuda con el botón de auxilio de la app, que avisa a soporte con la ubicación. El botón de auxilio no reemplaza a los servicios de emergencia: si hay peligro para la vida, llama primero a ellos.',
                ],
            ],
            [
                'titulo' => 'Cancelaciones',
                'parrafos' => [
                    'Cancelar debe ser fácil. :titular no impone penalidades en dinero: registra quién cancela y deja que la reputación y la moderación actúen.',
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
                    'Una calificación no se edita. Un administrador puede ocultarla, con motivo y autor, si tiene insultos o datos personales, si responde a factores ajenos al proveedor, si es una represalia por un reclamo o si viene de una cuenta sin servicio real. Ocultar no borra el registro: la calificación deja de contar para el promedio. Cualquier persona puede reportar una calificación.',
                    'Ninguna calificación restringe una cuenta de forma automática.',
                ],
            ],
            [
                'titulo' => 'Contenido de usuarios, conducta, reportes y bloqueos',
                'parrafos' => [
                    'Los mensajes, las ofertas, las calificaciones y las fotos que publicas son contenido tuyo y eres responsable de él. Nos autorizas a guardarlo, mostrarlo a la otra parte del servicio y usarlo para operar la plataforma, atender reclamos y moderar; no lo usamos para otro fin ni lo publicamos de forma abierta.',
                ],
                'items' => [
                    'Se prohíben los insultos, el acoso, las amenazas, el contenido sexual o violento, los datos de terceros, la suplantación de identidad y los pagos o acuerdos por fuera para estafar.',
                    'Las fotos de avance muestran lo trabajado: no deben mostrar personas identificables ni viviendas de terceros más de lo necesario.',
                    'Puedes reportar un mensaje, una conversación o una foto de avance desde la app, indicando un motivo. El reporte llega a soporte como un reclamo de conducta y, al reportar, aceptas que soporte lea solo lo reportado y su contexto cercano. A quien reportaste no se le dice quién lo hizo.',
                    'Puedes bloquear a la otra parte. El chat de esa solicitud se cierra para las dos y, hacia adelante, dejan de verse las solicitudes y los avisos del otro. Un servicio ya adjudicado o en ejecución sigue: se cierra, se cancela o se abre un reclamo por la vía normal.',
                    'Soporte responde los reportes en 24 horas hábiles y puede resolver sin lugar, con una advertencia, ocultando el contenido o suspendiendo la cuenta, siempre con motivo y autor, y con apelación. Un filtro básico rechaza palabras y direcciones web que un administrador configura. Ninguna cuenta se restringe sola por un reporte o un bloqueo.',
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
                    'Al abrirlo indicas el motivo y el monto reclamado, y agregas tus pruebas. :titular conserva las fotos de avance con su fecha, los registros de avance y cierre, y los mensajes. La ubicación del piloto no se conserva.',
                    'Soporte pide información a la otra parte, que responde en 48 horas. Un administrador resuelve a favor del cliente, a favor del proveedor, de forma parcial o sin lugar, y deja la decisión por escrito, con su autor y su motivo.',
                    'Cualquiera de las partes puede apelar una vez, dentro de los 7 días siguientes, con pruebas nuevas.',
                    'Si la resolución fija un monto ajustado, ese pasa a ser el precio final. Si hay un daño probado, se deriva al seguro del proveedor y :titular facilita el expediente.',
                    'Soporte está disponible desde la app y la web. La primera respuesta a reclamos e incidencias no urgentes llega en 24 horas hábiles (parámetro de :titular). Los mensajes de una conversación solo los lee soporte si hay un reclamo abierto o un reporte sobre ellos.',
                ],
            ],
            [
                'titulo' => 'Suspensión, baja y eliminación de cuentas',
                'items' => [
                    'Un administrador de :titular puede suspender una cuenta de forma inmediata por un incidente grave probado, por falsedad documental o por un reclamo de la autoridad. Un documento vencido suspende de forma automática hasta que se cargue uno vigente.',
                    'La baja definitiva procede por fraude, daño intencional o reincidencia en suspensión.',
                    'Toda suspensión o baja trae el motivo, el autor y la vía para apelar. Antes de restringir ofertas por calificaciones bajas se avisa por escrito, con las calificaciones que pesan; puedes apelar en 7 días con información nueva y revisa un administrador distinto del que decidió.',
                    'Una calificación baja o una tasa de cancelación alta generan una alerta para que un administrador revise; nunca una restricción automática. A un cliente con calificaciones bajas de parte de los proveedores se le avisa y, si persiste, se limita su publicación hasta una revisión.',
                    'Un cambio de estado de la cuenta no interrumpe por sí solo lo ya adjudicado: lo que pasa con un servicio en curso lo decide un administrador caso por caso.',
                    'Puedes eliminar tu cuenta cuando quieras, desde la app (Perfil, Privacidad y datos) o escribiendo a los canales de contacto. Qué se elimina y qué se conserva está en la política de privacidad. No puedes eliminarla mientras tengas un servicio adjudicado o en ejecución, un reclamo abierto o una incidencia grave abierta; la app te explica el motivo y la salida.',
                ],
            ],
            [
                'titulo' => 'Responsabilidades de cada parte',
                'parrafos' => [
                    'Estas reglas buscan que cada quien responda por lo que de verdad controla.',
                ],
                'items' => [
                    'El proveedor responde por el servicio que presta: su licencia o habilitación de piloto, los registros del dron, los seguros, los permisos que la ley exija para volar y aplicar productos, el producto que usa, la forma en que opera y los daños que cause. Un daño probado se deriva a su seguro y :titular facilita el expediente.',
                    'El cliente responde por la veracidad de los datos de su terreno (lotes, hectáreas, cultivo, accesos y colindantes sensibles), por tener derecho a que se trabaje en él y por pagar al proveedor el monto acordado.',
                    'Lo que se hace en el lote lo acuerdan y lo asumen el cliente y el proveedor. El pago se acuerda y se hace entre ellos, fuera de la app.',
                    ':titular responde por mantener la plataforma, por verificar a las cuentas con los medios descritos en estos términos, por registrar con fidelidad lo que las partes hacen en ella y por proteger tus datos como dice la política de privacidad.',
                    ':titular no garantiza que un cliente reciba ofertas ni que un proveedor reciba trabajos, la calidad ni el resultado agronómico del servicio, ni que la plataforma funcione sin interrupciones o errores. La verificación confirma identidad y documentos, no la capacidad técnica ni la conducta futura de nadie.',
                    'En la medida que la ley lo permita, :titular no responde por daños indirectos ni por lo que cliente y proveedor acuerden o hagan fuera de la plataforma. Nada de lo anterior limita los derechos que la ley te reconoce y que no se pueden renunciar.',
                ],
            ],
            [
                'titulo' => 'Datos personales',
                'parrafos' => [
                    'Cómo se recogen, usan, comparten, conservan y eliminan tus datos está en la política de privacidad, que forma parte de estos términos.',
                ],
            ],
            [
                'titulo' => 'Cambios en estos términos',
                'parrafos' => [
                    'Cada versión de este documento tiene un número y una fecha de vigencia, que aparecen al inicio de la página. Los términos pueden cambiar; cuando cambian, lo avisamos por estos medios:',
                ],
                'items' => [
                    'Dentro de la app: un aviso y, si el cambio es de fondo, la pantalla para aceptar la nueva versión.',
                    'En esta misma página, con la versión y la fecha desde la que rige.',
                ],
                'condicionales' => [
                    ['si' => 'redes', 'texto' => 'En nuestras redes sociales oficiales: :valor.'],
                ],
                'parrafos_finales' => [
                    'Un cambio rige desde la fecha indicada. Si no estás de acuerdo con una nueva versión, puedes dejar de usar el servicio y eliminar tu cuenta. Lo ya adjudicado antes del cambio se rige por la versión que aceptaste, salvo que la nueva sea más favorable para ti.',
                ],
            ],
            [
                'titulo' => 'Ley aplicable y diferencias',
                'parrafos' => [
                    'Estos términos se rigen por las leyes del Estado Plurinacional de Bolivia. Las diferencias entre tú y :titular se procuran resolver primero por diálogo, escribiendo a los canales de contacto, y, si no se resuelven, por la vía legal que corresponda.',
                ],
                'condicionales' => [
                    ['si' => 'jurisdiccion', 'texto' => 'Para lo que no se resuelva por esas vías, son competentes los tribunales de :valor.'],
                ],
            ],
        ],
    ],

    'privacidad' => [
        'titulo_pestana' => 'Política de privacidad',
        'titulo' => 'Política de privacidad',
        'enlace_otro' => 'Términos y condiciones',
        'introduccion' => [
            'Esta política explica qué datos recoge :titular a través de :producto, para qué los usa, quién los ve, con qué terceros los comparte, cuánto tiempo los conserva y cómo puedes ejercer tus derechos. :titular solo pide lo que necesita para que el servicio funcione.',
            'Responsable de los datos: :titular, con domicilio en :direccion. Puedes escribirnos por los medios que figuran al final de esta página.',
        ],
        'secciones' => [
            [
                'titulo' => 'Qué datos recogemos',
                'items' => [
                    'Tu cuenta y tu persona: nombres, apellidos, tipo y número de documento (cédula de identidad, licencia de conducir o pasaporte), celular, usuario y correo electrónico, que sirve de respaldo para recuperar tu acceso. Si eres proveedor y piloto, también tu fecha de nacimiento. Tu contraseña se guarda solo como un hash irreversible (ver «Cómo protegemos tus datos»).',
                    'Tu verificación: la selfie que tomas con la cámara de la app y la foto de tu documento. Es distinta de tu foto de perfil, que sí ven las otras partes.',
                    'Si eres una empresa (opcional): razón social, documento fiscal, nombre comercial, logo y personas de contacto de referencia, con su nombre, teléfono y cargo.',
                    'Si eres proveedor: licencia o habilitación de piloto, registros o matrículas, seguros, certificados, y los datos y las fotos de tu dron. Opcionalmente, el nombre y el celular de las personas que forman tu equipo.',
                    'Si eres cliente: tus terrenos con los lotes dibujados, las hectáreas, el cultivo, el departamento, la provincia, el municipio y la comunidad, los accesos, las observaciones, los colindantes sensibles, las fotos del terreno y la referencia de tu dirección.',
                    'Tu actividad en el mercado: solicitudes, ofertas y su historial, calificaciones y reclamos con sus pruebas, reportes y bloqueos, incidencias con sus fotos, y tus preferencias de aviso y filtros.',
                    'Durante el servicio: las fotos de avance (comprimidas a menos de 300 KB), el avance, las hectáreas ejecutadas y los mensajes de texto entre cliente y proveedor adjudicado.',
                    'La ubicación del piloto, solo mientras el servicio está en ejecución, y la ubicación que se adjunta a una incidencia o a un aviso de auxilio (ver «Tu ubicación»).',
                    'Datos técnicos: un identificador de tu dispositivo y un token para mantener tu sesión; la versión y fecha de los términos y la privacidad que aceptaste; la dirección IP y los registros del servidor que usamos para seguridad y para limitar intentos abusivos; y un registro de las acciones relevantes sobre cuentas y servicios (quién, cuándo y qué).',
                ],
                'parrafos' => [
                    ':titular no recoge pagos, contactos de tu teléfono, tu galería completa (solo las fotos que eliges), micrófono, ubicación en segundo plano, identificador de publicidad ni datos para analíticas o publicidad de terceros.',
                ],
            ],
            [
                'titulo' => 'Para qué los usamos',
                'parrafos' => [
                    'Usamos tus datos para operar el servicio y para nada más. No los vendemos, no hacemos publicidad ni seguimiento de tu actividad en otras apps o sitios, y no los usamos para tomar decisiones automáticas sobre ti: la aprobación y las sanciones las decide una persona.',
                ],
                'items' => [
                    'Crear tu cuenta, verificar que eres quien dices ser y evitar cuentas repetidas o fraudes. Por eso una misma persona tiene, como máximo, una cuenta de cliente y una de proveedor.',
                    'Mostrar solicitudes a los pilotos habilitados, avisarles de las nuevas según sus filtros y preferencias, y permitir la negociación entre las partes.',
                    'Registrar el estado y el resultado de cada servicio: avance, fotos, mensajes, monto final, calificaciones.',
                    'Atender reclamos, reportes e incidencias, moderar calificaciones, mensajes y fotos, y decidir sobre suspensiones y bajas.',
                    'Avisarte de los vencimientos de tus documentos de proveedor, 30 y 7 días antes, y enviarte los códigos de seguridad que pides.',
                    'Darte soporte desde la app y la web, y cumplir la ley cuando una autoridad competente nos lo exija.',
                    'La selfie y el documento de verificación se usan solo para verificar tu identidad y prevenir el fraude. No se usan para otro fin, no se venden ni se comparten.',
                ],
            ],
            [
                'titulo' => 'Quién ve tus datos y cuándo',
                'parrafos' => [
                    'Dentro de :titular, tus datos los ve solo el equipo autorizado, y cada persona del equipo solo accede a lo que su función necesita. Las otras partes del servicio ven únicamente lo que sigue.',
                    'Antes de ofertar, el piloto ve los lotes dibujados, el departamento, la provincia, el municipio y la comunidad, las hectáreas, el cultivo, los accesos, las observaciones y las fotos del terreno, para decidir su precio. No ve quién es el cliente.',
                ],
                'items' => [
                    'Los datos de contacto del cliente (nombre, teléfono, documento y dirección; en una empresa, la persona de contacto y la razón social) se entregan solo al proveedor adjudicado, y solo después de adjudicar. Un proveedor nunca ve datos de clientes que no le fueron adjudicados.',
                    'El teléfono y el documento del piloto los ve el cliente solo después de adjudicar. Antes, en cada oferta el cliente ve el nombre y la foto del piloto, la calificación, los servicios y la tasa de cancelación del proveedor, los datos y las fotos aprobadas del dron, y que el proveedor está verificado. Después de adjudicar ve también a las personas del equipo.',
                    'Un proveedor nunca ve las ofertas de otros proveedores. Los demás pilotos no ven las fotos, el avance, los mensajes ni la ubicación de un servicio que no es suyo.',
                    'Los mensajes los ven solo el cliente y el proveedor adjudicado. Soporte puede leer una conversación únicamente si hay un reclamo abierto, o solo la parte reportada si alguien reporta un mensaje, una conversación o una foto; cada lectura queda registrada.',
                    'Tu selfie y tus documentos los ven solo los administradores y el soporte de :titular que revisan tu cuenta, con un permiso específico, y cada acceso queda registrado. Las otras partes ven únicamente la insignia «Verificado», sin los documentos.',
                    'La calificación que el cliente escribe la ve el proveedor; la que el proveedor escribe sobre el cliente es privada y solo la usan la moderación y el soporte.',
                ],
            ],
            [
                'titulo' => 'Con qué terceros compartimos datos',
                'parrafos' => [
                    ':titular no vende tus datos. Para operar usa proveedores de infraestructura que procesan datos en nombre de :titular y bajo sus instrucciones; solo reciben lo necesario para su función:',
                ],
                'items' => [
                    'Servidores donde corren la aplicación y la base de datos, administrados por :titular.',
                    'Almacenamiento privado de archivos (fotos, documentos y reportes) compatible con S3; los archivos no son públicos y solo se abren con enlaces temporales a quien tiene permiso. En el entorno de producción lo presta Cloudflare (servicio R2).',
                    'Un servidor de correo (SMTP), que recibe tu correo y el contenido de los avisos y códigos que te enviamos por esa vía.',
                    'WhatsApp Business y mensajes de texto (SMS), cuando se activan, para enviarte códigos de seguridad: el proveedor del canal recibe tu número de teléfono y el código.',
                    'Un proveedor de mapas, que recibe la dirección IP de tu dispositivo y la zona que miras para dibujar el mapa (hoy, los servidores de OpenStreetMap).',
                    'Autoridades, cuando una ley o una orden válida nos obligue a entregar información.',
                    'Algunos de estos proveedores pueden procesar datos fuera de Bolivia. Si agregamos otros proveedores que reciban datos personales, lo diremos en una nueva versión de esta política.',
                ],
            ],
            [
                'titulo' => 'Tu ubicación',
                'items' => [
                    'La app te pide permiso de ubicación solo cuando una función la necesita, y antes te explica para qué. Puedes negar o retirar el permiso en los ajustes de tu teléfono; la app sigue funcionando.',
                    'La app del piloto comparte su última posición con el cliente únicamente mientras el servicio está en ejecución y la app está abierta. El piloto puede desactivarla; entonces el cliente ve «sin ubicación reciente».',
                    'El servidor guarda solo la última posición, no la ruta, y la elimina cuando el servicio finaliza o se cancela. El cliente no ve ruta, velocidad ni tiempo estimado.',
                    'Si pulsas el botón de auxilio o reportas una incidencia, la ubicación que acompaña ese aviso se guarda para que soporte te ayude y atienda el caso. Es solo si tú lo haces, nunca de forma automática ni en segundo plano. La ve soporte, no la otra parte, y se elimina :emergencia_dias días después de cerrar la incidencia. En la app del piloto es la posición del dispositivo en ese momento; en la del cliente, la ubicación del terreno del servicio.',
                    'La ubicación nunca se escribe en historiales, registros técnicos, reportes de error ni analíticas. El mapa de solicitudes no recibe la posición del piloto.',
                ],
            ],
            [
                'titulo' => 'Cámara, fotos y notificaciones',
                'items' => [
                    'La cámara se usa para tu selfie de verificación, la foto de tu documento, las fotos del terreno, las fotos de avance y las de una incidencia. La app te explica para qué antes de pedirte el permiso.',
                    'Las fotos de tu galería se usan solo si tú las eliges (foto de perfil, logo, documentos y terreno). La app no lee el resto de tu galería.',
                    'Las notificaciones te avisan de ofertas, adjudicaciones, mensajes y solicitudes nuevas. No son obligatorias para usar la app y puedes desactivarlas cuando quieras en la app o en los ajustes de tu teléfono.',
                ],
            ],
            [
                'titulo' => 'Cuánto tiempo conservamos tus datos',
                'parrafos' => [
                    'Los datos se conservan mientras tu cuenta esté activa. Cuando la eliminas, los datos personales se borran o se anonimizan, salvo lo que se detalla aquí, que se conserva por el plazo indicado y no se usa para otro fin.',
                ],
                'items' => [
                    'La ubicación del piloto no se conserva: se elimina al finalizar o cancelar el servicio. La ubicación de un aviso de auxilio o una incidencia se elimina :emergencia_dias días después de cerrarla.',
                    'La selfie y el documento de verificación de una cuenta eliminada se conservan aislados, para defendernos de un reclamo y prevenir fraude, :dias_sin_antecedentes días si no tuviste antecedentes y :meses meses si los tuviste (una suspensión, un rechazo por fraude o falsedad documental, una incidencia grave o un reclamo resuelto con tu responsabilidad, o una baja administrativa); luego se destruyen. Las que se rechazan o se reemplazan se destruyen a los :dias_sin_antecedentes días.',
                    'Después queda solo un registro mínimo antifraude: un código derivado de tu documento con una clave del servidor, el motivo y la fecha, sin imágenes ni nombre. Dura 12 meses si no tuviste antecedentes y 36 si los tuviste.',
                    'Los mensajes y las fotos de un servicio se conservan hasta :servicio_meses meses después de que cierra el plazo de reclamo y de calificación, y luego se eliminan. El precio acordado, el avance, las hectáreas y las calificaciones quedan como registro del resultado del servicio, sin tus datos identificables cuando eliminas tu cuenta.',
                    'La prueba de que aceptaste los términos y la privacidad (versión y fecha) se conserva 36 meses.',
                    'La bitácora de auditoría conserva quién hizo qué y cuándo; al eliminar una cuenta, los valores personales se redactan. Los registros técnicos del servidor se conservan por un tiempo limitado, solo para seguridad.',
                    'Los respaldos de seguridad rotan cada :respaldos días; si eliminaste tu cuenta, tus datos desaparecen de ellos en ese plazo y no se restauran.',
                    'No eliminamos cuentas por inactividad. Si algún día lo hiciéramos, te avisaríamos con anticipación por la app y por correo antes de borrar algo.',
                    'Conservamos además lo que una ley nos obligue a conservar, por el tiempo que ella indique. Una calificación no se edita, y ocultarla no la borra. Un registro ya confirmado por el servidor no se reescribe: una corrección es un registro nuevo, con su motivo y su autor.',
                ],
            ],
            [
                'titulo' => 'Cómo eliminar tu cuenta y tus datos',
                'items' => [
                    'En la app: Perfil, Privacidad y datos, «Eliminar mi cuenta». Lo confirmas con tu contraseña o con un código que te enviamos por WhatsApp, SMS o correo. Cada cuenta se elimina por separado: eliminar la de cliente no elimina la de proveedor.',
                    'Si no puedes entrar a la app, escríbenos por los medios de contacto de esta página; un administrador verifica tu identidad y registra la solicitud.',
                    'Al pedirla se cierran tus sesiones, tus solicitudes abiertas se cancelan y tus ofertas vigentes se retiran. Tienes :gracia días para arrepentirte: entras a la app y eliges «Conservar mi cuenta». Pasado ese tiempo la eliminación se ejecuta, te avisamos por correo y se completa como máximo a los :plazo días de tu pedido.',
                    'No puedes eliminar tu cuenta mientras tengas un servicio adjudicado o en ejecución, un reclamo abierto, una incidencia grave abierta o pilotos vinculados a tu empresa; la app te explica el motivo y la salida. Las cuentas que todavía no fueron aprobadas se eliminan sin condiciones.',
                    'Se borran o se anonimizan tu nombre, usuario, teléfono, correo, contraseña, fotos, tokens y avisos, los datos de empresa y del dron, los documentos del proveedor, los terrenos sin servicio vigente y el texto de los mensajes que escribiste. Los servicios, ofertas y calificaciones se conservan sin datos identificables y aparecen como «Cuenta eliminada». Lo que se conserva y por cuánto tiempo está en la sección anterior.',
                    'Si después vuelves, te registras de nuevo y empiezas de cero: no se recuperan la reputación ni los servicios anteriores.',
                ],
            ],
            [
                'titulo' => 'Cómo protegemos tus datos',
                'parrafos' => [
                    'Aplicamos estas medidas. Ningún sistema es totalmente invulnerable, pero no describimos aquí nada que no esté en funcionamiento.',
                ],
                'items' => [
                    'Las apps y la API se comunican por conexiones seguras (HTTPS).',
                    'Tu contraseña se guarda únicamente como un hash irreversible: nadie, ni el personal de :titular, puede verla ni recuperarla. Si la olvidas, solo se puede restablecer. Los códigos de seguridad también se guardan con hash, vencen en 10 minutos, se usan una sola vez y admiten 5 intentos.',
                    'La sesión de cada dispositivo usa un token que caduca a los 30 días y que puedes revocar: cerrar sesión, cambiar la contraseña o eliminar la cuenta lo invalidan. En tu teléfono el token se guarda en el almacenamiento seguro del sistema (Keychain o Keystore) y se borra al cerrar sesión.',
                    'El inicio de sesión y los códigos tienen límites de intentos y bloqueos temporales para frenar los intentos abusivos.',
                    'Los documentos del proveedor y la verificación se guardan en almacenamiento privado, se sirven con enlaces temporales solo a quien tiene permiso, y no entran a los registros técnicos ni a la bitácora; la app borra su copia local de tu selfie y de tu documento cuando los sube.',
                    'Cada acceso del personal a documentos de verificación y a las lecturas de conversaciones queda registrado. Los permisos del personal son por función.',
                    'Si un incidente de seguridad afecta tus datos personales, te avisaremos por la app o por correo, indicando qué pasó y qué puedes hacer.',
                ],
            ],
            [
                'titulo' => 'Tus derechos',
                'parrafos' => [
                    'Tienes derecho a conocer qué datos tiene :titular sobre ti. La Constitución Política del Estado reconoce el derecho a la privacidad (art. 21) y la acción de protección de privacidad (art. 130), que te permite pedir que se conozcan, se rectifiquen o se eliminen tus datos. Además, :titular te ofrece directamente estos derechos:',
                ],
                'items' => [
                    'Acceso: saber qué datos tenemos sobre ti y recibir una copia de los datos de tu cuenta en un formato de uso común.',
                    'Rectificación: corregir los datos equivocados; muchos puedes cambiarlos tú mismo en tu perfil. Una corrección sobre un registro ya confirmado se hace con un registro nuevo.',
                    'Eliminación: eliminar tu cuenta y tus datos, como se explica más arriba, dentro de la app.',
                    'Oposición y revocación: retirar tu consentimiento cuando el tratamiento se base en él, por ejemplo desactivando la ubicación o las notificaciones, sin perder el acceso a lo esencial de la app.',
                    'Portabilidad razonable: llevarte la copia de tus datos a otro servicio.',
                    'Reclamar: si crees que no atendimos bien tu pedido, escríbenos y lo revisamos; también puedes acudir a la autoridad competente.',
                ],
                'parrafos_finales' => [
                    'Para ejercer un derecho, escríbenos por los medios de contacto de esta página desde el correo o el teléfono de tu cuenta, o desde la app si ahí está disponible. Para protegerte, podemos pedirte datos que confirmen que eres tú. Respondemos en un plazo máximo de 30 días. Algunos datos se conservan por el plazo indicado más arriba, y te explicaremos cuáles y por qué.',
                    'Si alguien se registró con tu documento sin que seas tú, escríbenos: un administrador compara la selfie con el documento y elimina la cuenta falsa, con su motivo.',
                ],
            ],
            [
                'titulo' => 'Menores de edad',
                'parrafos' => [
                    ':producto es solo para mayores de 18 años y no está dirigido a niñas, niños ni adolescentes. Si nos enteramos de que una cuenta pertenece a un menor, la eliminamos.',
                ],
            ],
            [
                'titulo' => 'Cambios en esta política',
                'parrafos' => [
                    'Cada versión de este documento tiene un número y una fecha de vigencia, que aparecen al inicio de la página. Esta política puede cambiar; cuando cambia, lo avisamos por estos medios:',
                ],
                'items' => [
                    'Dentro de la app: un aviso y, si el cambio es de fondo, la pantalla para aceptar la nueva versión.',
                    'En esta misma página, con la versión y la fecha desde la que rige.',
                ],
                'condicionales' => [
                    ['si' => 'redes', 'texto' => 'En nuestras redes sociales oficiales: :valor.'],
                ],
                'parrafos_finales' => [
                    'Cuando el cambio altera para qué usamos tus datos, con quién los compartimos o cuánto los conservamos, te pedimos de nuevo tu aceptación antes de aplicarlo a tu cuenta.',
                ],
            ],
        ],
    ],

];
