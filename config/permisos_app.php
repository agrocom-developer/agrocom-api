<?php

/*
|--------------------------------------------------------------------------
| Permisos de las cuentas de app (ADR 0046, punto 18)
|--------------------------------------------------------------------------
|
| Lo que cada tipo de cuenta de app puede hacer desde su app. No son permisos
| del panel: viven acá (no en la matriz de `sec_role`), no se editan desde el
| panel y `sec_permission.ambito` los marca como `app` para que la pantalla de
| roles no los ofrezca. El alcance (qué es «lo suyo») lo hacen los casos de uso.
|
| TRANSITORIO hasta la fase A5 (contrato del token): el token de dispositivo
| sigue resolviendo permisos por los roles `cliente`, `proveedor` y `piloto`
| de `sec_role` (ámbito `app`), que `SeguridadSeeder` siembra desde esta lista.
| La clave `piloto` desaparece cuando el piloto con cuenta propia de un tercero
| sea una cuenta `proveedor` (A5); `IdentidadOperarioTokenSanctum` pasará a
| leer esta lista por tipo y estado de la cuenta.
*/

return [
    'cliente' => [
        'solicitudes.cliente.gestionar',
        'solicitudes.terreno.gestionar',
        'solicitudes.solicitud.gestionar',
        'negociacion.oferta.cliente',
        'negociacion.seguimiento.ver',
        'negociacion.precio_final.confirmar',
        'negociacion.mensaje.usar',
        'negociacion.preferencias.gestionar',
        'calificaciones.calificacion.calificar_proveedor',
        'disputas.disputa.gestionar_cliente',
    ],

    'proveedor' => [
        'proveedores.cuenta.gestionar',
        'proveedores.documento.cargar',
        'negociacion.mercado.ver',
        'negociacion.oferta.proveedor',
        'negociacion.seguimiento.registrar',
        'negociacion.seguimiento.ver',
        'negociacion.evidencia.subir',
        'negociacion.pago.confirmar',
        'negociacion.mensaje.usar',
        'negociacion.preferencias.gestionar',
        'calificaciones.calificacion.calificar_cliente',
        'calificaciones.calificacion.ver_recibidas',
        'calificaciones.calificacion.reportar',
        'disputas.disputa.gestionar_proveedor',
    ],

    // Piloto con cuenta propia de un tercero: lo que hace desde `agrocom-field`.
    'piloto' => [
        'proveedores.cuenta.ver',
        'proveedores.documento.cargar',
        'negociacion.mercado.ver',
        'negociacion.oferta.proveedor',
        'negociacion.seguimiento.registrar',
        'negociacion.seguimiento.ver',
        'negociacion.evidencia.subir',
        'negociacion.pago.confirmar',
        'negociacion.mensaje.usar',
        'negociacion.preferencias.gestionar',
        'calificaciones.calificacion.calificar_cliente',
        'calificaciones.calificacion.ver_recibidas',
        'calificaciones.calificacion.reportar',
        'disputas.disputa.gestionar_proveedor',
    ],
];
