<?php

/*
 * Copy de vocabulario del dominio Comercial (clientes, contratos, lotes).
 * Mismo criterio que lang/es/operaciones.php: las claves de estado nunca se
 * hardcodean en la vista, se resuelven acá contra el valor crudo que
 * viaja como dato (ADR 0013).
 */

return [

    // HU-22 (tarea 33): alta y mantenimiento de clientes con sus contactos.
    // Primer ABM completo del panel — molde de HU-23 a HU-27 y HU-45.
    'clientes' => [
        'creado' => 'El cliente se dio de alta correctamente.',
        'actualizado' => 'Los datos del cliente se actualizaron correctamente.',
        'eliminado' => 'El cliente se dio de baja correctamente.',
        'volver_a_formulario_origen' => 'Volver al formulario anterior',

        // Listado
        'titulo' => 'Clientes',
        'subtitulo' => 'Alta y mantenimiento de clientes con sus contactos.',
        'nuevo' => 'Nuevo cliente',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Razón social o NIT',
        'filtrar' => 'Buscar',
        'limpiar_filtro' => 'Limpiar búsqueda',
        'vacio_titulo' => 'Todavía no hay clientes',
        'vacio_detalle' => 'Los clientes se dan de alta con sus contactos principales. En cuanto se registre el primero, vas a verlo en este listado.',
        'filtro_vacio_titulo' => 'Sin resultados para esta búsqueda',
        'filtro_vacio_detalle' => 'Ningún cliente coincide con el término buscado. Prueba con otra razón social o NIT.',
        'col_razon_social' => 'Razón social',
        'col_nit' => 'NIT',
        'col_contactos' => 'Contactos',
        'sin_nit' => 'Sin NIT',
        'contactos_cantidad' => ':cantidad contactos',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja' => '¿Dar de baja este cliente? Sus contratos y campos no se ven afectados.',
        'paginacion_aria' => 'Paginación de clientes',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        // Formulario (create/edit)
        'titulo_crear' => 'Nuevo cliente',
        'titulo_editar' => 'Editar cliente',
        'subtitulo_form' => 'El cliente se guarda junto con sus contactos en una sola operación.',
        'seccion_datos' => 'Datos del cliente',
        'campos_contador' => ':cantidad campos',
        'campo_razon_social' => 'Razón social',
        'campo_nombre_comercial' => 'Nombre comercial',
        'campo_nombre_comercial_ayuda' => 'Opcional. Solo para persona jurídica — el nombre con el que opera si es distinto de la razón social.',
        'campo_tipo_persona' => 'Tipo de persona',
        'campo_tipo_persona_placeholder' => 'Selecciona un tipo',
        'tipo_persona_opcion' => [
            'fisica' => 'Persona física (unipersonal)',
            'juridica' => 'Persona jurídica (sociedad)',
        ],
        'campo_nit' => 'NIT',
        'campo_nit_ayuda' => 'Opcional. No puede repetirse entre clientes activos.',
        'campo_ubicacion_oficina' => 'Ubicación de la oficina central',
        'campo_ubicacion_oficina_ayuda' => 'Opcional. Dirección de la oficina central del cliente.',
        'campo_logo' => 'Logo del cliente',
        'campo_logo_ayuda' => 'PNG, JPG, WEBP, GIF o SVG, hasta 20 MB. Lo optimizamos automáticamente. Fondo transparente recomendado.',
        'campo_logo_reemplazar' => 'Reemplazar',
        'campo_logo_quitar' => 'Quitar',
        'error_logo_tipo' => 'El logo tiene que ser un archivo PNG, JPG, WEBP, GIF o SVG.',
        'error_logo_tamano' => 'El logo no puede superar los 20 MB.',
        'error_logo_subida' => 'No se pudo subir el archivo. Prueba de nuevo con una imagen más liviana.',
        'error_razon_social_requerida' => 'Ingresa la razón social del cliente.',
        'seccion_contactos' => 'Contactos',
        'contacto_agregar' => 'Agregar contacto',
        'contacto_quitar' => 'Quitar',
        'contacto_tipo' => 'Tipo',
        'contacto_tipo_placeholder' => 'Selecciona un tipo',
        'contacto_tipo_opcion' => [
            'dueno' => 'Dueño',
            'agronomo' => 'Agrónomo',
            'encargado_propiedad' => 'Encargado de la propiedad',
            'gerente_general' => 'Gerente general',
            'finanzas' => 'Finanzas',
            'secretario' => 'Secretario',
            'otro' => 'Otro',
        ],
        'contacto_tipo_otro' => 'Especifica el tipo',
        'contacto_tipo_otro_ayuda' => 'Solo si el tipo es "Otro" — descríbelo en pocas palabras (ej. "Contador externo").',
        'error_contacto_tipo_otro' => 'Especifica el tipo de contacto.',
        'error_contacto_tipo_requerido' => 'Elige el tipo de contacto.',
        'error_contacto_nombre_requerido' => 'Ingresa el nombre del contacto.',
        'contacto_nombre' => 'Nombre Completo',
        'contacto_telefono' => 'Teléfono',
        'contacto_email' => 'Email',
        'contacto_observaciones' => 'Observaciones',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'volver' => 'Volver a clientes',

        // Aside de resumen de contratos/propiedades/campañas, solo en edición.
        'aside_contratos_titulo' => 'Contratos',
        'aside_contratos_total' => 'Total',
        'aside_contratos_vigentes' => 'En ejecución',
        'aside_contratos_vacio_titulo' => 'Sin contratos todavía',
        'aside_contratos_vacio_detalle' => 'Registra el primer contrato de este cliente y va a quedar vinculado automáticamente.',
        'aside_contratos_accion' => 'Nuevo contrato',
        'aside_propiedades_titulo' => 'Propiedades',
        'aside_propiedades_total' => 'Total',
        'aside_propiedades_lotes' => 'Lotes',
        'aside_propiedades_vacio_titulo' => 'Sin propiedades todavía',
        'aside_propiedades_vacio_detalle' => 'Registra la primera propiedad de este cliente para después cargar sus lotes.',
        'aside_propiedades_accion' => 'Nueva propiedad',
        'aside_ordenes_titulo' => 'Aplicación',
        'aside_ordenes_total' => 'Total',
        'aside_ordenes_vigentes' => 'Vigentes',
        'aside_ordenes_vacio_titulo' => 'Sin órdenes todavía',
        'aside_ordenes_vacio_detalle' => 'Las órdenes de aplicación de este cliente van a aparecer acá una vez que tenga un contrato vigente.',
        'aside_ordenes_accion' => 'Nueva orden de aplicación',
    ],

    // Los nombres visibles cambian; las claves siguen siendo las mismas que
    // usa el estado del contrato internamente.
    'contrato' => [
        'estado' => [
            'borrador' => 'En Aprobación',
            'vigente' => 'En Ejecución',
            'finalizado' => 'Ejecutado',
            'cancelado' => 'Cancelado',
            'pausado' => 'Pausado',
        ],
    ],

    // ADR 0018 (tarea x): nivel de terreno entre cliente y campo.
    // Propiedades: un cliente tiene varias propiedades; cada propiedad tiene
    // varios lotes (ADR 0020: eliminó el nivel de campo intermedio).
    'propiedades' => [
        'creado' => 'La propiedad se dio de alta correctamente.',
        'actualizado' => 'Los datos de la propiedad se actualizaron correctamente.',
        'eliminado' => 'La propiedad se dio de baja correctamente.',
        'mapa_guardado' => 'Las coordenadas de la propiedad se guardaron correctamente.',
        'lotes_generados' => ':cantidad lotes generados correctamente. Entra a cada uno para renombrarlo y dibujar su polígono.',
        'volver_a_formulario_origen' => 'Volver al formulario anterior',

        // Listado
        'titulo' => 'Propiedades',
        'subtitulo' => 'Administración de propiedades y sus lotes.',
        'nuevo' => 'Nueva propiedad',
        'filtro_busqueda_placeholder' => 'Nombre, cliente o localidad',
        'filtro_cliente' => 'Cliente',
        'filtro_cliente_placeholder' => 'Todos los clientes',
        'filtro_departamento' => 'Departamento',
        'filtro_departamento_placeholder' => 'Todos los departamentos',
        'vacio_titulo' => 'Todavía no hay propiedades',
        'vacio_detalle' => 'Las propiedades son terrenos de un cliente donde se trabaja. Se dan de alta con sus datos básicos y ubicación. En cuanto se registre la primera, vas a verla aquí con sus lotes.',
        'filtro_vacio_titulo' => 'Sin resultados',
        'filtro_vacio_detalle' => 'Ninguna propiedad coincide con la búsqueda o los filtros aplicados.',
        'col_nombre' => 'Propiedad',
        'col_cliente' => 'Cliente',
        'col_ubicacion' => 'Ubicación',
        'sin_ubicacion' => 'Sin ubicación',
        'col_lotes' => 'Lotes',
        'lotes_cantidad' => ':cantidad lotes',
        'col_hectareas' => 'Hectáreas',
        'hectareas_valor' => ':cantidad ha',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja' => '¿Dar de baja esta propiedad? Sus lotes no se ven afectados.',
        'paginacion_aria' => 'Paginación de propiedades',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        // Formulario (create/edit)
        'titulo_crear' => 'Nueva propiedad',
        'titulo_editar' => 'Editar propiedad',
        'subtitulo_form' => 'Datos principales de la propiedad, sin lotes (se agregan desde la ficha de lotes).',
        'seccion_datos' => 'Datos de la propiedad',
        'campos_contador' => ':cantidad campos',
        'campo_cliente' => 'Cliente',
        'campo_cliente_placeholder' => 'Selecciona un cliente',
        'campo_nombre' => 'Nombre de la propiedad',
        'campo_hectareas' => 'Hectáreas totales',
        'campo_hectareas_ayuda' => 'Superficie total de la hacienda (según título), no la suma de sus lotes — eso es lo que se fumiga y se factura.',
        'campo_departamento' => 'Departamento',
        'campo_departamento_placeholder' => 'Selecciona un departamento',
        'campo_provincia' => 'Provincia',
        'campo_provincia_placeholder' => 'Elige un departamento primero',
        'campo_municipio' => 'Municipio',
        'campo_municipio_placeholder' => 'Elige una provincia primero',
        'campo_localidad' => 'Localidad',
        'campo_localidad_placeholder' => 'Ej. Cuatro Cañadas, Roboré, San Matías',
        'campo_localidad_ayuda' => 'Pueblo o comunidad dentro del municipio elegido — texto libre.',
        'campo_color' => 'Color',
        'campo_color_ayuda' => 'Identifica esta propiedad en listados y mapas — sus lotes heredan el mismo color.',
        'campo_color_cambiar' => 'Cambiar',
        'campo_color_sin_elegir' => 'Sin color elegido',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'volver' => 'Volver a propiedades',
        'error_coordenada_incompleta' => 'Completa latitud y longitud juntas, o deja las dos vacías.',
        'error_geometria_invalida' => 'La geometría tiene que ser un JSON válido con "type": "MultiPolygon" y "coordinates" como arreglo.',
        'error_nombre_requerido' => 'Ingresa el nombre de la propiedad.',

        // Aside de resumen de mapa/lotes/siembra, solo en edición.
        'aside_mapa_titulo' => 'Coordenadas del mapa',
        'aside_mapa_referencia' => 'Punto de referencia',
        'aside_mapa_vertices' => 'Vértices del perímetro',
        'aside_mapa_vacio_titulo' => 'Sin coordenadas todavía',
        'aside_mapa_vacio_detalle' => 'Marca el punto de referencia y dibuja el perímetro de esta propiedad sobre el mapa.',
        'aside_mapa_accion_agregar' => 'Agregar coordenadas',
        'aside_mapa_accion_editar' => 'Editar en el mapa',

        'aside_lotes_titulo' => 'Lotes',
        'aside_lotes_total' => 'Lotes',
        'aside_lotes_hectareas' => 'Hectáreas en lotes',
        'aside_lotes_hectareas_propiedad' => 'Hectáreas totales (declaradas)',
        'aside_lotes_vacio_titulo' => 'Sin lotes todavía',
        'aside_lotes_vacio_detalle' => 'Los lotes de esta propiedad se dan de alta desde su propia ficha.',
        'aside_lotes_accion' => 'Ver lotes',
        'aside_lotes_generar' => 'Crear lotes',

        'aside_siembra_titulo' => 'Siembra actual',
        'aside_siembra_campania' => 'Campaña',
        'aside_siembra_lotes' => 'Lotes sembrados',
        'aside_siembra_lotes_valor' => ':sembrados de :total',
        'aside_siembra_cultivo' => 'Cultivo predominante',
        'aside_siembra_vacio_titulo' => 'Sin siembra registrada',
        'aside_siembra_vacio_detalle' => 'Todavía no se cargó qué se sembró en los lotes de esta propiedad para la campaña vigente.',
        'aside_siembra_accion' => 'Ver siembra',

        // Pantalla de mapa
        'mapa_titulo' => 'Coordenadas — :propiedad',
        'mapa_titulo_seccion' => 'Ubicación en el mapa',
        'mapa_subtitulo' => 'Punto de referencia y perímetro de la propiedad.',
        'mapa_volver' => 'Volver a la propiedad',
        'campo_geometria' => 'Marcador y perímetro',
        'campo_geometria_ayuda' => 'Coloca el marcador y dibuja sobre el mapa satelital los terrenos de la propiedad (puede haber más de uno, ej. terrenos separados).',
        'mapa_barra_aria' => 'Herramientas del mapa',
        'mapa_dibujar' => 'Dibujar/editar terreno',
        'mapa_dibujar_terminar' => 'Terminar terreno',
        'mapa_deshacer' => 'Deshacer',
        'mapa_borrar_ultimo' => 'Borrar el último terreno',
        'mapa_zoom_completo' => 'Ver todo el perímetro',
        'mapa_capa_satelite' => 'Ver satélite',
        'mapa_capa_calles' => 'Ver calles',
        'mapa_marcador_colocar' => 'Colocar marcador',
        'mapa_marcador_sacar' => 'Sacar marcador',
        'mapa_expandir' => 'Expandir',
        'mapa_salir_expandir' => 'Salir de pantalla completa',
        'mapa_buscador_label' => 'Buscar coordenadas',
        'mapa_buscador_placeholder' => 'Lat, long (ej. -17.78, -63.18)',
        'mapa_buscador_error' => 'Coordenadas inválidas — usa el formato "lat, long".',
        'mapa_medida' => 'Superficie dibujada: :hectareas ha',

        // Pantalla "Crear Lotes" (HU-72 reconstruida, 16/9/2026) — sin
        // cultivo/campaña a propósito: eso es siembra, vive en el summary
        // "Siembra actual" / propiedades/siembra, no acá.
        'lotes_generar_titulo' => 'Crear lotes — :propiedad',
        'lotes_generar_subtitulo' => 'Genera varios lotes de una vez, con sus atributos de terreno; después entras a cada uno a renombrarlo y dibujar su polígono.',
        'lotes_generar_volver' => 'Volver a la propiedad',
        'lotes_generar_error' => 'Revisa los datos marcados antes de generar los lotes.',
        'lotes_generar_seccion_destino' => 'Dónde van estos lotes',
        'lotes_generar_cliente' => 'Cliente',
        'lotes_generar_propiedad' => 'Propiedad',
        'lotes_generar_seccion_cuantos' => 'Cuántos y con qué código',
        'lotes_generar_prefijo' => 'Prefijo del código',
        'lotes_generar_prefijo_ayuda' => 'Cada lote nace como prefijo + número correlativo (ej. Lote 6, Lote 7...), a renombrar después. El número sigue desde el último ya usado en esta propiedad con el mismo prefijo.',
        'lotes_generar_cantidad' => 'Cantidad de lotes',
        'lotes_generar_seccion_terreno' => 'Atributos del terreno',
        'lotes_generar_terreno_ayuda' => 'Se cargan una sola vez y se aplican a todos los lotes generados — si alguno necesita algo distinto, se ajusta después desde su propia ficha.',
        'lotes_generar_estado_form' => 'Los lotes se generan al confirmar.',
        'lotes_generar_accion' => 'Crear lotes',
    ],

    // HU-23 (tarea 34): administración de contratos con sus ventanas de
    // aplicación. Segundo ABM del panel — molde de la tarea 33 (clientes)
    // con una máquina de estados encima.
    'contratos' => [
        'creado' => 'El contrato se dio de alta correctamente, en estado borrador.',
        'actualizado' => 'Los datos del contrato se actualizaron correctamente.',
        'estado_cambiado' => 'El estado del contrato se actualizó correctamente.',

        // Listado
        'titulo' => 'Contratos',
        'subtitulo' => 'Administración de contratos con sus lotes y tarifa.',
        'nuevo' => 'Nuevo contrato',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Razón social del cliente',
        'filtro_campania' => 'Campaña',
        'filtro_campania_placeholder' => 'Todas las campañas',
        'filtro_cliente' => 'Cliente',
        'filtro_cliente_placeholder' => 'Todos los clientes',
        'filtro_propiedad' => 'Propiedad',
        'filtro_propiedad_placeholder' => 'Todas las propiedades',
        'filtrar' => 'Buscar',
        'limpiar_filtro' => 'Limpiar búsqueda',
        'vacio_titulo' => 'Todavía no hay contratos',
        'vacio_detalle' => 'Los contratos se crean entre un cliente y una campaña para especificar hectáreas, tarifa y ventanas. En cuanto se dé de alta el primero, vas a verlo en este listado.',
        'filtro_vacio_titulo' => 'Sin resultados para esta búsqueda',
        'filtro_vacio_detalle' => 'Ningún contrato coincide con la búsqueda o los filtros aplicados. Prueba con otro cliente, campaña o propiedad.',
        'col_cliente' => 'Cliente',
        'col_campania' => 'Campaña',
        'col_hectareas' => 'Hectáreas',
        'col_monto_total' => 'Monto total',
        'col_vigencia' => 'Vigencia',
        'col_estado' => 'Estado',
        'editar' => 'Editar',
        'vigencia_con_fin' => ':inicio – :fin',
        'vigencia_sin_fin' => 'Desde :inicio',
        'paginacion_aria' => 'Paginación de contratos',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        // Cambio de estado (listado)
        'accion_aprobar' => 'Aprobar',
        'accion_finalizar' => 'Finalizar',
        'accion_cancelar' => 'Cancelar',
        'accion_pausar' => 'Pausar',
        'accion_reanudar' => 'Reanudar',
        // Las cinco transiciones (tarea "listado-contratos-acciones") tienen
        // modal propio (molecules/confirm-button, mismo patrón que "Abrir
        // campaña"/"Cerrar campaña") en vez del confirm() nativo del
        // navegador — tono por destino: success al estado vigente
        // (aprobar/reanudar), info a finalizado, warning a pausado, danger a
        // cancelado (irreversible desde el panel).
        'confirmar_aprobar_titulo' => 'Aprobar contrato',
        'confirmar_aprobar' => '¿Aprobar este contrato? Pasa a vigente.',
        'confirmar_finalizar_titulo' => 'Finalizar contrato',
        'confirmar_finalizar' => '¿Dar este contrato por finalizado?',
        'confirmar_cancelar_titulo' => 'Cancelar contrato',
        'confirmar_cancelar' => '¿Cancelar este contrato? La baja no se puede deshacer desde el panel.',
        'confirmar_pausar_titulo' => 'Pausar contrato',
        'confirmar_pausar' => '¿Pausar este contrato? Se interrumpe la ejecución sin cancelarlo.',
        'confirmar_reanudar_titulo' => 'Reanudar contrato',
        'confirmar_reanudar' => '¿Reanudar este contrato?',

        // Formulario (create/edit)
        'titulo_crear' => 'Nuevo contrato',
        'titulo_editar' => 'Editar contrato',
        'subtitulo_form' => 'El contrato se guarda junto con sus lotes en una sola operación.',
        'seccion_datos' => 'Datos del contrato',
        'campos_contador' => ':cantidad campos',
        'campo_cliente' => 'Cliente',
        'campo_cliente_placeholder' => 'Selecciona un cliente',
        'crear_cliente' => 'Crear nuevo cliente',
        'crear_cliente_corto' => 'Nuevo',
        'campo_campania' => 'Campaña',
        'campo_campania_placeholder' => 'Selecciona primero un cliente',
        'campo_campania_ayuda' => 'Solo se listan las campañas del cliente elegido (ADR 0015): el contrato es con un cliente y para una campaña suya.',
        'campo_hectareas_contratadas' => 'Hectáreas contratadas',
        'campo_aplicaciones_previstas' => 'Aplicaciones previstas',
        'campo_precio_ha' => 'Precio por hectárea (Bs)',
        'campo_monto_total_ayuda' => 'Se calcula automáticamente: hectáreas × aplicaciones × precio por hectárea.',
        'campo_adelanto_monto' => 'Adelanto Solicitado',
        'campo_fecha_inicio' => 'Fecha de inicio',
        'campo_fecha_fin' => 'Fecha de fin',
        'campo_fecha_fin_ayuda' => 'Opcional. Si no se define, el contrato queda abierto.',

        // Orden de aplicación (antes "Ventanas de aplicación", HU-23 tarea 34,
        // renombrada en tarea "contratos-lotes"). "Día completo" convive con las
        // filas cargadas, nunca un booleano en la base (ADR 0015 punto 5) — el
        // interruptor es puramente de presentación, arranca encendido sin ventanas
        // cargadas y las filas se muestran/ocultan según su estado
        // (resources/js/pages/contratos-form.js).
        'seccion_ventanas' => 'Orden de aplicación',
        'ventana_dia_completo' => 'Día completo',
        'ventana_dia_completo_ayuda' => 'Sin restricción de horario. Apágalo para cargar franjas horarias.',
        'ventana_agregar' => 'Agregar ventana',
        'ventana_quitar' => 'Quitar',
        'ventana_hora_inicio' => 'Desde',
        'ventana_hora_fin' => 'Hasta',

        // Acomodaciones logísticas (HU-74, tarea 90): lo que Agrocom cubre
        // para el equipo de campo durante la ejecución del contrato. Solo se
        // registra el dato — el costeo en Finanzas es alcance de una tarea
        // futura.
        'seccion_logistica' => 'Logística',
        'campo_brinda_alimentacion' => 'Brinda alimentación',
        'campo_brinda_hospedaje' => 'Brinda hospedaje',
        'campo_brinda_combustible' => 'Brinda combustible',
        'campo_observaciones_logistica' => 'Observaciones de logística',
        'campo_observaciones_logistica_placeholder' => 'Detalles adicionales sobre la logística cubierta',

        'estado_form' => 'Los cambios se guardan al confirmar.',

        // Sección de propiedad y lotes (tarea "contratos-lotes"): selección
        // maestro-detalle de propiedades y sus lotes de un cliente.
        'seccion_lotes' => 'Propiedad y lotes',
        'lotes_contador' => ':cantidad lotes',
        'campo_propiedad' => 'Propiedad',
        'campo_propiedad_placeholder' => 'Selecciona una propiedad',
        'campo_propiedad_ayuda' => 'Del cliente ya elegido arriba. Carga una si no figura en la lista.',
        'lote_seleccionar_todos' => 'Seleccionar todos',
        'lote_personalizar_horario' => 'Personalizar horario',
        'lotes_sin_datos' => 'Esta propiedad todavía no tiene lotes cargados.',
        'lotes_quitar' => 'Quitar',
        'crear_propiedad' => 'Crear propiedad',
        'crear_propiedad_corto' => 'Nueva',
        'crear_lote' => 'Crear lote',

        // Modal de lotes por propiedad (tarea "contratos-lotes", rediseño
        // sept/2026): reemplaza al panel lateral con checkboxes siempre
        // visibles — un modal por click de pill/opción del select, con
        // "Guardar selección" recién aplica los cambios al contrato. Tabla
        // (segunda vuelta del rediseño, mismo mes): checkbox + atributos del
        // lote en columnas, en vez de una lista con meta-línea.
        'lotes_modal_ayuda' => 'Marca los lotes que forman parte de este contrato.',
        'lotes_modal_guardar' => 'Guardar selección',

        // Tabla de lotes ya agregados (bajo el select de Propiedad): una
        // columna por dato, "Horario" refleja "Día completo" o el rango
        // cargado — ver contratos-form.js.
        'lotes_col_horario' => 'Horario',
        'lotes_col_acciones' => 'Acciones',

        // Resumen del aside de editar contrato (tarea "resumen de contrato",
        // sept/2026): mismo criterio que el aside de clientes/campañas —
        // vacío con atajo a crear una orden, o dos tarjetas de solo lectura
        // (facturación / aplicación) una vez que hay datos.
        'aside_vacio_titulo' => 'Todavía no hay órdenes de aplicación',
        'aside_vacio_detalle' => 'Este contrato no tiene ninguna orden de aplicación cargada. En cuanto se registre la primera, vas a ver acá el avance de facturación y trabajos.',
        'aside_vacio_accion' => 'Nueva orden de aplicación',
        'aside_facturacion_titulo' => 'Facturación',
        'aside_monto_contratado' => 'Monto contratado (Bs)',
        'aside_monto_facturado' => 'Monto facturado (Bs)',
        'aside_saldo_pendiente' => 'Saldo pendiente (Bs)',
        'aside_aplicacion_titulo' => 'Aplicación',
        'aside_hectareas_contratadas' => 'Hectáreas contratadas',
        'aside_hectareas_aplicadas' => 'Hectáreas aplicadas',
        'aside_trabajos' => 'Trabajos realizados',

        // Errores de validación
        'error_cliente_requerido' => 'Selecciona un cliente.',
        'error_cliente_invalido' => 'El cliente seleccionado no es válido.',
        'error_campania_requerida' => 'Selecciona la campaña del contrato.',
        'error_campania_invalida' => 'La campaña seleccionada no es válida.',
        'error_lotes_requeridos' => 'Selecciona al menos un lote.',
        'error_lote_invalido' => 'Uno de los lotes seleccionados no es válido.',
        'error_lote_horario_incompleto' => 'Completa la hora de inicio y la hora de fin del lote.',
        'error_lote_horario_invalido' => 'La hora de fin del lote tiene que ser posterior a la hora de inicio.',
        'error_hectareas_contratadas_requeridas' => 'Ingresa las hectáreas contratadas.',
        'error_aplicaciones_previstas_requeridas' => 'Ingresa las aplicaciones previstas.',
        'error_precio_ha_requerido' => 'Ingresa el precio por hectárea.',
        'error_fecha_inicio_requerida' => 'Elige la fecha de inicio del contrato.',
        'error_estado_requerido' => 'Elige el estado del contrato.',
        'volver' => 'Volver a contratos',
    ],

    // Tarea 77 (HU-54, etapa 2): ficha propia de un lote — antes solo se
    // podía tocar entrando por su propiedad. Mismo molde de listado/formulario
    // que el resto del panel. Reusa el partial `_lote-fila.blade.php` para el
    // editor de geometría y los datos del lote (mismo copy, código/hectáreas/
    // geometría/restricciones/desnivel/limpieza).
    'lotes' => [
        'creado' => 'El lote se dio de alta correctamente.',
        'actualizado' => 'Los datos del lote se actualizaron correctamente.',
        'eliminado' => 'El lote se dio de baja correctamente.',
        'volver_a_formulario_origen' => 'Volver al formulario anterior',

        // Listado
        'titulo' => 'Lotes',
        'subtitulo' => 'Listado y ficha de lotes, con su perímetro en el mapa.',
        'nuevo' => 'Nuevo lote',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Código del lote',
        'filtro_cliente' => 'Cliente',
        'filtro_propiedad' => 'Propiedad',
        'filtro_todos' => 'Todas',
        'vacio_titulo' => 'Todavía no hay lotes',
        'vacio_detalle' => 'Los lotes se crean al dar de alta una propiedad o desde su ficha. En cuanto se registre el primero, vas a verlo aquí con su mapa de ubicación y perímetro.',
        'filtro_vacio_titulo' => 'Sin resultados para este filtro',
        'filtro_vacio_detalle' => 'Ningún lote coincide con el filtro. Prueba con otro cliente, propiedad o término de búsqueda.',
        'col_codigo' => 'Código',
        'col_propiedad' => 'Propiedad',
        'col_cliente' => 'Cliente',
        'col_hectareas' => 'Hectáreas',
        'hectareas_valor' => ':cantidad ha',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja' => '¿Dar de baja este lote?',
        'paginacion_aria' => 'Paginación de lotes',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        // Formulario (create/edit)
        'titulo_crear' => 'Nuevo lote',
        'titulo_editar' => 'Editar lote',
        'subtitulo_form' => 'Alta o edición de un lote suelto, con su perímetro en el mapa.',
        'seccion_datos' => 'Ubicación',
        'campos_contador' => ':cantidad campos',
        'campo_cliente' => 'Cliente',
        'campo_cliente_placeholder' => 'Todos los clientes',
        'campo_cliente_ayuda' => 'Filtra las propiedades de abajo. No se guarda: el lote hereda el cliente de su propiedad.',
        'campo_propiedad' => 'Propiedad',
        'campo_propiedad_placeholder' => 'Selecciona una propiedad',
        'seccion_lote' => 'Datos del lote',
        'seccion_mapa' => 'Mapa',

        'lote_agregar' => 'Agregar lote',
        'lote_quitar' => 'Quitar',
        'lote_codigo' => 'Código',
        'lote_hectareas' => 'Hectáreas',
        'lote_geometria' => 'Perímetro del lote',
        'lote_geometria_ayuda' => 'Opcional. Dibuja el contorno del lote sobre la imagen satelital con la herramienta de polígono. Se guarda como GeoJSON y es lo que colorea el mapa del tablero.',
        'lote_usar_superficie' => 'Usar como hectáreas',
        'lote_mapa_barra_aria' => 'Acciones del mapa',
        'lote_mapa_dibujar' => 'Dibujar perímetro',
        'lote_mapa_dibujar_terminar' => 'Terminar perímetro',
        'lote_mapa_borrar' => 'Borrar',
        'lote_mapa_deshacer' => 'Deshacer',
        'lote_mapa_centrar' => 'Centrar en el lote',
        'lote_mapa_capa_satelite' => 'Ver capa satelital',
        'lote_mapa_capa_calles' => 'Ver capa de calles',
        'lote_mapa_pantalla_completa' => 'Pantalla completa',
        'lote_mapa_salir_pantalla_completa' => 'Salir de pantalla completa',
        'lote_mapa_medida' => ':dibujadas ha dibujadas',
        'lote_mapa_medida_declaradas' => ':dibujadas ha dibujadas de :declaradas ha declaradas',
        'lote_restricciones' => 'Restricciones',
        'lote_restricciones_placeholder' => 'Cables, viviendas, colmenas, vecinos sensibles',

        'lote_desnivel' => 'Desnivel',
        'lote_desnivel_placeholder' => 'Sin especificar',
        'lote_desnivel_ninguno' => 'Ninguno',
        'lote_desnivel_algunos' => 'Algunos desniveles',
        'lote_desnivel_varios' => 'Varios desniveles',
        'lote_desnivel_empinado' => 'Empinado',
        'lote_limpieza' => 'Limpieza',
        'lote_limpieza_placeholder' => 'Sin especificar',
        'lote_limpieza_limpio' => 'Limpio',
        'lote_limpieza_pocos_obstaculos' => 'Pocos obstáculos',
        'lote_limpieza_algunos_obstaculos' => 'Algunos obstáculos',
        'lote_limpieza_muchos_obstaculos' => 'Muchos obstáculos',
        // Formulario del lote (16/9/2026): switch "¿está limpio?" + grado de
        // obstáculos si no lo está — mismas 4 claves de arriba para el
        // rótulo de cada opción, ver `lotes/_lote-fila.blade.php`.
        'lote_limpio' => '¿Está limpio el terreno?',
        'lote_limpio_si' => 'Sí',
        'lote_limpio_no' => 'No',
        'lote_grado_obstaculos' => 'Grado de obstáculos',
        'lote_grado_obstaculos_placeholder' => 'Sin especificar',

        'estado_form' => 'Los cambios se guardan al confirmar.',
        'volver' => 'Volver a lotes',

        // Aside "Siembra actual" (ficha de un lote, en edición)
        'aside_siembra_titulo' => 'Siembra actual',
        'aside_siembra_campania' => 'Campaña vigente',
        'aside_siembra_cultivo' => 'Cultivo',
        'aside_siembra_cultivo_desconocido' => 'Sin especificar',
        'aside_siembra_vacio_titulo' => 'Sin siembra registrada',
        'aside_siembra_vacio_detalle' => 'Este lote todavía no tiene un cultivo asignado en la campaña vigente.',
        'aside_siembra_accion' => 'Ir a siembra de la propiedad',

        // Errores de validación
        'error_geometria_invalida' => 'La geometría tiene que ser un JSON con "type": "Polygon" y "coordinates" como arreglo.',
        'error_grado_obstaculos_invalido' => 'El grado de obstáculos no es válido.',
        'error_grado_obstaculos_requerido' => 'Elige un grado de obstáculos para este lote.',
        'error_codigo_requerido' => 'Ingresa el código del lote.',
        'error_hectareas_requeridas' => 'Ingresa las hectáreas del lote.',
    ],

    // HU-48 (tarea 71, ADR 0015 punto 4): catálogo de cultivos. Cuarto ABM
    // simple del panel — mismo molde que `lotes`/`bases`, sin sub-entidad.
    'cultivos' => [
        'creado' => 'El cultivo se dio de alta correctamente.',
        'actualizado' => 'Los datos del cultivo se actualizaron correctamente.',
        'eliminado' => 'El cultivo se dio de baja correctamente.',

        // Listado
        'titulo' => 'Cultivos',
        'subtitulo' => 'Catálogo de cultivos disponibles para la siembra por lote y campaña.',
        'nuevo' => 'Nuevo cultivo',
        'filtro_busqueda_placeholder' => 'Buscar por nombre común o científico…',
        'vacio_titulo' => 'Todavía no hay cultivos',
        'vacio_detalle' => 'Los cultivos son el catálogo de opciones disponibles para sembrar en cada lote. En cuanto se registre el primero, vas a verlo aquí y en los formularios de siembra.',
        'filtro_vacio_titulo' => 'Sin resultados para esta búsqueda',
        'filtro_vacio_detalle' => 'Ningún cultivo coincide con los filtros aplicados. Prueba con otro término o quita algún filtro.',
        'filtro_tipo_cultivo' => 'Tipo de cultivo',
        'filtro_ciclo_vida' => 'Ciclo de vida',
        'filtro_todos' => 'Todos',
        'col_nombre' => 'Cultivo',
        'col_tipo' => 'Tipo',
        'col_ciclo_vida' => 'Ciclo de vida',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja' => '¿Dar de baja este cultivo? Las siembras que ya lo tienen cargado no se ven afectadas.',
        'paginacion_aria' => 'Paginación de cultivos',

        'tipo_cultivo_opcion' => [
            'cereal' => 'Cereal',
            'oleaginosa' => 'Oleaginosa',
            'leguminosa' => 'Leguminosa',
            'forrajera' => 'Forrajera',
            'horticola' => 'Hortícola',
            'frutal' => 'Frutal',
            'otro' => 'Otro',
        ],
        'ciclo_vida_opcion' => [
            'anual' => 'Anual',
            'bienal' => 'Bienal',
            'perenne' => 'Perenne',
        ],

        // Formulario (create/edit)
        'titulo_crear' => 'Nuevo cultivo',
        'titulo_editar' => 'Editar cultivo',
        'subtitulo_form' => 'Nombre, clasificación agronómica y disponibilidad.',
        'seccion_datos' => 'Datos del cultivo',
        'seccion_notas' => 'Notas agronómicas',
        'campos_contador' => ':cantidad campos',
        'campo_nombre_comun' => 'Nombre común',
        'campo_nombre_comun_ayuda' => 'Nombre con el que se conoce popularmente a la planta (ej. Maíz, Soya).',
        'campo_nombre_cientifico' => 'Nombre científico',
        'campo_nombre_cientifico_placeholder' => 'Ej. Zea mays',
        'campo_nombre_cientifico_ayuda' => 'Nombre taxonómico oficial de la especie. Opcional.',
        'campo_tipo_cultivo' => 'Tipo de cultivo',
        'campo_tipo_cultivo_placeholder' => 'Selecciona un tipo',
        'campo_ciclo_vida' => 'Ciclo de vida',
        'campo_ciclo_vida_placeholder' => 'Selecciona un ciclo',
        'campo_notas_agronomicas' => 'Sugerencias para quien carga la siembra',
        'campo_notas_agronomicas_placeholder' => 'Ej. mínimo de aplicaciones recomendado por campaña, tipo de calda que tolera…',
        'campo_notas_agronomicas_ayuda' => 'Referencia informativa de un agrónomo — Agrocom no define ni valida la composición del caldo, eso es responsabilidad del cliente.',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'volver' => 'Volver a cultivos',

        // Resumen relacionado (edición, §6.3.1 de guia_pantalla_panel.md):
        // estático por ahora, pendiente de conectar a un contrato de
        // lectura por cultivo.
        'resumen_titulo' => 'Lotes con este cultivo',
        'resumen_detalle' => 'Acá vas a ver cuántos lotes tienen este cultivo sembrado en la campaña vigente.',
        'resumen_accion' => 'Ir a propiedades',

        // Mensajes de los campos obligatorios.
        'error_nombre_comun_requerido' => 'Ingresa el nombre común del cultivo.',
    ],

    // HU-48 (tarea 71, etapa 3, ADR 0015 punto 4): qué se sembró en cada
    // lote del campo, por campaña. Se entra desde la ficha del campo
    // (`campos`, arriba) — sin listado ni ABM propio, es un formulario por
    // campo + campaña elegida.
    'siembra' => [
        'guardado' => 'La siembra se guardó correctamente.',

        'titulo' => 'Siembra — :propiedad',
        'subtitulo' => 'Qué se sembró en cada lote de esta propiedad, por campaña.',
        'volver' => 'Volver a propiedades',

        'sin_campanias' => 'Este cliente todavía no tiene ninguna campaña cargada.',
        'crear_campania' => 'Crear una campaña',

        'campo_campania' => 'Campaña',
        'ver' => 'Ver',

        'seccion_lotes' => 'Lotes',
        'lotes_contador' => ':cantidad lotes',
        'lote_hectareas_valor' => ':cantidad ha',
        'campo_cultivo' => 'Cultivo',
        'campo_cultivo_placeholder' => 'Sin sembrar esta campaña',
        'campo_hectareas_sembradas' => 'Hectáreas sembradas',
        'campo_fecha_siembra' => 'Fecha de siembra',
        'campo_fecha_cosecha_estimada' => 'Cosecha estimada',
        'estado_form' => 'Los cambios se guardan al confirmar.',

        // Mensajes de los campos obligatorios.
        'error_lotes_requeridos' => 'Agrega al menos un lote.',
        'error_lote_id_requerido' => 'Selecciona un lote.',
    ],

    // HU-31 (tarea 45): "como encargado, quiero emitir la factura de un
    // trabajo desde su acta conformada, para cobrar sobre hectáreas ya
    // firmadas" — abre Sprint 9. Sin edición ni baja: una factura emitida es
    // un snapshot inmutable (ver `Aplicacion/EmitirFactura`).
    'facturas' => [
        'creada' => 'La factura se emitió correctamente.',

        // Listado
        'titulo' => 'Facturas',
        'subtitulo' => 'Facturas emitidas desde actas de conformidad ya firmadas.',
        'nueva' => 'Emitir factura',
        'vacio_titulo' => 'Todavía no hay facturas',
        'vacio_detalle' => 'Las facturas se emiten a partir de actas de conformidad ya firmadas. En cuanto se firme la primera acta, vas a poder emitir su factura y verla en este listado.',
        'col_cliente' => 'Cliente',
        'col_acta' => 'Acta',
        'col_hectareas' => 'Hectáreas facturadas',
        'col_precio_ha' => 'Precio/ha',
        'col_monto' => 'Monto',
        'col_fecha_emision' => 'Fecha de emisión',
        'acta_valor' => 'Acta #:id',
        'monto_valor' => 'Bs :monto',
        'precio_ha_valor' => 'Bs :monto',
        'paginacion_aria' => 'Paginación de facturas',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        // Formulario (create)
        'titulo_crear' => 'Emitir factura',
        'subtitulo_form' => 'El monto se calcula automáticamente: hectáreas conformadas del acta × precio por hectárea del contrato.',
        'seccion_datos' => 'Datos de la factura',
        'campos_contador' => ':cantidad campo',
        'campo_acta' => 'Acta conformada',
        'campo_acta_placeholder' => 'Selecciona un acta firmada',
        'campo_acta_opcion' => ':cliente — Acta #:id (:hectareas ha)',
        'sin_actas_disponibles' => 'No hay actas firmadas pendientes de facturar.',
        'estado_form' => 'El monto se calcula al confirmar.',
        'error_acta_requerida' => 'Selecciona un acta conformada.',
        'error_acta_invalida' => 'El acta seleccionada no está disponible para facturar.',
        'volver' => 'Volver a facturas',
    ],

    // HU-32 (tarea 46, reemplazado por HU-52 tarea 75): "como dueño, quiero
    // un reporte comercial de avance por cliente, contrato y campaña, para
    // saber cuánto queda por aplicar y por cobrar" — cierra Sprint 9. Solo
    // lectura, sin exportar en v1 (HU-52 amplía con agrupación y filtros).
    // Las claves 'col_' y 'contrato_valor' de HU-32 se reusan en HU-52
    // para la tabla, en la pestaña de resultados.
    'reportes_comerciales' => [
        'titulo' => 'Informe de avance de contratos',
        'subtitulo' => 'Hectáreas contratadas, aplicadas y pendientes, agrupadas por cultivo y cliente.',
        'sin_datos_titulo' => 'Todavía no hay nada que informar',
        'sin_datos_detalle' => 'Este informe agrupa contratos por cliente y cultivo — hace falta al menos un cliente y un cultivo dados de alta para poder generarlo.',

        // Entrada (pantalla inicial, con formulario de selección obligatoria)
        'entrada' => [
            'cliente' => 'Clientes',
            'cultivo' => 'Cultivos',
            'filtros' => 'Filtros',
            'generar' => 'Generar informe',
            'error_cliente' => 'Selecciona al menos un cliente.',
            'error_cultivo' => 'Selecciona al menos un cultivo.',
        ],

        // Estado vacío
        'estado' => [
            'sin_resultados' => 'Ningún contrato coincide con el filtro aplicado.',
        ],

        // Chips de filtros (carrusel horizontal)
        'chips' => [
            'cliente' => 'Cliente (:cantidad)',
            'cultivo' => 'Cultivo (:cantidad)',
            'campania' => 'Campaña (:cantidad)',
            'rango_fechas' => ':desde – :hasta',
            'fecha_desde' => 'Desde :fecha',
            'fecha_hasta' => 'Hasta :fecha',
            'estado' => 'Estado: :estado',
            'saldo' => 'Saldo: :saldo',
            'incluir_deshabilitados' => 'Incluye deshabilitados',
            'filtros' => 'Filtros',
        ],

        // Filtros (offcanvas)
        'filtros' => [
            'titulo' => 'Filtros avanzados',
            'cliente' => 'Clientes',
            'cultivo' => 'Cultivos',
            'campania' => 'Campañas',
            'campania_sin_cliente' => 'Selecciona clientes para filtrar campañas.',
            'fecha_desde' => 'Desde (fecha)',
            'fecha_hasta' => 'Hasta (fecha)',
            'estado' => 'Estado del contrato',
            'saldo' => 'Saldo',
            'incluir_deshabilitados' => 'Incluir contratos deshabilitados',
            'seleccionar' => 'Seleccionar',
            'aplicar' => 'Aplicar',
            'cancelar' => 'Cancelar',
            'limpiar' => 'Limpiar a valores por defecto',
        ],

        // Pestañas
        'tabs' => [
            'por_cultivo' => 'Por cultivo',
            'por_cliente' => 'Por cliente',
        ],

        // Tabla de contratos (reutiliza algunas claves de HU-32)
        'tabla' => [
            'contrato' => 'Contrato',
            'hectareas_contratadas' => 'Ha. pactadas',
            'hectareas_aplicadas' => 'Ha. aplicadas',
            'hectareas_a_aplicar' => 'A aplicar',
            'contrato_valor' => 'Contrato #:id',
            'total' => 'Total',
        ],
    ],

    // Enumeraciones: saldo del contrato (reutilizable, agregado en HU-52)
    'saldo' => [
        'pendiente' => 'Pendiente',
        'a_aplicar' => 'A aplicar',
        'cumplido' => 'Cumplido',
    ],

    // Nombres de los colores de propiedad.
    'colores' => [
        'rojo' => 'Rojo',
        'naranja' => 'Naranja',
        'ambar' => 'Ámbar',
        'verde_bosque' => 'Verde bosque',
        'verde_azulado' => 'Verde azulado',
        'turquesa' => 'Turquesa',
        'azul' => 'Azul',
        'indigo' => 'Índigo',
        'violeta' => 'Violeta',
        'purpura' => 'Púrpura',
        'frambuesa' => 'Frambuesa',
        'carmin' => 'Carmín',
        'marron' => 'Marrón',
        'pizarra' => 'Pizarra',
        'musgo' => 'Musgo',
        'malva' => 'Malva',
        'caqui' => 'Caqui',
        'salvia' => 'Salvia',
        'acero' => 'Acero',
        'aciano' => 'Aciano',
        'vino' => 'Vino',
        'terracota' => 'Terracota',
        'negro' => 'Negro',
    ],

    // Mensajes de error.
    'errores' => [
        'acta_no_existe' => 'El acta #:id no existe.',
        'acta_no_firmada' => 'El acta #:id debe estar firmada para poder facturarse.',
        'acta_ya_facturada' => 'El acta #:id ya tiene una factura emitida.',
        'contrato_fecha_inicio_pasada' => "El contrato #:id tiene fecha de inicio :fecha, ya pasada — no puede pasar a 'vigente'.",
        'campania_cerrada' => "La campaña ':codigo' está cerrada: no admite nuevas imputaciones.",
        'cliente_nit_duplicado' => "Ya existe un cliente activo con el NIT ':nit'.",
        'cultivo_nombre_duplicado' => "Ya existe un cultivo activo con el nombre ':nombre'.",
        'informe_falta_cliente' => 'El informe de avance de contratos requiere al menos un cliente.',
        'informe_falta_cultivo' => 'El informe de avance de contratos requiere al menos un cultivo.',
        'hectareas_sembradas_superan_lote' => "Las hectáreas sembradas (:sembradas) superan las hectáreas del lote ':codigo' (:hectareas).",
        'lote_ajeno_al_cliente' => "El lote ':codigo' no pertenece a ninguna propiedad del cliente elegido.",
        'lote_con_historial_asociado' => "El lote ':codigo' tiene órdenes de aplicación o trabajos asociados y no se puede eliminar.",
        'lote_codigo_duplicado' => "Ya existe un lote activo con el código ':codigo' para esta propiedad.",
        'lote_codigo_libre_agotado' => 'No se pudo generar un código de lote libre tras :intentos intentos.',
        'propiedad_lotes_agotados' => "La propiedad ':nombre' ya tiene todos sus lotes cubiertos por otros contratos vigentes de esta campaña.",
        'propiedad_con_lotes_asociados' => "La propiedad ':nombre' tiene lotes asociados y no se puede eliminar.",
        'propiedad_nombre_duplicado' => "Ya existe una propiedad activa con el nombre ':nombre' para este cliente.",
        'siembra_duplicada' => "El lote ':codigo' ya tiene una siembra cargada para esta campaña.",
        'contrato_transicion_no_permitida' => "No se puede pasar un contrato de ':desde' a ':hasta'.",
        'provincia_ajena_al_departamento' => 'La provincia seleccionada no pertenece al departamento elegido.',
        'municipio_ajeno_a_la_provincia' => 'El municipio seleccionado no pertenece a la provincia elegida.',
    ],

    // Mensajes de los formularios.
    'validacion' => [
        'tipo_persona_requerido' => 'Selecciona si el cliente es persona física o jurídica.',
        'tipo_persona_invalido' => 'El tipo de persona no es válido.',
        'contactos_requeridos' => 'Agrega al menos un contacto.',
        'contacto_ajeno_al_cliente' => 'Uno de los contactos enviados no pertenece a este cliente.',
        'contacto_tipo_invalido' => 'El tipo de contacto no es válido.',
        'tipo_cultivo_requerido' => 'Selecciona el tipo de cultivo.',
        'tipo_cultivo_invalido' => 'El tipo de cultivo no es válido.',
        'ciclo_vida_requerido' => 'Selecciona el ciclo de vida.',
        'ciclo_vida_invalido' => 'El ciclo de vida no es válido.',
        'propiedad_requerida' => 'Selecciona una propiedad.',
        'propiedad_invalida' => 'La propiedad seleccionada no es válida.',
        'hectareas_mayor_a_cero' => 'Las hectáreas tienen que ser mayores a cero.',
        'departamento_invalido' => 'El departamento seleccionado no es válido.',
        'provincia_invalida' => 'La provincia seleccionada no es válida.',
        'municipio_invalido' => 'El municipio seleccionado no es válido.',
        'color_invalido' => 'Elige un color de la paleta disponible.',
        'lotes_generar_prefijo_requerido' => 'Elige un prefijo para el código de los lotes.',
        'lotes_generar_cantidad_requerida' => 'Indica cuántos lotes generar.',
        'lotes_generar_cantidad_minima' => 'Genera al menos un lote.',
        'lotes_generar_cantidad_maxima' => 'No se pueden generar más de 50 lotes a la vez.',
        'siembra_campania_requerida' => 'Selecciona la campaña.',
        'siembra_hectareas_sembradas_mayor_a_cero' => 'Las hectáreas sembradas tienen que ser mayores a cero.',
        'siembra_hectareas_sembradas_requeridas' => 'Indica las hectáreas sembradas de ese lote.',
        'siembra_cosecha_estimada_invalida' => 'La cosecha estimada no puede ser anterior a la siembra.',
    ],

];
