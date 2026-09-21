<?php

/*
 * Mensajes de error generales del sistema: los que responde cuando algo falla
 * antes de llegar a una pantalla o a un dato concreto (sesión vencida, falta
 * de permiso, dirección que no existe). Los ven tanto el panel como las apps.
 */

return [

    'no_autenticado' => 'Tu sesión no es válida o ya venció. Vuelve a ingresar.',
    'no_autorizado' => 'No tienes permiso para hacer esto.',
    'no_encontrado' => 'No encontramos lo que buscas.',
    'metodo_no_permitido' => 'Esta operación no está disponible.',
    'pagina_vencida' => 'La página venció por inactividad. Recárgala y vuelve a intentar.',
    'demasiados_intentos' => 'Demasiados intentos. Espera un momento y vuelve a intentar.',
    'error_servidor' => 'Ocurrió un error en el servidor. Vuelve a intentar en un momento.',
    'en_mantenimiento' => 'El sistema está en mantenimiento. Vuelve a intentar en unos minutos.',
    'solicitud_invalida' => 'No se pudo procesar la solicitud.',

];
