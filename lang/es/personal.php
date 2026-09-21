<?php

/*
 * Copy de vocabulario del dominio Personal (bases, personas operativas).
 * Mismo criterio que lang/es/comercial.php: las claves de estado nunca se
 * hardcodean en la vista, se resuelven acá contra el valor crudo que viaja
 * como dato (ADR 0013).
 */

return [

    // Clasificación operativa de una persona (RolOperativoPersona, espec
    // §4.2) — compartido por el listado y el formulario de personas.
    'roles' => [
        'piloto' => 'Piloto',
        'auxiliar' => 'Auxiliar',
        'jefe_campo' => 'Jefe de campo',
        'encargado_operaciones' => 'Encargado de operaciones',
        'dueno' => 'Dueño',
    ],

    // Estado descriptivo de un equipo de trabajo (EstadoEquipoTrabajo, tarea
    // 72) — campo libre, sin máquina de estados (ver docblock del enum).
    'estado' => [
        'activo' => 'Activo',
        'inactivo' => 'Inactivo',
    ],

    // Rol de una persona DENTRO de un equipo de trabajo (RolEquipo, tarea 72).
    'rol_equipo' => [
        'piloto' => 'Piloto',
        'auxiliar' => 'Ayudante',
    ],

    // Tipo de recurso asignable a un equipo de trabajo (RecursoTipoEquipo,
    // tarea 72; `bateria` agregada por la tarea "cuadrillas-estadias",
    // 19/9/2026).
    'recurso_tipo' => [
        'dron' => 'Dron',
        'vehiculo' => 'Vehículo',
        'generador' => 'Generador',
        'bateria' => 'Batería',
    ],

    // HU-26 (tarea 37): alta y mantenimiento de bases operativas. Catálogo
    // simple, sin sub-entidad — mismo molde que operaciones.drones.
    'bases' => [
        'creado' => 'La base se dio de alta correctamente.',
        'actualizado' => 'Los datos de la base se actualizaron correctamente.',
        'eliminado' => 'La base se dio de baja correctamente.',

        // Listado
        'titulo' => 'Bases',
        'subtitulo' => 'Bases operativas registradas, con su ubicación.',
        'nueva' => 'Nueva base',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Nombre o ubicación…',
        'filtrar' => 'Filtrar',
        'limpiar_filtro' => 'Limpiar filtro',
        'filtro_vacio_titulo' => 'Sin resultados para esta búsqueda',
        'filtro_vacio_detalle' => 'Ninguna base coincide con el término buscado. Prueba con otro nombre o ubicación.',
        'vacio_titulo' => 'Todavía no hay bases registradas',
        'vacio_detalle' => 'Las bases operativas se registran como infraestructura permanente de Agrocom. Se crea una nueva desde el formulario de alta arriba.',
        'col_nombre' => 'Nombre',
        'col_ubicacion' => 'Ubicación',
        'sin_ubicacion' => '—',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_eliminar_titulo' => 'Eliminar base',
        'confirmar_baja' => '¿Confirmas la baja de esta base?',
        'paginacion_aria' => 'Paginación de bases',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        // Formulario
        'titulo_crear' => 'Nueva base',
        'titulo_editar' => 'Editar base',
        'subtitulo_form' => 'Nombre y ubicación de la base.',
        'seccion_datos' => 'Datos de la base',
        'campos_contador' => ':cantidad campos',
        'campo_nombre' => 'Nombre',
        'campo_ubicacion' => 'Ubicación',
        'campo_latitud' => 'Latitud',
        'campo_latitud_ayuda' => 'En grados decimales, entre -90 y 90. Se completa junto con la longitud.',
        'campo_longitud' => 'Longitud',
        'campo_longitud_ayuda' => 'En grados decimales, entre -180 y 180. Se completa junto con la latitud.',
        'error_coordenada_incompleta' => 'Completa latitud y longitud juntas, o deja las dos vacías.',
        'error_nombre_requerido' => 'Ingresa el nombre de la base.',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'volver' => 'Volver a bases',

        // Resumen relacionado
        'aside_personas_titulo' => 'Personas',
        'aside_personas_total' => 'Total',
        'aside_personas_pilotos' => 'Pilotos',
        'aside_personas_vacio_titulo' => 'Sin personas asignadas',
        'aside_personas_vacio_detalle' => 'Da de alta una persona y asígnale esta base para que aparezca aquí.',
        'aside_personas_accion' => 'Nueva persona',
        'aside_cuadrillas_titulo' => 'Cuadrillas',
        'aside_cuadrillas_total' => 'Total',
        'aside_cuadrillas_activas' => 'Activas',
        'aside_cuadrillas_vacio_titulo' => 'Sin cuadrillas',
        'aside_cuadrillas_vacio_detalle' => 'Todavía no hay cuadrillas que salgan de esta base.',
        'aside_cuadrillas_accion_ver' => 'Ver cuadrillas',
        'aside_cuadrillas_accion_crear' => 'Nueva cuadrilla',
        'aside_equipamiento_titulo' => 'Equipamiento',
        'aside_equipamiento_vehiculos' => 'Vehículos',
        'aside_equipamiento_generadores' => 'Generadores',
        'aside_equipamiento_baterias' => 'Baterías',
        'aside_equipamiento_vacio_titulo' => 'Sin equipamiento asignado',
        'aside_equipamiento_vacio_detalle' => 'Elige esta base al registrar un vehículo, un generador o una batería para que aparezca aquí.',
        'aside_stock_titulo' => 'Stock',
        'aside_stock_repuestos' => 'Repuestos',
        'aside_stock_bajo_minimo' => 'Bajo el mínimo',
        'aside_stock_vacio_titulo' => 'Sin stock registrado',
        'aside_stock_vacio_detalle' => 'Todavía no hay repuestos con existencias en esta base. Se cargan al registrar un movimiento de stock.',
        'aside_stock_accion_ver' => 'Ver stock',
        'aside_stock_accion_registrar' => 'Registrar movimiento',
    ],

    // HU-26 (tarea 37): alta y mantenimiento de personas operativas, con su
    // rol y tarifa por hectárea. `base_id` es opcional — mismo criterio de
    // select nativo que `cliente_id` en comercial.campos.
    'personas' => [
        'creado' => 'La persona se dio de alta correctamente.',
        'actualizado' => 'Los datos de la persona se actualizaron correctamente.',
        'eliminado' => 'La persona se dio de baja correctamente.',

        // Listado
        'titulo' => 'Personas',
        'subtitulo' => 'Personas de campo registradas, con su puesto y tarifa por hectárea.',
        'nueva' => 'Nueva persona',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Nombre o cédula…',
        'filtrar' => 'Filtrar',
        'limpiar_filtro' => 'Limpiar filtro',
        'filtro_vacio_titulo' => 'Sin resultados para esta búsqueda',
        'filtro_vacio_detalle' => 'Ninguna persona coincide con el nombre o la cédula buscados. Prueba con otro dato.',
        'vacio_titulo' => 'Todavía no hay personas registradas',
        'vacio_detalle' => 'Las personas operativas se registran con sus datos personales, sus referencias y su puesto (piloto, auxiliar, jefe de campo). Se da de alta una nueva desde el formulario arriba.',
        'col_nombre' => 'Nombre',
        'col_rol' => 'Puesto',
        'col_base' => 'Base',
        'col_tarifa' => 'Tarifa/ha',
        'sin_base' => 'Sin base asignada',
        'sin_tarifa' => '—',
        'tarifa_valor' => 'Bs :monto',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_eliminar_titulo' => 'Eliminar persona',
        'confirmar_baja' => '¿Confirmas la baja de esta persona?',
        'paginacion_aria' => 'Paginación de personas',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        // HU-58 (tarea 81): enlace desde la fila del listado a la ficha de
        // desempeño — gateado por `personal.persona.desempenio`, permiso
        // aparte de `.ver` (información sensible sobre la persona).
        'desempenio' => [
            'ver' => 'Desempeño',
        ],

        // Formulario
        'titulo_crear' => 'Nueva persona',
        'titulo_editar' => 'Editar persona',
        'subtitulo_form' => 'Datos personales, datos de referencia y trabajo en campo de la persona.',
        'seccion_datos' => 'Datos personales',
        'seccion_referencia' => 'Datos de referencia',
        'seccion_trabajo' => 'Trabajo en campo',
        'campos_contador' => ':cantidad campos',
        'campo_nombres' => 'Nombres',
        'campo_apellido_paterno' => 'Apellido paterno',
        'campo_apellido_materno' => 'Apellido materno',
        'campo_ci' => 'Cédula de identidad',
        'campo_ci_ayuda' => 'Con complemento y extensión si los tiene, por ejemplo 1234567-1A SC.',
        'campo_celular' => 'Celular',
        'campo_correo' => 'Correo',
        'campo_direccion' => 'Dirección de vivienda',
        'campo_direccion_ayuda' => 'Calle, número, barrio y ciudad: dónde ubicar a la persona si hace falta.',
        'campo_rol' => 'Puesto',
        'campo_rol_placeholder' => 'Selecciona un puesto',
        'campo_rol_ayuda' => 'Lo que hace en campo. No da acceso al sistema: eso lo define el usuario vinculado a la persona.',
        'campo_base' => 'Base',
        'campo_base_placeholder' => 'Sin base asignada',
        'campo_tarifa' => 'Tarifa por hectárea',
        'campo_tarifa_ayuda' => 'Se usa para calcular el devengo de cada sesión validada. Cambiarla no altera los devengos ya generados.',
        'campo_activo' => 'Persona activa',
        'campo_activo_ayuda' => 'Una persona inactiva no puede asignarse a sesiones nuevas.',
        'error_nombres_requerido' => 'Ingresa los nombres de la persona.',
        'error_apellido_paterno_requerido' => 'Ingresa el apellido paterno.',
        'error_ci_requerido' => 'Ingresa la cédula de identidad.',
        'error_ci_formato' => 'La cédula lleva solo números, con complemento y extensión opcionales (1234567-1A SC).',
        'error_ci_repetido' => 'Ya hay una persona registrada con esa cédula.',
        'error_celular_requerido' => 'Ingresa un celular de contacto.',
        'error_celular_formato' => 'El celular lleva solo números, entre 7 y 20 dígitos.',
        'error_correo_formato' => 'Ingresa un correo válido.',
        'error_rol_requerido' => 'Elige el puesto de la persona.',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'volver' => 'Volver a personas',
        'volver_a_formulario_origen' => 'Volver al formulario de origen',

        // Resumen relacionado
        'aside_cuadrillas_titulo' => 'Cuadrillas',
        'aside_cuadrillas_vigentes' => 'Vigentes',
        'aside_cuadrillas_historial' => 'En total',
        'aside_cuadrillas_actual' => 'Actual',
        'aside_cuadrillas_vacio_titulo' => 'Sin cuadrillas',
        'aside_cuadrillas_vacio_detalle' => 'Esta persona todavía no integra ninguna cuadrilla. Se asigna desde la ficha de una cuadrilla.',
        'aside_cuadrillas_accion_ver' => 'Ver cuadrillas',
        'aside_usuario_titulo' => 'Acceso al sistema',
        'aside_usuario_usuario' => 'Usuario',
        'aside_usuario_estado' => 'Estado',
        'aside_usuario_activo' => 'Activo',
        'aside_usuario_bloqueado' => 'Bloqueado',
        'aside_usuario_roles' => 'Roles',
        'aside_usuario_vacio_titulo' => 'Sin acceso al sistema',
        'aside_usuario_vacio_detalle' => 'Esta persona no tiene un usuario vinculado. Crea uno para que pueda ingresar.',
        'aside_usuario_accion_ver' => 'Ver usuario',
        'aside_usuario_accion_crear' => 'Nuevo usuario',
        'aside_sesiones_titulo' => 'Sesiones de vuelo',
        'aside_sesiones_total' => 'Total',
        'aside_sesiones_validadas' => 'Validadas',
        'aside_sesiones_vacio_titulo' => 'Sin sesiones',
        'aside_sesiones_vacio_detalle' => 'Esta persona todavía no participó en ninguna sesión de vuelo, ni como piloto ni como auxiliar.',
        'aside_sesiones_accion_desempenio' => 'Ver desempeño',
        'aside_anticipos_titulo' => 'Anticipos',
        'aside_anticipos_cantidad' => 'Registrados',
        'aside_anticipos_total' => 'Monto total',
        'aside_anticipos_valor' => 'Bs :monto',
        'aside_anticipos_vacio_titulo' => 'Sin anticipos',
        'aside_anticipos_vacio_detalle' => 'Todavía no se registró ningún anticipo a esta persona.',
        'aside_anticipos_accion_ver' => 'Ver anticipos',
        'aside_anticipos_accion_registrar' => 'Registrar anticipo',
    ],

    // Tarea 72 (HU-49, ADR 0015 punto 3), pasada a «cuadrilla» y ampliada con
    // alta de una sola vez, máquina de estados y accesorios por la tarea
    // "cuadrillas-estadias" (pedido del dueño, 19/9/2026): la cuadrilla es el
    // piloto, su ayudante (con posible segundo) y su equipamiento (dron,
    // vehículo, generador, baterías, accesorios). Sin campaña (la cuadrilla
    // es de Agrocom, trabaja para varias a la vez) — la vigencia es la de la
    // cuadrilla y la de cada integrante/recurso, no un período de campaña.
    //
    // El grupo de idioma conserva el nombre de clave `equipos_trabajo`
    // (identificador de código, no se traduce) — todos los VALORES dicen
    // «cuadrilla».
    'equipos_trabajo' => [
        'creado' => 'La cuadrilla se dio de alta correctamente.',
        'actualizado' => 'Los datos de la cuadrilla se actualizaron correctamente.',
        'eliminado' => 'La cuadrilla se dio de baja correctamente.',
        'estado_cambiado' => 'El estado de la cuadrilla se actualizó correctamente.',
        'integrante_asignado' => 'El integrante se asignó correctamente.',
        'integrante_finalizado' => 'Se finalizó la vigencia del integrante.',
        'recurso_asignado' => 'El recurso se asignó correctamente.',
        'recurso_finalizado' => 'Se finalizó la vigencia del recurso.',
        'accesorio_agregado' => 'El accesorio se agregó a la cuadrilla.',
        'accesorio_quitado' => 'El accesorio se quitó de la cuadrilla.',
        'aviso_solapamiento' => 'Ya está vigente en otra cuadrilla en fechas que se superponen: :equipos. Se guardó igual — la operación real presta gente y equipamiento entre cuadrillas.',
        'error_codigo_requerido' => 'Ingresa el código de la cuadrilla.',
        'error_base_requerida' => 'Elige la base de la cuadrilla.',
        'error_estado_requerido' => 'Elige el estado de la cuadrilla.',
        'error_desde_requerida' => 'Elige la fecha de inicio de la vigencia.',
        'error_integrante_persona_requerida' => 'Elige la persona que integra la cuadrilla.',
        'error_integrante_rol_requerido' => 'Elige el rol de la persona en la cuadrilla.',
        'error_integrante_desde_requerida' => 'Elige la fecha desde la que integra la cuadrilla.',
        'error_recurso_tipo_requerido' => 'Elige el tipo de recurso.',
        'error_recurso_id_requerido' => 'Elige el recurso.',
        'error_recurso_desde_requerida' => 'Elige la fecha desde la que se asigna el recurso.',
        'error_vigencia_hasta_requerida' => 'Elige la fecha en que termina la vigencia.',
        // Alta de una sola vez (ArmarCuadrilla, "cuadrillas-estadias").
        'error_piloto_requerido' => 'Elige el piloto de la cuadrilla.',
        'error_ayudante_requerido' => 'Elige el ayudante de la cuadrilla.',
        'error_persona_repetida' => 'La misma persona no puede ocupar dos puestos de la cuadrilla.',
        'error_dron_requerido' => 'Elige el dron de la cuadrilla.',
        'error_baterias_repetidas' => 'No repitas la misma batería.',
        // Accesorios (AgregarAccesorioEquipo, "cuadrillas-estadias").
        'error_accesorio_requerido' => 'Elige un accesorio del catálogo o escribe el nombre de uno nuevo.',
        'error_accesorio_ambos' => 'Elige un accesorio del catálogo o escribe uno nuevo, no los dos.',
        'error_accesorio_cantidad_requerida' => 'Indica la cantidad del accesorio (al menos 1).',

        // Listado
        'titulo' => 'Cuadrillas',
        'subtitulo' => 'Cuadrillas de Agrocom, con su base, vigencia y estado.',
        'nuevo' => 'Nueva cuadrilla',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Código o nombre…',
        'filtro_base' => 'Base',
        'filtro_estado' => 'Estado',
        'filtro_todos' => 'Todas',
        'filtrar' => 'Filtrar',
        'limpiar_filtro' => 'Limpiar filtro',
        'filtro_vacio' => 'Ninguna cuadrilla coincide con esta búsqueda.',
        'filtro_vacio_titulo' => 'Sin resultados',
        'filtro_vacio_detalle' => 'Ninguna cuadrilla coincide con esta búsqueda. Prueba con otro término o quita los filtros.',
        'vacio_titulo' => 'Todavía no hay cuadrillas',
        'vacio_detalle' => 'Una cuadrilla se arma con su piloto, su ayudante y su equipamiento (dron, vehículo, generador, baterías) desde un único formulario de alta.',
        'col_codigo' => 'Código',
        'col_nombre' => 'Nombre',
        'col_integrantes' => 'Integrantes',
        'col_dron' => 'Dron',
        'sin_integrantes' => 'Sin integrantes',
        'sin_piloto' => 'Sin piloto',
        'sin_dron' => 'Sin dron',
        'confirmar_eliminar_titulo' => 'Eliminar cuadrilla',
        'col_base' => 'Base',
        'col_vigencia' => 'Vigencia',
        'col_estado' => 'Estado',
        'sin_nombre' => '—',
        'vigente' => 'vigente',
        'ver' => 'Ver ficha',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja' => '¿Confirmas la baja de esta cuadrilla?',
        'paginacion_aria' => 'Paginación de cuadrillas',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        // Formulario
        'titulo_crear' => 'Nueva cuadrilla',
        'titulo_editar' => 'Editar cuadrilla',
        'subtitulo_form' => 'Quiénes la integran, con qué equipamiento sale al campo y desde cuándo.',
        'seccion_datos' => 'Datos de la cuadrilla',
        'seccion_identificacion' => 'Identificación',
        'seccion_vigencia' => 'Vigencia',
        'seccion_integrantes' => 'Integrantes',
        'seccion_equipamiento' => 'Equipamiento',
        'seccion_accesorios' => 'Accesorios',
        'campos_contador' => ':cantidad campos',
        'campo_codigo' => 'Código',
        'campo_nombre' => 'Nombre',
        'campo_base' => 'Base',
        'campo_base_placeholder' => 'Selecciona una base',
        'campo_estado' => 'Estado',
        'campo_desde' => 'Vigente desde',
        'campo_hasta' => 'Vigente hasta',
        'campo_hasta_ayuda' => 'Déjalo vacío si la cuadrilla sigue vigente.',
        'campo_baterias_ayuda' => 'La cantidad de baterías es cuántas marques.',
        // Formulario de alta y ficha de edición.
        'estado_pasos_aria' => 'Estado de la cuadrilla',
        'estado_cambio_de_a' => 'Cambio de estado: de :desde a :hacia',
        'confirmar_estado_titulo' => [
            'activo' => 'Activar cuadrilla',
            'inactivo' => 'Inactivar cuadrilla',
        ],
        'confirmar_estado' => [
            'activo' => 'La cuadrilla vuelve a estar disponible para órdenes de trabajo y estadías.',
            'inactivo' => 'La cuadrilla deja de ofrecerse en órdenes de trabajo y estadías. Sus registros anteriores no cambian y puedes reactivarla cuando quieras.',
        ],
        'accion_estado' => [
            'activo' => 'Activar',
            'inactivo' => 'Inactivar',
        ],
        'campo_codigo_ayuda' => 'Corto y único, como C1 o NORTE-2. Es lo que se ve en órdenes, gastos y estadías.',
        'campo_nombre_ayuda' => 'Opcional. Un nombre para reconocerla más fácil.',
        'campo_base_ayuda' => 'La base de la que sale la cuadrilla.',
        'campo_desde_ayuda' => 'Desde cuándo trabaja esta formación. Sus integrantes y su equipamiento nacen con esta misma fecha.',
        'campo_piloto' => 'Piloto',
        'campo_piloto_placeholder' => 'Elige al piloto…',
        'campo_piloto_ayuda' => 'Quien vuela el dron. Solo figura el personal con puesto de piloto.',
        'campo_ayudante' => 'Ayudante',
        'campo_ayudante_placeholder' => 'Elige al ayudante…',
        'campo_ayudante_ayuda' => 'Quien prepara el tanque, rota las baterías y atiende el generador.',
        'campo_ayudante2' => 'Segundo ayudante',
        'campo_ayudante2_placeholder' => 'Elige al segundo ayudante…',
        'campo_ayudante2_ayuda' => 'Opcional. Para cuadrillas que salen con dos ayudantes.',
        'campo_dron' => 'Dron',
        'campo_dron_placeholder' => 'Elige el dron…',
        'campo_dron_ayuda' => 'El dron con el que trabaja la cuadrilla.',
        'campo_vehiculo' => 'Camioneta o vehículo',
        'campo_vehiculo_placeholder' => 'Elige el vehículo…',
        'campo_vehiculo_ayuda' => 'Opcional. Se reconoce por su placa.',
        'campo_generador' => 'Generador',
        'campo_generador_placeholder' => 'Elige el generador…',
        'campo_generador_ayuda' => 'Opcional. El que carga las baterías en el campo.',
        'campo_baterias' => 'Baterías',
        'baterias_vacio_titulo' => 'Sin baterías disponibles',
        'baterias_vacio_detalle' => 'No hay baterías activas en el catálogo. Puedes asignarlas después, desde la ficha de la cuadrilla.',
        'accion_nuevo_corto' => 'Nuevo',
        'accion_nueva_corto' => 'Nueva',
        'accion_nuevo_personal' => 'Dar de alta a una persona nueva',
        'accion_nueva_base' => 'Dar de alta una base nueva',
        'accion_nuevo_dron' => 'Dar de alta un dron nuevo',
        'accion_nuevo_vehiculo' => 'Dar de alta un vehículo nuevo',
        'accion_nuevo_generador' => 'Dar de alta un generador nuevo',
        'volver_a_formulario_origen' => 'Volver al formulario de origen',

        // Tablas de detalle de la ficha de edición y sus diálogos.
        'contador_vigentes' => '{0} sin vigentes|{1} :cantidad vigente|[2,*] :cantidad vigentes',
        'contador_baterias' => '{0} sin baterías|{1} :cantidad batería|[2,*] :cantidad baterías',
        'contador_accesorios' => '{0} sin accesorios|{1} :cantidad accesorio|[2,*] :cantidad accesorios',
        'detalle_agregar' => 'Agregar',
        'detalle_finalizar' => 'Finalizar',
        'detalle_integrantes_vacio_titulo' => 'Sin integrantes',
        'detalle_equipamiento_vacio_titulo' => 'Sin equipamiento',
        'detalle_accesorios_vacio_titulo' => 'Sin accesorios',
        'detalle_agregar_integrante' => 'Agregar integrante',
        'detalle_agregar_integrante_mensaje' => 'Elige a la persona y el puesto que ocupa en la cuadrilla.',
        'detalle_integrantes_vacio_detalle' => 'Agrega al piloto y a su ayudante para que la cuadrilla pueda recibir trabajos.',
        'detalle_integrantes_paginacion' => 'Paginación de integrantes',
        'ficha_campo_rol_placeholder' => 'Elige el puesto…',
        'detalle_finalizar_integrante_titulo' => 'Finalizar integrante',
        'detalle_finalizar_integrante_mensaje' => ':persona deja de integrar la cuadrilla desde la fecha que indiques. Su paso queda en el historial.',
        'detalle_campo_hasta_cierre' => 'Último día en la cuadrilla',
        'detalle_hasta_ayuda' => 'Déjalo vacío si sigue vigente.',
        'detalle_agregar_equipamiento' => 'Agregar equipamiento',
        'detalle_agregar_equipamiento_mensaje' => 'Elige el tipo y después el recurso, entre los que ya existen en el sistema.',
        'detalle_equipamiento_vacio_detalle' => 'Agrega el dron, las baterías, el generador y la camioneta con los que sale la cuadrilla.',
        'detalle_equipamiento_paginacion' => 'Paginación de equipamiento',
        'detalle_col_recurso' => 'Recurso',
        'detalle_recurso_ayuda' => 'Cada batería se agrega por separado: la cantidad es cuántas tenga vigentes.',
        'ficha_campo_tipo_placeholder' => 'Elige el tipo…',
        'detalle_finalizar_recurso_titulo' => 'Finalizar equipamiento',
        'detalle_finalizar_recurso_mensaje' => ':recurso deja de estar asignado a la cuadrilla desde la fecha que indiques.',
        'detalle_agregar_accesorio_mensaje' => 'Elige un accesorio del catálogo o escribe uno nuevo, y cuántos lleva la cuadrilla.',
        'detalle_accesorios_vacio_detalle' => 'Agrega lo que la cuadrilla lleva al campo: machete, palas, linternas…',
        'detalle_accesorios_paginacion' => 'Paginación de accesorios',
        'detalle_accesorio_catalogo' => 'Accesorio del catálogo',
        'detalle_accesorio_catalogo_placeholder' => 'Elige un accesorio…',
        'detalle_accesorio_nuevo' => 'O escribe uno nuevo',
        'detalle_accesorio_nuevo_placeholder' => 'Por ejemplo: Machete',
        'detalle_accesorio_nuevo_ayuda' => 'Queda en el catálogo para las demás cuadrillas. Si la cuadrilla ya lo tiene, se actualiza la cantidad.',
        'confirmar_quitar_accesorio_titulo' => 'Quitar accesorio',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'volver' => 'Volver a cuadrillas',
        'confirmar_cambio_titulo' => 'Cambiar estado de la cuadrilla',
        'confirmar_cambio' => 'Cambiar estado',

        // Pasos de estado (`step-arrow`, §6.3.4, "cuadrillas-estadias"):
        // grupo anidado propio de esta pantalla, distinto del grupo plano
        // `personal.estado.*` que ya usan el listado y la ficha para el
        // badge — mismo contenido, ruta de clave que exige
        // `PasosDeEstado::armar()`/`::ayuda()` (mismo criterio que
        // `campania.campania.estado`/`estado_ayuda`).
        'estado' => [
            'activo' => 'Activa',
            'inactivo' => 'Inactiva',
        ],
        'estado_ayuda' => [
            'activo' => ':actual: la cuadrilla está operativa y se puede asignar a órdenes de trabajo.',
            'inactivo' => ':actual: la cuadrilla no se asigna a trabajos nuevos, pero conserva su historial.',
        ],

        // Ficha
        'ficha_titulo' => 'Cuadrilla :codigo',
        'ficha_subtitulo' => 'Integrantes, equipamiento y accesorios vigentes a la fecha elegida.',
        'ficha_volver' => 'Volver al listado',
        'ficha_campo_base' => 'Base',
        'ficha_campo_vigencia' => 'Vigencia de la cuadrilla',
        'ficha_selector_fecha' => 'Ver vigencia al',
        'ficha_consultar' => 'Consultar',
        'ficha_seccion_integrantes' => 'Integrantes',
        'ficha_seccion_accesorios' => 'Accesorios',
        'ficha_integrantes_vacio' => 'Nadie integraba esta cuadrilla en la fecha elegida.',
        'ficha_accesorios_vacio' => 'Esta cuadrilla no tenía accesorios asignados.',
        'ficha_finalizar' => 'Finalizar vigencia',
        'ficha_quitar' => 'Quitar',
        'ficha_asignar_integrante' => 'Agregar integrante',
        'ficha_campo_persona' => 'Persona',
        'ficha_campo_persona_placeholder' => 'Selecciona una persona',
        'ficha_campo_rol' => 'Rol en la cuadrilla',
        'ficha_campo_tipo' => 'Tipo de recurso',
        'ficha_campo_cantidad' => 'Cantidad',
        'ficha_campo_accesorio' => 'Accesorio',
        'ficha_campo_observacion' => 'Observación',
        'ficha_campo_observacion_placeholder' => 'Notas sobre el accesorio (opcional)',
        'ficha_seccion_recursos' => 'Recursos asignados',
        'ficha_recursos_vacio' => 'Esta cuadrilla no tenía recursos asignados en la fecha elegida.',
        'ficha_asignar_recurso' => 'Agregar equipamiento',
        'ficha_campo_recurso_placeholder' => 'Selecciona un recurso',
        'ficha_agregar_accesorio' => 'Agregar accesorio',
        'confirmar_quitar_accesorio' => '¿Confirmas que quieres quitar este accesorio?',

        // Resumen relacionado del aside de edit() (§6.3.1, "cuadrillas-estadias").
        'aside_estadias_titulo' => 'Estadías en hacienda',
        'aside_estadias_total' => 'Total',
        'aside_estadias_en_curso' => 'En curso',
        'aside_estadias_vacio_titulo' => 'Sin estadías registradas',
        'aside_estadias_vacio_detalle' => 'Todavía no se registró ninguna estadía de esta cuadrilla en una hacienda.',
        'aside_estadias_accion_ver' => 'Ver estadías',
        'aside_estadias_accion_crear' => 'Registrar estadía',
        'aside_trabajos_titulo' => 'Órdenes de trabajo',
        'aside_trabajos_total' => 'Total',
        'aside_trabajos_abiertos' => 'Abiertos',
        'aside_trabajos_vacio_titulo' => 'Sin trabajos asignados',
        'aside_trabajos_vacio_detalle' => 'Todavía no se le asignó ningún trabajo a esta cuadrilla.',
        'aside_trabajos_accion' => 'Ver trabajos',
        'aside_base_titulo' => 'Base',
        'aside_base_nombre' => 'Nombre',
        'aside_base_ubicacion' => 'Ubicación',
        'aside_base_accion' => 'Ver base',
    ],

    // Ficha de desempeño de una persona (HU-58, tarea 81): "¿qué hizo esta
    // persona esta campaña?" — hechos verificables por SESIÓN (nunca por
    // equipo de trabajo, ADR 0015 punto 3), sin puntaje ni ranking. Arquetipo
    // Detalle, mismo molde de filtros por GET que `equipos_trabajo.ficha_*`.
    'desempenio' => [
        'titulo' => 'Desempeño de :nombre',
        'subtitulo' => 'Sesiones, rechazos e incidencias de la persona en el rango elegido — hechos, no un puntaje.',
        'volver' => 'Volver a personas',

        'filtro_desde' => 'Desde',
        'filtro_hasta' => 'Hasta',
        'filtro_cliente' => 'Cliente',
        'filtro_cliente_placeholder' => 'Todos los clientes',
        'filtro_campania' => 'Campaña',
        'filtro_campania_placeholder' => 'Todas las campañas',
        'filtrar' => 'Filtrar',
        'limpiar_filtro' => 'Limpiar filtros',

        'total_hectareas' => 'Hectáreas aplicadas',
        'total_sesiones_validadas' => 'Sesiones validadas',
        'total_sesiones_rechazadas' => 'Sesiones rechazadas',
        'total_incidencias' => 'Incidencias',

        // Estado vacío ilustrado (etapa 3, tarea 81): la persona no tiene
        // NINGUNA sesión ni rechazo en todo el rango — ver comentario de
        // cabecera de desempeno.blade.php. Distinto de 'sesiones_vacio' de
        // abajo, que es "hay datos pero este filtro puntual no trae nada".
        'vacio_titulo' => 'Sin actividad registrada',
        'vacio_detalle' => 'Esta persona no tiene sesiones en el rango elegido. Prueba ampliar las fechas, o vuelve cuando haya volado su primera aplicación.',

        'seccion_sesiones' => 'Sesiones',
        'sesiones_vacio' => 'No hay sesiones de esta persona con estos filtros.',
        'col_fecha' => 'Fecha',
        'col_rol' => 'Rol',
        'col_cliente' => 'Cliente',
        'col_campania' => 'Campaña',
        'col_lote' => 'Lote',
        'col_hectareas' => 'Hectáreas',
        'col_estado' => 'Estado',
        'sin_campania' => '—',

        'seccion_rechazos' => 'Sesiones rechazadas',
        'rechazos_vacio' => 'Ninguna sesión rechazada con estos filtros.',
        'col_motivo' => 'Motivo',
        'col_rechazado_por' => 'Rechazado por',

        'seccion_incidencias' => 'Incidencias',
        'incidencias_vacio' => 'Sin incidencias registradas con estos filtros.',
        'col_tipo' => 'Tipo',
        'col_descripcion' => 'Descripción',
        'sin_descripcion' => '—',

        // Catálogo de EstadoSesion (Operaciones\Dominio\EstadoSesion) — copia
        // deliberada de `operaciones.estado`: esta pantalla es de `Personal`
        // y su lang file es su propio vocabulario (ADR 0013), no un import
        // cruzado del lang de otro módulo.
        'estado_sesion' => [
            'abierto' => 'Abierto',
            'cerrado' => 'Cerrado',
            'validado' => 'Validado',
        ],

        // Catálogo de TipoIncidencia (Operaciones\Dominio\TipoIncidencia) —
        // misma razón de copia que `estado_sesion` arriba.
        'incidencia_tipo' => [
            'caldo' => 'Caldo',
            'esc' => 'ESC',
            'bateria' => 'Batería',
            'mecanica' => 'Mecánica',
            'clima' => 'Clima',
            'otro' => 'Otro',
        ],
    ],

    // Mensajes de error.
    'errores' => [
        'equipo_trabajo_duplicado' => "Ya existe una cuadrilla activa con el código ':codigo'.",
        'recurso_equipo_invalido' => 'El :tipo #:recurso_id no existe o no está disponible.',
        'vigencia_integrante_solapada' => ':persona ya integra esta cuadrilla en una vigencia que se superpone con :rango.',
        'vigencia_recurso_solapada' => ':recurso ya está asignado a esta cuadrilla en una vigencia que se superpone con :rango.',
        'vigencia_indefinida' => 'vigente',
        // Máquina de estados de la cuadrilla ("cuadrillas-estadias", 19/9/2026).
        'transicion_equipo_trabajo_no_permitida' => 'No se puede pasar la cuadrilla de :desde a :hasta.',
    ],

    // Mensajes de los formularios.
    'validacion' => [
        'base_invalida' => 'La base seleccionada no es válida.',
        'persona_invalida' => 'La persona seleccionada no es válida.',
        'accesorio_invalido' => 'El accesorio seleccionado no es válido.',
    ],

];
