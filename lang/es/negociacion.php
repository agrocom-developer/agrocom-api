<?php

/*
 * Negociación oferta-demanda (`docs/especificacion/negociacion-contrato.md`): ofertas, mapa de
 * solicitudes abiertas, seguimiento con fotos, chat y ubicación del piloto. Tuteo estándar.
 */
return [
    'campos' => [
        'uuid_cliente' => 'identificador',
        'tipo' => 'tipo de oferta',
        'precio_ha' => 'precio por hectárea',
        'equipo_id' => 'equipo',
        'nota' => 'nota',
        'justificacion_precio' => 'justificación del precio',
        'orden' => 'orden',
        'precio_ha_min' => 'precio mínimo por hectárea',
        'precio_ha_max' => 'precio máximo por hectárea',
        'monto_max' => 'total máximo',
        'calificacion_min' => 'calificación mínima',
        'tipo_proveedor' => 'tipo de proveedor',
        'capacidad_tanque_min' => 'capacidad mínima del tanque',
        'hectareas_ejecutadas' => 'hectáreas ejecutadas',
        'porcentaje' => 'porcentaje',
        'motivo' => 'motivo',
        'texto' => 'mensaje',
    ],

    'errores' => [
        'sin_proveedor' => 'Tu cuenta no pertenece a ningún proveedor.',
        'proveedor_no_habilitado' => 'Tu proveedor todavía no está habilitado: necesita aprobación y un equipo apto para ver solicitudes y ofertar.',
        'solicitud_ajena' => 'La solicitud no existe.',
        'oferta_ajena' => 'La oferta no existe.',
        'evidencia_ajena' => 'La foto no existe.',
        'solicitud_no_abierta' => 'La solicitud ya no recibe ofertas.',
        'solicitud_ya_adjudicada' => 'La solicitud ya fue adjudicada a otra oferta.',
        'oferta_no_vigente' => 'La oferta ya no está vigente: venció, la retiraron o ya se respondió.',
        'oferta_definitiva' => 'Esta es la última oferta de la negociación: solo se puede aceptar o rechazar.',
        'no_es_su_turno' => 'Esta oferta es tuya: la responde la otra parte.',
        'negociacion_vigente' => 'Ya tienes una negociación vigente en esta solicitud.',
        'negociacion_cerrada' => 'Tu negociación en esta solicitud ya terminó y no se puede abrir otra.',
        'equipo_no_disponible' => 'El equipo ya no está disponible para esas fechas. Elige otra oferta o espera a que se libere.',
        'equipo_no_apto' => 'Necesitas un equipo apto (piloto y dron aprobados, con documentos vigentes) para ofertar.',
        'propia_cuenta' => 'No puedes ofertar sobre una solicitud de tu propia cuenta.',
        'precio_invalido' => 'Indica un precio por hectárea mayor que cero.',
        'precio_excede_maximo' => 'El precio por hectárea no puede ser mayor que :maximo.',
        'precio_igual_propuesto' => 'Ese es el precio propuesto: elige «aceptar el precio» o indica uno distinto.',
        'precio_sin_cambio' => 'Ese es el mismo precio de la oferta vigente: indica uno distinto.',
        'uuid_en_uso' => 'Ese identificador ya se usó. Genera uno nuevo.',
        'motivo_requerido' => 'Indica el motivo.',
        'filtro_desconocido' => 'Ese filtro de avisos no existe.',
        'filtro_invalido' => 'El valor de ese filtro de avisos no es válido.',
        'estado_invalido' => 'La solicitud no está en un estado que permita esta acción.',
        'avance_requiere_uno' => 'Indica las hectáreas ejecutadas o el porcentaje, no los dos ni ninguno.',
        'avance_invalido' => 'El avance no puede ser negativo.',
        'hectareas_exceden_tolerancia' => 'Las hectáreas ejecutadas superan lo solicitado más la tolerancia de :tolerancia %.',
        'correccion_sin_motivo' => 'El avance es menor al último registrado: indica el motivo de la corrección.',
        'limite_fotos_de_avance' => 'Un servicio admite hasta :maximo fotos de avance. Ya subiste el máximo.',
        'limite_fotos_del_chat' => 'El chat de un servicio admite hasta :maximo fotos. Ya se envió el máximo.',
        'evidencia_muy_grande' => 'La foto debe pesar menos de :maximo KB: comprímela antes de subirla.',
        'ubicacion_no_permitida' => 'La ubicación solo se envía mientras el servicio está en ejecución.',
        'mensaje_invalido' => 'Escribe un mensaje de hasta :maximo caracteres.',
        'chat_cerrado' => 'El chat de este servicio ya se cerró: solo puedes leer los mensajes.',
        'pago_solo_proveedor' => 'Solo el proveedor del servicio puede informar que recibió el pago.',
        'pago_solo_titular' => 'Solo el titular del proveedor puede informar que recibió el pago.',
        'precio_final_solo_cliente' => 'Solo el cliente de la solicitud puede confirmar el precio final.',
        'pago_ya_confirmado' => 'El pago de este servicio ya fue confirmado.',
        'bbox_invalido' => 'El encuadre debe ser «oeste,sur,este,norte» con números.',
    ],

    // Líneas de sistema del chat (`neg_mensajes.tipo = sistema`), una por hecho de la solicitud.
    'chat_sistema' => [
        'solicitud_adjudicada' => 'La solicitud quedó adjudicada. Ya pueden coordinar por aquí.',
        'servicio_iniciado' => 'El piloto inició el servicio.',
        'servicio_finalizado' => 'El piloto finalizó el servicio. Precio final: :monto.',
        'precio_final_confirmado' => 'El cliente confirmó el precio final: :monto.',
    ],

    // Intervención del administrador desde el panel (ficha de la solicitud).
    'panel' => [
        'negociacion_cerrada' => 'Cerraste la negociación. El cliente y el proveedor reciben el motivo.',
        'error_motivo_requerido' => 'Escribe el motivo del cierre.',
        'error_motivo_corto' => 'El motivo debe tener al menos 3 caracteres.',
        'error_motivo_largo' => 'El motivo no puede tener más de 500 caracteres.',
    ],
];
