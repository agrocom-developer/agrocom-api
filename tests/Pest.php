<?php

use PHPUnit\Framework\ExpectationFailedException;
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

/**
 * El cierre de la aserción de un rechazo de dominio sin atarse al idioma de la
 * prueba: la excepción es de la clase esperada y su mensaje es el de la clave de
 * `lang/es` (con sus `:marcadores` abiertos), traducida o no según el entorno.
 *
 *     expect(fn () => $caso->ejecutar(...))->toRechazarCon(CalificacionInvalida::class, 'calificaciones.errores.plazo_vencido');
 */
expect()->extend('toRechazarCon', function (string $excepcion, string $clave) {
    try {
        ($this->value)();
    } catch (Throwable $encontrada) {
        expect($encontrada)->toBeInstanceOf($excepcion);

        $plantilla = preg_quote((string) __($clave), '/');
        $patron = '/^'.preg_replace('/\\\\:[a-z_]+/', '.+', $plantilla).'$/su';

        expect($encontrada->getMessage())->toMatch($patron);

        return $this;
    }

    throw new ExpectationFailedException("No se lanzó {$excepcion}.");
});
