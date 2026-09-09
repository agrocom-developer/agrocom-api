<?php

namespace App\Dominios\Compartido\Contratos;

/**
 * Frontera de `Compartido` hacia el resto de los módulos (ADR 0003, regla 2)
 * para leer una clave de `/panel/configuracion` (tarea 78, HU-55) sin tocar
 * `Configuracion` ni saber que existe una tabla, cifrado, o un `.env` de
 * respaldo detrás. La tarea 79 (proveedor de mapas configurable) es el primer
 * consumidor; cualquier módulo futuro que necesite una llave o token pide por
 * acá.
 *
 * Resolución en cascada (nunca al revés): fila en la base primero, `.env`/
 * `config()` como respaldo, `null` si no hay ninguno de los dos — nunca una
 * excepción. Un sistema sin llave de mapas sigue funcionando con el proveedor
 * por defecto; es responsabilidad del consumidor, no de este contrato,
 * decidir qué hacer con un `null`.
 */
interface LecturaConfiguracion
{
    public function valor(string $clave): ?string;
}
