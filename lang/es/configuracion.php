<?php

return [

    // Pantalla `/panel/configuracion` (tarea 78, HU-55): llaves y tokens de
    // infraestructura, separados a propósito de los datos de la empresa
    // (`seguridad.organizacion.*`, en lang/es/seguridad.php).
    'titulo' => 'Configuración del sistema',
    'subtitulo' => 'Claves y tokens que el sistema necesita para funcionar. Están separados de los datos de la empresa.',
    'tabs_aria' => 'Secciones de configuración',

    'grupo_mapas' => 'Mapas',
    'grupo_correo' => 'Correo',
    'grupo_integraciones' => 'Integraciones',

    'guardada' => 'Configuración actualizada.',

    'estado_configurada' => 'Configurada',
    'estado_sin_configurar' => 'Sin configurar',
    'estado_termina_en' => 'Termina en :ultimos4',

    'accion_borrar' => 'Eliminar',
    'ayuda_secreto' => 'Déjalo vacío para conservarlo. Para reemplazarlo, escribe el valor nuevo completo.',
    'ayuda_forzar_leaflet' => 'Activado: el editor de lotes usa Leaflet aunque haya una clave de Google Maps. Desactivado: usa Google automáticamente si hay una clave válida.',

    'vacio_integraciones' => 'Todavía no hay integraciones configurables. Se agregan a medida que el sistema las necesite.',

    // Descripciones de cada clave del catálogo (config/configuracion.php).
    // Búsqueda por llave LITERAL (`$descripciones[$clave]`), nunca
    // `__("configuracion.claves.{$clave}")`: ver el docblock de
    // `ListarConfiguracionPorGrupo::ejecutar()`.
    'claves' => [
        'mapas.proveedor_preferido' => 'Usar siempre Leaflet, no Google Maps',
        'mapas.google_maps_api_key' => 'Clave de Google Maps',
        'correo.host' => 'Servidor de correo',
        'correo.puerto' => 'Puerto',
        'correo.usuario' => 'Usuario',
        'correo.password' => 'Contraseña',
        'correo.remitente' => 'Correo del remitente',
    ],

];
