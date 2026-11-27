<?php

/*
 * Códigos de un solo uso de las cuentas de app (ADR 0046, puntos 27 a 31): verificar el teléfono,
 * recuperar el acceso, cambiar la contraseña o el teléfono y eliminar la cuenta.
 */
return [
    'codigo' => [
        'longitud' => 6,
        'vigencia_minutos' => (int) env('VERIFICACION_VIGENCIA_MINUTOS', 10),
        'intentos' => 5,
        'envios_por_hora' => 3,
        'envios_por_dia' => 10,
    ],

    /*
     * Código fijo `000000` para las pruebas y los simuladores. Solo se respeta con APP_ENV `local` o
     * `testing`: en cualquier otro entorno se ignora (ver `CodigosDeVerificacion::modoDeDesarrollo()`).
     */
    'modo_desarrollo' => (bool) env('VERIFICACION_MODO_DESARROLLO', false),

    /*
     * Adaptadores de los canales que salen por un proveedor externo. Cada uno es una clase que implementa
     * `App\Dominios\Seguridad\Contratos\EnviadorDeCodigo` y enchufa la función de envío que ya usa el
     * panel (WhatsApp Business). Sin adaptador, el canal responde `503 canal_no_disponible`.
     */
    'canales' => [
        'whatsapp' => ['adaptador' => env('VERIFICACION_WHATSAPP_ADAPTADOR')],
        'sms' => ['adaptador' => env('VERIFICACION_SMS_ADAPTADOR')],
    ],

    /*
     * Bloqueo temporal del login (ADR 0046, punto 31): la contraseña es corta, así que los fallos cuentan
     * por identificador (exista o no la cuenta) y por IP.
     */
    'login' => [
        'ventana_minutos' => 15,
        'fallos_por_identificador' => 10,
        'fallos_por_ip' => 30,
    ],
];
