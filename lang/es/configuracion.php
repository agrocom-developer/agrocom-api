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
        'correo.proveedor' => 'Proveedor de correo',
        'correo.host' => 'Servidor SMTP',
        'correo.puerto' => 'Puerto',
        'correo.cifrado' => 'Cifrado',
        'correo.usuario' => 'Usuario o correo de la cuenta',
        'correo.password' => 'Contraseña de aplicación',
        'correo.remitente' => 'Correo del remitente',
        'correo.remitente_nombre' => 'Nombre visible del remitente',
    ],

    // Nombres de los campos del correo en los mensajes de validación (ver ActualizarConfiguracionRequest).
    'campos_correo' => [
        'proveedor' => 'proveedor',
        'host' => 'servidor SMTP',
        'puerto' => 'puerto',
        'cifrado' => 'cifrado',
        'usuario' => 'usuario',
        'remitente' => 'correo del remitente',
        'remitente_nombre' => 'nombre del remitente',
    ],

    // Pestaña Correo: el servidor que envía los códigos de verificación y los enlaces para recuperar el acceso.
    'correo' => [
        'contador' => '8 campos',
        'sin_servidor_guardado' => 'Todavía no hay un servidor de correo guardado. Los valores de abajo son los de Gmail; completa tu cuenta y la contraseña de aplicación, guarda y envía un correo de prueba.',
        'proveedor_gmail' => 'Gmail',
        'proveedor_outlook' => 'Outlook o Microsoft 365',
        'proveedor_otro' => 'Otro (servidor propio)',
        'cifrado_tls' => 'STARTTLS (puerto 587)',
        'cifrado_ssl' => 'SSL/TLS (puerto 465)',
        'cifrado_ninguno' => 'Ninguno (solo redes internas)',
        'ayuda_cifrado' => 'Gmail y Outlook usan STARTTLS en el puerto 587; Gmail también admite SSL/TLS en el 465.',
        'ayuda_usuario' => 'Normalmente es la dirección de correo completa.',
        'ayuda_password' => 'No es la contraseña de tu correo. Déjala vacía para conservar la guardada; para cambiarla, escribe la nueva completa.',
        'ayuda_remitente' => 'Dirección que aparece como remitente. En Gmail debe ser la misma cuenta o un alias de ella.',
        'password_guardada' => 'Guardada',
        'guia_titulo' => 'Cómo crear una contraseña de aplicación en Gmail',
        'guia_paso_1' => 'Entra a tu cuenta de Google y activa la verificación en dos pasos (Seguridad > Verificación en dos pasos).',
        'guia_paso_2' => 'Abre myaccount.google.com/apppasswords e inicia sesión de nuevo si te lo pide.',
        'guia_paso_3' => 'Escribe un nombre, por ejemplo «DronAgro», y pulsa Crear.',
        'guia_paso_4' => 'Copia las 16 letras que muestra Google y pégalas aquí; los espacios no importan. Solo se muestran una vez.',
        'prueba_ayuda' => 'Guarda los cambios y luego envía un correo de prueba a la dirección de tu cuenta.',
        'prueba_boton' => 'Enviar correo de prueba',
    ],

    'correo_prueba' => [
        'asunto' => 'Correo de prueba de DronAgro',
        'cuerpo' => 'Si lees esto, el servidor de correo de DronAgro está bien configurado.',
        'nota' => 'Ya puede enviar códigos de verificación y enlaces para recuperar el acceso.',
        'despedida' => 'Saludos, DronAgro.',
        'exito' => 'Correo de prueba enviado a :destino. Revisa tu bandeja de entrada.',
        'error' => [
            'sin_configurar' => 'Todavía no hay un servidor de correo configurado. Guarda los datos del servidor y vuelve a intentarlo.',
            'sin_destino' => 'Tu cuenta no tiene un correo registrado, así que no hay dónde enviar la prueba. Agrégalo en tu perfil.',
            'autenticacion' => 'El servidor rechazó el usuario o la contraseña. Con Gmail usa una contraseña de aplicación, no la de tu correo, y revisa que el usuario sea la dirección completa.',
            'conexion' => 'No se pudo conectar con el servidor. Revisa el servidor SMTP y el puerto.',
            'tiempo' => 'El servidor tardó demasiado en responder. Revisa el servidor y el puerto, o intenta de nuevo en unos minutos.',
            'cifrado' => 'No se pudo asegurar la conexión. Revisa que el cifrado coincida con el puerto: STARTTLS con 587 o SSL/TLS con 465.',
            'desconocido' => 'No se pudo enviar el correo de prueba. Revisa los datos del servidor e intenta de nuevo.',
        ],
    ],

];
