<?php

/*
 * Solicitudes de servicio del marketplace (ADR 0027): cliente, zonas, terrenos
 * y solicitudes, en la API y en el panel.
 */
return [
    'campos' => [
        'tipo' => 'tipo de cliente',
        'nombre' => 'nombre',
        'username' => 'usuario',
        'email' => 'correo',
        'password' => 'contraseña',
        'telefono' => 'teléfono',
        'razon_social' => 'razón social',
        'documento_fiscal' => 'documento fiscal',
        'hectareas' => 'hectáreas',
        'cultivo' => 'cultivo',
        'geometria' => 'geometría',
        'geometria.type' => 'tipo de geometría',
        'geometria.coordinates' => 'coordenadas',
        'referencias_acceso' => 'referencias de acceso',
        'colindantes_sensibles' => 'colindantes sensibles',
        'acceso_dificil' => 'acceso difícil',
        'zona_id' => 'zona',
        'uuid_cliente' => 'identificador de la solicitud',
        'terreno_id' => 'terreno',
        'tipo_aplicacion' => 'tipo de aplicación',
        'hectareas_solicitadas' => 'hectáreas solicitadas',
        'producto_indicado' => 'producto indicado',
        'aporta_producto' => 'quién aporta el producto',
        'ventana_desde' => 'fecha deseada desde',
        'ventana_hasta' => 'fecha deseada hasta',
        'urgente' => 'urgencia',
        'observaciones' => 'observaciones',
        'motivo' => 'motivo',
    ],

    'errores' => [
        'documento_fiscal_duplicado' => 'El documento fiscal :documento ya pertenece a otro cliente.',
        'sin_cliente' => 'Tu cuenta no tiene un perfil de cliente.',
        'cliente_suspendido' => 'Tu cuenta de cliente está suspendida y no puede pedir servicios.',
        'terreno_ajeno' => 'El terreno no existe.',
        'solicitud_ajena' => 'La solicitud no existe.',
        'uuid_en_uso' => 'Ese identificador de solicitud ya se usó. Genera uno nuevo.',
        'hectareas_exceden_terreno' => 'Pides :solicitadas ha y el terreno tiene :disponibles ha.',
        'ventana_vencida' => 'La fecha deseada ya pasó. Elige una fecha de hoy en adelante.',
        'motivo_requerido' => 'Indica el motivo de la cancelación.',
        'no_se_puede_editar' => 'La solicitud ya no admite ese cambio.',
        'terreno_con_solicitudes_vivas' => 'El terreno tiene solicitudes en curso.',
        'transicion_no_permitida' => 'La solicitud no puede pasar de «:desde» a «:hacia».',
    ],

    'estado' => [
        'borrador' => 'Borrador',
        'publicada' => 'Publicada',
        'en_asignacion' => 'En asignación',
        'sin_proveedor' => 'Sin proveedor',
        'asignada' => 'Asignada',
        'en_camino' => 'En camino',
        'en_ejecucion' => 'En ejecución',
        'suspendida_por_clima' => 'Suspendida por clima',
        'ejecutada' => 'Ejecutada',
        'en_disputa' => 'En disputa',
        'completada' => 'Completada',
        'cancelada' => 'Cancelada',
    ],

    'tipo_aplicacion' => [
        'herbicida' => 'Herbicida',
        'insecticida' => 'Insecticida',
        'fungicida' => 'Fungicida',
        'fertilizante_foliar' => 'Fertilizante foliar',
        'desecante' => 'Desecante',
        'otro' => 'Otro',
    ],

    'aporta_producto' => [
        'cliente' => 'El cliente',
        'proveedor' => 'El proveedor',
    ],

    'origen' => [
        'cliente' => 'Cliente',
        'proveedor' => 'Proveedor',
        'administrador' => 'Administrador',
        'sistema' => 'Sistema',
    ],
];
