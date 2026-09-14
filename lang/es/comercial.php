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

        // Listado
        'titulo' => 'Clientes',
        'subtitulo' => 'Alta y mantenimiento de clientes con sus contactos.',
        'nuevo' => 'Nuevo cliente',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Razón social o NIT',
        'filtrar' => 'Buscar',
        'limpiar_filtro' => 'Limpiar búsqueda',
        'vacio' => 'Todavía no se dio de alta ningún cliente.',
        'filtro_vacio' => 'Ningún cliente coincide con la búsqueda.',
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
        'campo_tipo_persona' => 'Tipo de persona',
        'campo_tipo_persona_placeholder' => 'Seleccioná un tipo',
        'tipo_persona_opcion' => [
            'fisica' => 'Persona física (unipersonal)',
            'juridica' => 'Persona jurídica (sociedad)',
        ],
        'campo_nit' => 'NIT',
        'campo_nit_ayuda' => 'Opcional. No puede repetirse entre clientes activos.',
        'campo_ubicacion_oficina' => 'Ubicación de la oficina central',
        'campo_ubicacion_oficina_ayuda' => 'Opcional. Dirección de la oficina central del cliente.',
        'campo_logo' => 'Logo del cliente',
        'campo_logo_ayuda' => 'PNG o SVG, fondo transparente recomendado.',
        'campo_logo_reemplazar' => 'Reemplazar',
        'campo_logo_quitar' => 'Quitar',
        'error_logo_tipo' => 'El logo tiene que ser un archivo PNG o SVG.',
        'error_logo_tamano' => 'El logo no puede superar los 2 MB.',
        'seccion_contactos' => 'Contactos',
        'contacto_agregar' => 'Agregar contacto',
        'contacto_quitar' => 'Quitar',
        'contacto_tipo' => 'Tipo',
        'contacto_tipo_placeholder' => 'Seleccioná un tipo',
        'contacto_tipo_opcion' => [
            'dueno' => 'Dueño',
            'agronomo' => 'Agrónomo',
            'encargado_propiedad' => 'Encargado de la propiedad',
            'otro' => 'Otro',
            'gerente_general' => 'Gerente general',
            'finanzas' => 'Finanzas',
            'secretario' => 'Secretario',
        ],
        'contacto_nombre' => 'Nombre',
        'contacto_telefono' => 'Teléfono',
        'contacto_email' => 'Email',
        'contacto_observaciones' => 'Observaciones',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'volver' => 'Volver a clientes',
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
    // varios campos. Mismo molde que campos/clientes.
    'propiedades' => [
        'creado' => 'La propiedad se dio de alta correctamente.',
        'actualizado' => 'Los datos de la propiedad se actualizaron correctamente.',
        'eliminado' => 'La propiedad se dio de baja correctamente.',

        // Listado
        'titulo' => 'Propiedades',
        'subtitulo' => 'Administración de propiedades, con sus campos y lotes.',
        'nuevo' => 'Nueva propiedad',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Nombre de la propiedad o cliente',
        'filtrar' => 'Buscar',
        'limpiar_filtro' => 'Limpiar búsqueda',
        'vacio' => 'Todavía no se dio de alta ninguna propiedad.',
        'filtro_vacio' => 'Ninguna propiedad coincide con la búsqueda.',
        'col_nombre' => 'Propiedad',
        'col_cliente' => 'Cliente',
        'col_campos' => 'Campos',
        'campos_cantidad' => ':cantidad campos',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja' => '¿Dar de baja esta propiedad? Sus campos y lotes no se ven afectados.',
        'paginacion_aria' => 'Paginación de propiedades',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        // Formulario (create/edit)
        'titulo_crear' => 'Nueva propiedad',
        'titulo_editar' => 'Editar propiedad',
        'subtitulo_form' => 'Datos principales de la propiedad.',
        'seccion_datos' => 'Datos de la propiedad',
        'campos_contador' => ':cantidad campos',
        'campo_cliente' => 'Cliente',
        'campo_cliente_placeholder' => 'Seleccioná un cliente',
        'campo_nombre' => 'Nombre de la propiedad',
        'campo_ubicacion' => 'Ubicación',
        'campo_ubicacion_placeholder' => 'Ej. Cuatro Cañadas, Roboré, San Matías',
        'campo_ubicacion_ayuda' => 'Localidad física: departamento, provincia, municipio o pueblo.',
        'campo_departamento' => 'Departamento',
        'campo_municipio' => 'Municipio',
        'campo_localidad' => 'Localidad',
        'campo_latitud' => 'Latitud',
        'campo_longitud' => 'Longitud',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'volver' => 'Volver a propiedades',
        'error_coordenada_incompleta' => 'Completá latitud y longitud juntas, o dejá las dos vacías.',
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
        'subtitulo' => 'Administración de contratos con sus ventanas de aplicación y tarifa.',
        'nuevo' => 'Nuevo contrato',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Razón social del cliente',
        'filtro_campania' => 'Campaña',
        'filtro_campania_placeholder' => 'Todas las campañas',
        'filtrar' => 'Buscar',
        'limpiar_filtro' => 'Limpiar búsqueda',
        'vacio' => 'Todavía no se dio de alta ningún contrato.',
        'filtro_vacio' => 'Ningún contrato coincide con la búsqueda.',
        'col_cliente' => 'Cliente',
        'col_campania' => 'Campaña',
        'col_hectareas' => 'Hectáreas',
        'col_monto_total' => 'Monto total',
        'col_vigencia' => 'Vigencia',
        'col_ventanas' => 'Ventanas',
        'col_estado' => 'Estado',
        'editar' => 'Editar',
        'vigencia_con_fin' => ':inicio – :fin',
        'vigencia_sin_fin' => 'Desde :inicio',
        'paginacion_aria' => 'Paginación de contratos',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        // Cambio de estado (listado)
        'accion_activar' => 'Activar',
        'accion_finalizar' => 'Finalizar',
        'accion_cancelar' => 'Cancelar',
        'accion_pausar' => 'Pausar',
        'accion_reanudar' => 'Reanudar',
        'confirmar_activar' => '¿Pasar este contrato a vigente?',
        'confirmar_finalizar' => '¿Dar este contrato por finalizado?',
        'confirmar_cancelar' => '¿Cancelar este contrato? La baja no se puede deshacer desde el panel.',
        'confirmar_pausar' => '¿Pausar este contrato? Se interrumpe la ejecución sin cancelarlo.',
        'confirmar_reanudar' => '¿Reanudar este contrato?',

        // Formulario (create/edit)
        'titulo_crear' => 'Nuevo contrato',
        'titulo_editar' => 'Editar contrato',
        'subtitulo_form' => 'El contrato se guarda junto con sus ventanas de aplicación en una sola operación.',
        'seccion_datos' => 'Datos del contrato',
        'campos_contador' => ':cantidad campos',
        'campo_cliente' => 'Cliente',
        'campo_cliente_placeholder' => 'Seleccioná un cliente',
        'campo_campania' => 'Campaña',
        'campo_campania_placeholder' => 'Seleccioná primero un cliente',
        'campo_campania_ayuda' => 'Solo se listan las campañas del cliente elegido (ADR 0015): el contrato es con un cliente y para una campaña suya.',
        'campo_hectareas_contratadas' => 'Hectáreas contratadas',
        'campo_aplicaciones_previstas' => 'Aplicaciones previstas',
        'campo_precio_ha' => 'Precio por hectárea (Bs)',
        'campo_monto_total_ayuda' => 'Se calcula automáticamente: hectáreas × aplicaciones × precio por hectárea.',
        'campo_adelanto_monto' => 'Adelanto Solicitado',
        'campo_fecha_inicio' => 'Fecha de inicio',
        'campo_fecha_fin' => 'Fecha de fin',
        'campo_fecha_fin_ayuda' => 'Opcional. Si no se define, el contrato queda abierto.',

        // Ventanas de aplicación (HU-47, tarea 70): "Día completo" convive con
        // las filas cargadas, nunca un booleano en la base (ADR 0015 punto 5)
        // — el interruptor es puramente de presentación, arranca encendido
        // sin ventanas cargadas y las filas se muestran/ocultan según su
        // estado (resources/js/pages/contratos-form.js).
        'seccion_ventanas' => 'Ventanas de aplicación',
        'ventana_dia_completo' => 'Día completo',
        'ventana_dia_completo_ayuda' => 'Sin restricción de horario. Apagalo para cargar franjas horarias.',
        'ventana_agregar' => 'Agregar ventana',
        'ventana_quitar' => 'Quitar',
        'ventana_hora_inicio' => 'Desde',
        'ventana_hora_fin' => 'Hasta',
        'ventana_rango' => ':inicio – :fin',

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

        // Errores de validación
        'error_cliente_requerido' => 'Seleccioná un cliente.',
        'error_cliente_invalido' => 'El cliente seleccionado no es válido.',
        'error_campania_requerida' => 'Seleccioná la campaña del contrato.',
        'error_campania_invalida' => 'La campaña seleccionada no es válida.',
        'error_ventana_ajena' => 'Una de las ventanas enviadas no pertenece a este contrato.',
        'error_ventana_incompleta' => 'Completá la hora de inicio y la hora de fin de la ventana.',
        'error_ventana_horas' => 'La hora de fin tiene que ser posterior a la hora de inicio.',
        'volver' => 'Volver a contratos',
    ],

    // HU-24 (tarea 35; actualizado ADR 0018): administración de campos con sus
    // lotes. Tercer ABM del panel — mismo molde que clientes (tarea 33): un
    // campo se crea/edita con sus lotes en la misma operación, sin pantalla
    // propia para lotes. ADR 0018: ahora el campo cuelga de una propiedad, no
    // directo de un cliente.
    'campos' => [
        'creado' => 'El campo se dio de alta correctamente.',
        'actualizado' => 'Los datos del campo se actualizaron correctamente.',
        'eliminado' => 'El campo se dio de baja correctamente.',

        // Listado
        'titulo' => 'Campos',
        'subtitulo' => 'Administración de campos y sus lotes, agrupados por propiedad.',
        'nuevo' => 'Nuevo campo',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Nombre del campo',
        'filtrar' => 'Buscar',
        'limpiar_filtro' => 'Limpiar búsqueda',
        'vacio' => 'Todavía no se dio de alta ningún campo.',
        'filtro_vacio' => 'Ningún campo coincide con la búsqueda.',
        'col_nombre' => 'Campo',
        'col_cliente' => 'Cliente',
        'col_lotes' => 'Lotes',
        'col_hectareas' => 'Hectáreas',
        'lotes_cantidad' => ':cantidad lotes',
        'hectareas_valor' => ':cantidad ha',
        'siembra' => 'Siembra',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja' => '¿Dar de baja este campo? Sus lotes no se ven afectados.',
        'paginacion_aria' => 'Paginación de campos',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        // Formulario (create/edit)
        'titulo_crear' => 'Nuevo campo',
        'titulo_editar' => 'Editar campo',
        'subtitulo_form' => 'El campo se guarda junto con sus lotes en una sola operación.',
        'seccion_datos' => 'Datos del campo',
        'campos_contador' => ':cantidad campos',
        'campo_cliente' => 'Cliente',
        'campo_cliente_placeholder' => 'Seleccioná un cliente',
        'campo_propiedad' => 'Propiedad',
        'campo_propiedad_placeholder' => 'Seleccioná una propiedad',
        'campo_nombre' => 'Nombre',

        // Generador de lotes (alta masiva, HU-72, tarea 88)
        'seccion_generador' => 'Generar lotes',
        'generador_cantidad' => 'Cantidad de lotes',
        'generador_hectareas' => 'Hectáreas por lote',
        'generador_cultivo' => 'Cultivo por defecto',
        'generador_cultivo_placeholder' => 'Sin sembrar',
        'generador_campania' => 'Campaña',
        'generador_campania_placeholder' => 'Seleccioná una campaña',
        'generador_generar' => 'Generar lotes',
        'generador_ayuda' => 'Genera lotes provisorios ("Lote 1".."Lote N") con la misma superficie. Renombralos y dibujá su perímetro después, desde la ficha de cada lote.',

        'seccion_lotes' => 'Lotes',
        'lote_agregar' => 'Agregar lote',
        'lote_quitar' => 'Quitar',
        'lote_codigo' => 'Código',
        'lote_hectareas' => 'Hectáreas',
        'lote_geometria' => 'Perímetro del lote',
        'lote_geometria_ayuda' => 'Opcional. Dibujá el contorno del lote sobre la imagen satelital con la herramienta de polígono. Se guarda como GeoJSON y es lo que colorea el mapa del tablero.',
        'lote_usar_superficie' => 'Usar como hectáreas',
        'lote_mapa_barra_aria' => 'Acciones del mapa',
        'lote_mapa_dibujar' => 'Dibujar perímetro',
        'lote_mapa_editar_vertices' => 'Editar vértices',
        'lote_mapa_mover' => 'Mover',
        'lote_mapa_borrar' => 'Borrar',
        'lote_mapa_deshacer' => 'Deshacer',
        'lote_mapa_centrar' => 'Centrar en el lote',
        'lote_mapa_capa_satelite' => 'Ver capa satelital',
        'lote_mapa_capa_calles' => 'Ver capa de calles',
        'lote_mapa_pantalla_completa' => 'Pantalla completa',
        'lote_mapa_salir_pantalla_completa' => 'Salir de pantalla completa',
        // Plantillas con placeholders `:dibujadas`/`:declaradas`: el JS del
        // editor (organisms/lote-mapa-editor.js) los reemplaza por los
        // números ya calculados en el navegador — nunca arma la frase él
        // mismo (ADR 0013, el JS no sabe de idiomas).
        'lote_mapa_medida' => ':dibujadas ha dibujadas',
        'lote_mapa_medida_declaradas' => ':dibujadas ha dibujadas de :declaradas ha declaradas',
        'lote_restricciones' => 'Restricciones',
        'lote_restricciones_placeholder' => 'Cables, viviendas, colmenas, vecinos sensibles',

        // Desnivel y limpieza del lote: catálogos cerrados aparte de
        // `restricciones` (texto libre).
        'lote_desnivel' => 'Desnivel',
        'lote_desnivel_placeholder' => 'Sin especificar',
        'lote_desnivel_ninguno' => 'Ninguno',
        'lote_desnivel_algunos' => 'Algunos desniveles',
        'lote_desnivel_varios' => 'Varios desniveles',
        'lote_desnivel_empinado' => 'Empinado',
        'lote_limpieza' => 'Limpieza',
        'lote_limpieza_placeholder' => 'Sin especificar',
        'lote_limpieza_limpio' => 'Limpio',
        'lote_limpieza_algunos_obstaculos' => 'Algunos obstáculos',
        'lote_limpieza_muchos_obstaculos' => 'Muchos obstáculos',

        'estado_form' => 'Los cambios se guardan al confirmar.',
        'volver' => 'Volver a campos',

        // Errores de validación
        'error_geometria_invalida' => 'La geometría tiene que ser un JSON con "type": "Polygon" y "coordinates" como arreglo.',
    ],

    // Tarea 77 (HU-54, etapa 2): ficha propia de un lote — antes solo se
    // podía tocar entrando por su propiedad (`campos`, arriba). Mismo molde
    // de listado/formulario que el resto del panel; los rótulos
    // `lote_codigo`/`lote_hectareas`/`lote_geometria*`/`lote_restricciones*`/
    // `lote_desnivel*`/`lote_limpieza*` del bloque `campos` de arriba se
    // reusan tal cual (mismo copy, misma fila `_lote-fila.blade.php`
    // compartida por las dos pantallas).
    'lotes' => [
        'creado' => 'El lote se dio de alta correctamente.',
        'actualizado' => 'Los datos del lote se actualizaron correctamente.',
        'eliminado' => 'El lote se dio de baja correctamente.',

        // Listado
        'titulo' => 'Lotes',
        'subtitulo' => 'Listado y ficha de lotes, con su perímetro en el mapa.',
        'nuevo' => 'Nuevo lote',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Código del lote',
        'filtro_cliente' => 'Cliente',
        'filtro_propiedad' => 'Propiedad',
        'filtro_todos' => 'Todas',
        'filtrar' => 'Buscar',
        'limpiar_filtro' => 'Limpiar filtros',
        'vacio' => 'Todavía no se dio de alta ningún lote.',
        'filtro_vacio' => 'Ningún lote coincide con el filtro.',
        'col_codigo' => 'Código',
        'col_propiedad' => 'Campo',
        'col_cliente' => 'Cliente',
        'col_hectareas' => 'Hectáreas',
        'hectareas_valor' => ':cantidad ha',
        'filtro_campo' => 'Campo',
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
        'campo_cliente_ayuda' => 'Filtra las propiedades y campos de abajo. No se guarda: el lote hereda el cliente de su propiedad.',
        'campo_propiedad' => 'Propiedad',
        'campo_propiedad_placeholder' => 'Seleccioná una propiedad',
        'campo_campo' => 'Campo',
        'campo_campo_placeholder' => 'Seleccioná un campo',
        'seccion_lote' => 'Datos del lote',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'volver' => 'Volver a lotes',

        // Errores de validación
        'error_geometria_invalida' => 'La geometría tiene que ser un JSON con "type": "Polygon" y "coordinates" como arreglo.',
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
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Nombre del cultivo',
        'filtrar' => 'Buscar',
        'limpiar_filtro' => 'Limpiar búsqueda',
        'vacio' => 'Todavía no se dio de alta ningún cultivo.',
        'filtro_vacio' => 'Ningún cultivo coincide con la búsqueda.',
        'col_nombre' => 'Cultivo',
        'col_estado' => 'Estado',
        'estado_activo' => 'Activo',
        'estado_inactivo' => 'Inactivo',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja' => '¿Dar de baja este cultivo? Las siembras que ya lo tienen cargado no se ven afectadas.',
        'paginacion_aria' => 'Paginación de cultivos',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        // Formulario (create/edit)
        'titulo_crear' => 'Nuevo cultivo',
        'titulo_editar' => 'Editar cultivo',
        'subtitulo_form' => 'Catálogo simple: nombre y disponibilidad.',
        'seccion_datos' => 'Datos del cultivo',
        'campos_contador' => ':cantidad campos',
        'campo_nombre' => 'Nombre',
        'campo_activo' => 'Activo',
        'campo_activo_ayuda' => 'Un cultivo inactivo deja de ofrecerse para nuevas siembras, sin afectar las ya cargadas.',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'volver' => 'Volver a cultivos',
    ],

    // HU-48 (tarea 71, etapa 3, ADR 0015 punto 4): qué se sembró en cada
    // lote del campo, por campaña. Se entra desde la ficha del campo
    // (`campos`, arriba) — sin listado ni ABM propio, es un formulario por
    // campo + campaña elegida.
    'siembra' => [
        'guardado' => 'La siembra se guardó correctamente.',

        'titulo' => 'Siembra — :campo',
        'subtitulo' => 'Qué se sembró en cada lote de este campo, por campaña.',
        'volver' => 'Volver a campos',

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
        'vacio' => 'Todavía no se emitió ninguna factura.',
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
        'campo_acta_placeholder' => 'Seleccioná un acta firmada',
        'campo_acta_opcion' => ':cliente — Acta #:id (:hectareas ha)',
        'sin_actas_disponibles' => 'No hay actas firmadas pendientes de facturar.',
        'estado_form' => 'El monto se calcula al confirmar.',
        'error_acta_requerida' => 'Seleccioná un acta conformada.',
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

        // Entrada (pantalla inicial, con formulario de selección obligatoria)
        'entrada' => [
            'cliente' => 'Clientes',
            'cultivo' => 'Cultivos',
            'filtros' => 'Filtros',
            'generar' => 'Generar informe',
            'error_cliente' => 'Seleccioná al menos un cliente.',
            'error_cultivo' => 'Seleccioná al menos un cultivo.',
        ],

        // Estados vacíos
        'estado' => [
            'primera_visita' => 'Seleccioná clientes y cultivos para generar el informe.',
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
            'campania_opcion' => ':codigo — :cliente',
            'campania_sin_cliente' => 'Seleccioná clientes para filtrar campañas.',
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

];
