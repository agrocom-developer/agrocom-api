<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Los tests de Feature extienden el TestCase de Laravel (levantan la app);
| los de Unit son PHPUnit puro. Descripciones de tests en español, igual
| que el vocabulario de dominio (CLAUDE.md).
|
*/

pest()->extend(TestCase::class)
    // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/**
 * Request de la app de campo firmado con el token de un dispositivo.
 *
 * El `forgetGuards()` no es cosmético: dentro de un mismo test el contenedor
 * es el mismo entre requests y `RequestGuard` cachea el usuario que ya
 * resolvió, así que sin esto un segundo request reusaría la autenticación del
 * primero y "revocar, volver a pedir" daría 200 con un token ya muerto. En
 * producción cada request arranca con el contenedor limpio.
 */
function comoDispositivo(string $token): TestCase
{
    app('auth')->forgetGuards();

    return test()->withHeader('Authorization', "Bearer {$token}");
}
