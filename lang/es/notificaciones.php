<?php

return [
    'titulo' => [
        'trabajo_cerrado' => 'Se cerró un trabajo de :hectareas ha (solicitud #:solicitud).',
        'sujeto_aprobado' => 'El :sujeto «:nombre» fue aprobado.',
        'sujeto_rechazado' => 'El :sujeto «:nombre» fue rechazado. Motivo: :motivo',
        'sujeto_suspendido' => 'El :sujeto «:nombre» fue suspendido. Motivo: :motivo',
        'sujeto_rehabilitado' => 'El :sujeto «:nombre» volvió a estar aprobado.',
        'sujeto_dado_de_baja' => 'El :sujeto «:nombre» fue dado de baja. Motivo: :motivo',
        'sujeto_reenviado' => 'El :sujeto «:nombre» se reenvió a revisión.',
        'documento_por_vencer' => 'El documento «:documento» del :sujeto vence el :fecha (en :dias días). Carga uno vigente a tiempo.',
        'documento_vencido' => 'Venció el documento «:documento» del :sujeto el :fecha: queda suspendido hasta que cargues uno vigente.',
        'proveedor_registrado' => 'Un proveedor :tipo espera tu revisión: «:nombre».',
        'oferta_creada' => 'Nueva oferta de :hectareas ha (solicitud #:solicitud). Respóndela antes de las :hora.',
        'oferta_vencida' => 'Venció sin respuesta la oferta de la solicitud #:solicitud.',
        'oferta_retirada' => 'Se retiró la oferta de la solicitud #:solicitud. Motivo: :motivo.',
        'solicitud_asignada' => 'La solicitud #:solicitud quedó asignada a «:proveedor».',
        'solicitud_reasignada' => 'El proveedor canceló la solicitud #:solicitud: vuelve a asignación con prioridad.',
        'solicitud_sin_proveedor' => 'Ningún proveedor aceptó la solicitud #:solicitud: amplía la ventana, mejora la tarifa o cancélala sin penalidad.',
        'proveedor_sancionado' => 'Sanción a «:proveedor»: :sancion por :causa.',
        'solicitud_completada' => 'El servicio #:solicitud quedó completado: puedes calificarlo durante 7 días.',
        'calificacion_registrada' => 'Recibiste una calificación de :estrellas estrellas en el servicio #:solicitud.',
        'calificacion_reportada' => '«:proveedor» reportó una calificación del servicio #:solicitud: espera tu decisión en el panel.',
        'disputa_abierta' => 'Se abrió una disputa por :motivo en el servicio #:solicitud. Responde antes del plazo.',
        'disputa_resuelta' => 'La disputa del servicio #:solicitud se cerró: :resultado. Reembolso: :monto.',
        'disputa_apelada' => 'Se apeló la resolución de la disputa del servicio #:solicitud: vuelve a revisión.',
        'cliente_con_aviso' => 'El promedio de las calificaciones que recibes de los proveedores es :promedio. Si no mejora, se limitará tu publicación de solicitudes.',
        'equipo_en_camino' => 'El equipo salió hacia tu lote: el servicio #:solicitud empieza pronto.',
        'solicitud_ejecutada' => 'El servicio #:solicitud quedó ejecutado: confirma que estás conforme o abre una disputa antes del plazo.',
        'publicacion_limitada' => 'La publicación de solicitudes de «:cliente» quedó limitada por su calificación promedio (:promedio) hasta una revisión.',
    ],

    'errores' => [
        'no_encontrada' => 'No encontramos ese aviso entre los tuyos.',
    ],
];
