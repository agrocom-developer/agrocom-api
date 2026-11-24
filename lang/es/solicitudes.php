<?php

/*
 * Solicitudes del marketplace (ADR 0027): por ahora el cliente y las zonas.
 */
return [
    'campos' => [
        'tipo' => 'tipo de cliente',
        'nombre' => 'nombre',
        'username' => 'usuario',
        'email' => 'correo',
        'password' => 'contraseña',
        'telefono' => 'teléfono',
        'razon_social' => 'razón social',
        'documento_fiscal' => 'documento fiscal',
    ],

    'errores' => [
        'documento_fiscal_duplicado' => 'El documento fiscal :documento ya pertenece a otro cliente.',
        'sin_cliente' => 'Tu cuenta no tiene un perfil de cliente.',
    ],
];
