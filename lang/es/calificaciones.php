<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Calificaciones del marketplace (ADR 0031 y 0039)
    |--------------------------------------------------------------------------
    |
    | El cliente califica al proveedor y al piloto de un servicio
    | completado; el proveedor califica al cliente en privado; el administrador
    | modera. Español neutro, tuteo (CLAUDE.md, «Tuteo estándar»).
    */

    'campos' => [
        'calificaciones' => 'calificaciones',
        'uuid_cliente' => 'identificador de la calificación',
        'sujeto_tipo' => 'sujeto',
        'estrellas' => 'estrellas',
        'etiquetas' => 'etiquetas',
        'comentario' => 'comentario',
        'motivo' => 'motivo',
        'causa' => 'causa',
    ],

    'estado' => [
        'visible' => 'Visible',
        'en_moderacion' => 'En moderación',
        'oculta' => 'Oculta',
    ],

    'sujeto' => [
        'proveedor' => 'Proveedor',
        'piloto' => 'Piloto',
        'cliente' => 'Cliente',
    ],

    'autor' => [
        'cliente' => 'La escribió el cliente',
        'proveedor' => 'La escribió el proveedor (privada)',
    ],

    'etiqueta' => [
        'puntualidad' => 'Puntualidad',
        'cobertura_pareja' => 'Cobertura pareja',
        'comunicacion' => 'Comunicación',
        'cuidado_entorno' => 'Cuidado del entorno',
        'orden_lote' => 'Orden en el lote',
        'acceso_lote' => 'Acceso al lote',
        'claridad_pedido' => 'Claridad del pedido',
    ],

    'causa' => [
        'insultos_o_datos_personales' => 'Insultos o datos personales',
        'causa_ajena' => 'Causa ajena al proveedor (clima o demora del cliente)',
        'represalia' => 'Represalia tras una disputa',
        'sin_servicio_real' => 'Cuenta sin servicio real',
        'otra' => 'Otra causa',
    ],

    'errores' => [
        'servicio_ajeno' => 'El servicio no existe.',
        'calificacion_ajena' => 'La calificación no existe.',
        'sin_proveedor' => 'Tu cuenta no pertenece a ningún proveedor.',
        'servicio_no_completado' => 'Solo se califica un servicio completado.',
        'plazo_vencido' => 'El plazo para calificar este servicio venció: son :dias días desde que se completó.',
        'sujeto_invalido' => 'En este servicio no hay a quién calificar como «:sujeto».',
        'etiqueta_invalida' => 'La etiqueta «:etiqueta» no está en el catálogo para esta calificación.',
        'ya_calificado' => 'Ya calificaste a ese sujeto en este servicio. Una calificación no se edita.',
        'uuid_en_uso' => 'Ese identificador de calificación ya se usó. Genera uno nuevo.',
        'motivo_requerido' => 'Indica el motivo.',
        'causa_requerida' => 'Indica la causa.',
        'no_reportable' => 'La calificación ya está oculta.',
        'transicion_no_permitida' => 'La calificación no puede pasar de «:desde» a «:hacia».',
        'propia_calificacion' => 'No puedes moderar una calificación que escribiste tú.',
    ],

    // Panel del administrador: moderación (política de calificación, «Moderación»).
    'panel' => [
        'titulo' => 'Calificaciones',
        'subtitulo' => 'Modera las calificaciones que incumplen la política. Ocultar no borra: la calificación deja de contar para el promedio y conserva su registro.',
        'vacio_titulo' => 'Todavía no hay calificaciones',
        'vacio_detalle' => 'Aparecen cuando los clientes y los proveedores califican un servicio completado.',
        'filtro_estado' => 'Estado',
        'filtro_sujeto' => 'Sujeto',
        'filtro_todos' => 'Todos',
        'filtro_vacio_titulo' => 'Sin resultados para este filtro',
        'filtro_vacio_detalle' => 'Cambia el estado o el sujeto para ver más calificaciones.',
        'paginacion_aria' => 'Paginación de calificaciones',
        'col_sujeto' => 'Sujeto',
        'col_estrellas' => 'Estrellas',
        'col_comentario' => 'Comentario',
        'col_fecha' => 'Fecha',
        'col_estado' => 'Estado',
        'de_servicio' => 'servicio #:solicitud',
        'sin_comentario' => 'Sin comentario',
        'ultima_moderacion' => ':rol: :motivo',
        'autor_administrador' => 'Administrador',
        'autor_proveedor' => 'Proveedor (reporte)',
        'ocultar' => 'Ocultar',
        'ocultar_titulo' => 'Ocultar la calificación',
        'ocultar_mensaje' => 'La calificación deja de contar para el promedio y queda registrada con tu nombre, la causa y el motivo. Puedes restablecerla después.',
        'restablecer' => 'Dejar visible',
        'restablecer_titulo' => 'Dejar visible la calificación',
        'restablecer_mensaje' => 'La calificación vuelve a contar para el promedio y queda registrada con tu nombre y el motivo.',
        'causa_etiqueta' => 'Causa',
        'motivo_etiqueta' => 'Motivo',
        'motivo_ayuda' => 'Queda en el registro de moderación junto con tu nombre.',
        'oculta' => 'La calificación quedó oculta y el promedio se recalculó.',
        'restablecida' => 'La calificación quedó visible y el promedio se recalculó.',
    ],
];
