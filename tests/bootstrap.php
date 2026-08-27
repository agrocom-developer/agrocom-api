<?php

/*
 * Bootstrap de test dedicado (en vez de apuntar `phpunit.xml` directo a
 * vendor/autoload.php). Hace falta porque `docker-compose.yml` fija
 * DB_CONNECTION=pgsql (y el resto de credenciales) como variable de entorno
 * REAL del contenedor, y el `<env force="true">` de phpunit.xml solo
 * garantiza `putenv()`/`getenv()` — el helper `env()` de Laravel lee de
 * `$_ENV`/`$_SERVER`, que quedan con el valor real del contenedor desde
 * antes de que PHPUnit toque nada. Sin este bootstrap, la suite de Feature
 * tests corre contra la base de Postgres real de desarrollo (la borra en
 * cada corrida vía RefreshDatabase) en vez de la sqlite en memoria prevista.
 */

$overrides = [
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
];

foreach ($overrides as $clave => $valor) {
    putenv("{$clave}={$valor}");
    $_ENV[$clave] = $valor;
    $_SERVER[$clave] = $valor;
}

require __DIR__.'/../vendor/autoload.php';
