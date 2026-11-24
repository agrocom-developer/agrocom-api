<?php

/*
|--------------------------------------------------------------------------
| Disputas y reembolsos del marketplace (ADR 0033 y 0039)
|--------------------------------------------------------------------------
|
| Parámetros de la política de disputas (`docs/politicas/disputas.md`): los plazos
| para abrir, responder y apelar. Los valores de aquí son el respaldo; no hay
| secretos. Los importes siempre son cadenas decimales exactas.
|
*/

return [
    /* Horas que tiene el cliente, desde que el servicio queda `ejecutada`, para abrir una disputa (la conformidad tácita cierra el plazo). */
    'apertura_cliente_horas' => (int) env('DISPUTAS_APERTURA_CLIENTE_HORAS', 72),

    /* Días que tiene el proveedor, desde que el servicio se ejecutó o se completó, para abrir una disputa (cobro, acceso o calificación). */
    'apertura_proveedor_dias' => (int) env('DISPUTAS_APERTURA_PROVEEDOR_DIAS', 30),

    /* Horas que tiene la contraparte para responder a la disputa. */
    'respuesta_horas' => (int) env('DISPUTAS_RESPUESTA_HORAS', 48),

    /* Días que tienen las partes, desde la resolución, para apelar una vez con pruebas nuevas. */
    'apelacion_dias' => (int) env('DISPUTAS_APELACION_DIAS', 7),

    /* Longitud máxima del detalle y de cada mensaje. */
    'texto_max' => (int) env('DISPUTAS_TEXTO_MAX', 2000),
];
