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
        'solicitud_no_abierta' => 'La solicitud ya no está abierta a ofertas.',
        'solicitud_ya_adjudicada' => 'La solicitud ya fue adjudicada a otra oferta.',
        'oferta_no_vigente' => 'La oferta ya no está vigente: venció, la retiraron o ya se respondió.',
        'oferta_definitiva' => 'Esta es la última oferta de la negociación: solo se puede aceptar o rechazar.',
        'no_es_su_turno' => 'Esta oferta es tuya: la responde la otra parte.',
        'negociacion_vigente' => 'Ya tienes una negociación vigente en esta solicitud.',
        'negociacion_cerrada' => 'Tu negociación en esta solicitud ya terminó y no se puede abrir otra.',
        'equipo_no_disponible' => 'El equipo ya no está disponible para esas fechas. Elige otra oferta o espera a que se libere.',
        'equipo_no_apto' => 'Necesitas un equipo apto (piloto y dron aprobados, con documentos vigentes) para ofertar.',
        'propia_cuenta' => 'No puedes ofertar sobre una solicitud de tu propia cuenta.',
        'precio_invalido' => 'Indica un precio por hectárea mayor a cero.',
        'precio_excede_maximo' => 'El precio por hectárea no puede pasar de :maximo.',
        'precio_igual_propuesto' => 'Ese precio es el propuesto: elige «aceptar el precio» o indica uno distinto.',
        'precio_sin_cambio' => 'Ese es el mismo precio de la oferta vigente: indica uno distinto.',
        'uuid_en_uso' => 'Ese identificador ya se usó. Genera uno nuevo.',
        'motivo_requerido' => 'Indica el motivo.',
        'macrorregion_invalida' => 'La macrorregión no existe.',
        'estado_invalido' => 'La solicitud no está en un estado que permita esta acción.',
        'avance_requiere_uno' => 'Indica las hectáreas ejecutadas o el porcentaje, no los dos ni ninguno.',
        'avance_invalido' => 'El avance no puede ser negativo.',
        'hectareas_exceden_tolerancia' => 'Las hectáreas ejecutadas pasan lo solicitado más la tolerancia de :tolerancia %.',
        'correccion_sin_motivo' => 'El avance es menor al último registrado: indica el motivo de la corrección.',
        'evidencia_muy_grande' => 'La foto debe pesar menos de :maximo KB: comprímela antes de subirla.',
        'ubicacion_no_permitida' => 'La ubicación solo se envía mientras el servicio está en ejecución.',
        'mensaje_invalido' => 'Escribe un mensaje de hasta :maximo caracteres.',
        'chat_cerrado' => 'El chat de este servicio ya cerró: solo puedes leer los mensajes.',
        'bbox_invalido' => 'El encuadre debe ser «oeste,sur,este,norte» con números.',
    ],

    // Intervención del administrador desde el panel (ficha de la solicitud).
    'panel' => [
        'negociacion_cerrada' => 'Cerraste la negociación. El cliente y el proveedor reciben el motivo.',
        'error_motivo_requerido' => 'Escribe el motivo del cierre.',
        'error_motivo_corto' => 'El motivo debe tener al menos 3 caracteres.',
        'error_motivo_largo' => 'El motivo no puede pasar de 500 caracteres.',
    ],
];
