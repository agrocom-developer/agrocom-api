<?php

/*
 * Claves de traducción del sistema de diseño (atoms/molecules/organisms/
 * templates del catálogo Atomic Design), distintas de las claves de texto
 * específicas de cada pantalla de negocio (ADR 0013 punto pendiente,
 * resuelto acá: `lang/es/ui.php` es del catálogo de componentes; el texto
 * propio de una pantalla —copy del login, nombres de rol, etc.— va en un
 * archivo por módulo/pantalla, p. ej. `lang/es/seguridad.php`, a cargo de
 * quien construya esa pantalla).
 */

return [

    'logo' => [
        'alt' => 'Agrocom',
    ],

    'input' => [
        'show_password' => 'Mostrar contraseña',
        'hide_password' => 'Ocultar contraseña',
    ],

    // Chrome del combobox atoms/select (tarea 76, HU-53): microcopy propia
    // del control, no del formulario que lo usa (ADR 0013) — mismo criterio
    // que 'input' de arriba.
    'select' => [
        'search_placeholder' => 'Buscar…',
        'no_results' => 'Sin resultados',
        'clear' => 'Limpiar selección',
    ],

    // Chrome del calendario atoms/date (tarea 76, HU-53), etapa 2: nombres
    // de mes/día y microcopy de navegación — no son texto de negocio, son
    // el vocabulario del propio calendario (mismo criterio que 'select' de
    // arriba). 'dias_cortos'/'dias_completos' arrancan en lunes (invariante
    // de la tarea: semana empezando lunes) — date.js nunca reordena estos
    // arreglos, calcula el índice de columna directo desde la fecha.
    'date' => [
        'meses' => [
            'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
        ],
        'dias_cortos' => ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
        'dias_completos' => ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'],
        'mes_anterior' => 'Mes anterior',
        'mes_siguiente' => 'Mes siguiente',
        'hoy' => 'Hoy',
        'limpiar' => 'Limpiar fecha',
        'elegir_fecha' => 'Elegir fecha',
        // Formato que se muestra como guía al escribir fecha y hora.
        'formato_fecha_hora' => 'dd/mm/aaaa hh:mm',
    ],

    // Chrome de la búsqueda de atoms/checkbox-group (tarea 76, HU-53), etapa
    // 3: mismo criterio que 'select' de arriba — microcopy propia del
    // filtro, no del formulario que lo usa (ADR 0013).
    'checkbox_group' => [
        'search_placeholder' => 'Buscar…',
        'no_results' => 'Sin resultados',
    ],

    'theme' => [
        'light' => 'Tema claro',
        'dark' => 'Tema oscuro',
        'toggle' => 'Cambiar tema',
    ],

    'timezone' => [
        'badge_title' => 'Zona horaria',
    ],

    'image_modal' => [
        'ver_completa' => 'Ver imagen completa',
    ],

    'action' => [
        'save' => 'Guardar',
        'cancel' => 'Cancelar',
        'continue' => 'Continuar',
        'close' => 'Cerrar',
        'add' => 'Agregar',
        'confirm' => 'Confirmar',
    ],

    // Datos faltantes, genéricos del panel.
    'sin_datos' => '—',

    // Complementos para texto (segundo, tercero, etc.).
    'complemento' => [
        'segundo' => '(segundo)',
    ],

    // Estados genéricos de vigor.
    'estado' => [
        'vigente' => 'Vigente',
        'finalizado' => 'Finalizado',
    ],

    // Botón "Volver" del header de formularios (molecules/boton-volver,
    // memento de navegación, 17/9/2026): microcopy del componente, no de
    // cada pantalla — mismo criterio que 'action' de arriba.
    'navegacion' => [
        'volver' => 'Volver',
        'volver_a' => 'Volver a :origen',
    ],

    // Botón de cerrar genérico.
    'cerrar' => 'Cerrar',

    // Chrome genérico del panel (organisms/sidebar-nav, organisms/topbar,
    // HU-02): acciones de la cáscara de navegación, reutilizables en
    // cualquier pantalla — no específicas de una pantalla de negocio, por
    // eso viven acá y no en seguridad.php (ver sistema_diseno_panel.md §5).
    'sidebar' => [
        'open' => 'Abrir menú',
        'close' => 'Cerrar menú',
        'collapse' => 'Colapsar menú',
        'expand' => 'Expandir menú',
    ],

    'footer' => [
        'copyright' => 'AGROCOM SRL · :year',
    ],

    // Chrome del layout de tres niveles (quinta vuelta — organisms/module-rail,
    // organisms/module-sidebar, organisms/topbar): riel de módulos, buscador
    // global y drawer móvil. Genérico del catálogo — el CONTENIDO (badges,
    // breadcrumb) lo pasa quien arma la página.
    'rail' => [
        'aria' => 'Módulos',
        'configuracion' => 'Configuración',
    ],
    'header' => [
        'buscador_placeholder' => 'Buscar lote, orden, piloto o dron',
        'buscador_aria' => 'Buscador global',
        'atajo_buscador' => '⌘K',
    ],
    'drawer' => [
        'abrir' => 'Abrir módulos',
        'cerrar' => 'Cerrar módulos',
    ],

    // organisms/topbar (mockup de dashboard, HU-02 segunda ronda): chrome
    // genérico de la campana de notificaciones, reutilizable en cualquier
    // pantalla — el CONTENIDO de cada notificación (título, ícono, hora) lo
    // pasa quien arme la pantalla, esta clave es solo el rótulo del
    // disparador y el estado vacío.
    'topbar' => [
        'notifications' => 'Notificaciones',
        'no_notifications' => 'Sin notificaciones nuevas',
        'logout' => 'Cerrar sesión',
    ],

    // Chrome de una tabla de listado (patrón `.ag-<pagina>__tabla`) — el
    // índice numérico de fila y el link de "Limpiar" de molecules/table-search
    // son idénticos en cualquier pantalla, no hace falta redefinirlos en cada
    // lang/es/<dominio>.php.
    'tabla' => [
        'col_indice' => '#',
        'col_acciones' => 'Acciones',
        'col_integrantes' => 'Integrantes',
        'col_dron' => 'Dron',
        'col_recurso' => 'Recurso',
        'col_estado' => 'Estado',
        'buscador_limpiar' => 'Limpiar búsqueda',
        'mas_acciones' => 'Más acciones',
        'filtros_boton' => 'Filtros',
        'filtros_aplicar' => 'Aplicar',
        'filtros_limpiar' => 'Limpiar filtros',
        'filtro_placeholder' => 'Filtrar…',
        'vista_grupo' => 'Vista del listado',
        'vista_lista' => 'Ver como lista',
        'vista_grilla' => 'Ver como grilla',
    ],

    // Paginador de listas que ya están completas en la página (modal de
    // lotes, tabla de lotes de un contrato).
    'paginador' => [
        'anterior' => 'Página anterior',
        'siguiente' => 'Página siguiente',
        'pagina' => 'Página :numero',
    ],

    // Selector de horario (inicio y fin en una sola casilla).
    'time_range' => [
        'placeholder' => '--:-- – --:--',
        'elegir_horario' => 'Elegir horario',
        'limpiar_horario' => 'Limpiar horario',
        'inicio' => 'Inicio',
        'fin' => 'Fin',
        'hora' => 'Hora',
        'minutos' => 'Minutos',
        'periodo' => 'a. m. o p. m.',
        'am' => 'a. m.',
        'pm' => 'p. m.',
        'formato' => '6:30 a. m.',
        'ayuda_teclado' => 'Escribe la hora como 6:30 a. m., 6 pm o 18:30.',
        'limpiar' => 'Limpiar',
        'cancelar' => 'Cancelar',
        'aceptar' => 'Aceptar',
        'usar_teclado' => 'Usar teclado',
        'usar_reloj' => 'Usar reloj',
        'error_ambas' => 'Indica la hora de inicio y la de fin.',
        'error_orden' => 'La hora de fin debe ser posterior a la de inicio.',
        'error_formato' => 'Escribe la hora como 6:30 a. m., 6 pm o 18:30.',
    ],

    // Pasos del estado de un objeto (molecules/step-arrow): la pista de los
    // pasos que no se pueden usar y el cierre del párrafo de ayuda. El texto
    // de cada estado lo define el propio objeto en su archivo de idioma.
    'pasos' => [
        'pista_sin_permiso' => 'No tienes permiso para cambiar el estado.',
        'pista_bloqueado' => 'Primero debe pasar por «:paso».',
        'ayuda_accion' => 'Para avanzar, haz clic en «:paso».',
        'ayuda_sin_permiso' => 'Con tu rol no puedes cambiar el estado.',
    ],

    // Mensajes de error.
    'errores' => [
        'borrado_fisico_no_permitido' => 'Borrado físico bloqueado para :clase: los modelos de dominio solo admiten borrado lógico (ADR 0007 — soft delete y bitácora de auditoría).',
        'escritura_en_modo_solo_lectura' => 'Estás en modo de solo lectura: no se puede guardar, borrar ni restaurar nada. Vuelve a tu vista para hacerlo.',
        // Aviso cuando no carga el mapa de Google.
        'google_maps_no_disponible' => 'No se pudo cargar el SDK de Google Maps',
    ],

    // Textos de los comandos de consola.
    'consola' => [
        'listar_modelos_ruta_inexistente' => 'No existe :ruta',
        'listar_modelos_vacio' => 'No se encontró ningún modelo Eloquent bajo app/Dominios/*/Infraestructura/Eloquent.',
        'listar_modelos_columnas' => ['Módulo', 'Modelo', 'Tabla', 'Namespace'],
        'listar_modelos_resumen' => ':cantidad modelo(s) en :modulos módulo(s).',
    ],

];
