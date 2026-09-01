<?php

namespace App\Dominios\Compartido\Infraestructura\Eloquent;

/**
 * Enlaza {@see BitacoraObserver} al modelo que la declara — la bitácora de
 * auditoría transversal del ADR 0007 (invariante 9 de CLAUDE.md). Trait
 * "colgable por modelo" (`use RegistraBitacora;`), no un mecanismo que se
 * activa solo: ningún caso de uso llama a la bitácora a mano, así un agente
 * de IA no puede "olvidarlo" al escribir uno nuevo — es exactamente la
 * alternativa que el ADR 0007 descartó.
 *
 * Registra el observer a mano, evento por evento, en vez de usar el
 * `static::observe(BitacoraObserver::class)` de Eloquent: ese helper hace
 * `new static` para resolver los métodos observables
 * (`Concerns\HasEvents::observe()`), y llamado desde el propio
 * `bootRegistraBitacora()` de un modelo eso intenta re-arrancar la MISMA
 * clase mientras `bootIfNotBooted()` todavía la tiene marcada como
 * "arrancando" — Eloquent lo rechaza con
 * `LogicException: ... may not be called on model [...] while it is being
 * booted`. Registrar `[BitacoraObserver::class, $evento]` como listener en
 * forma de array no instancia nada ahora: el dispatcher lo resuelve contra
 * el contenedor recién cuando el evento se dispara, con el modelo ya
 * arrancado (`Illuminate\Events\Dispatcher::createClassCallable()`).
 *
 * `tests/Unit/BitacoraAuditoriaTest.php` decide, a partir del esquema real de
 * cada tabla, qué modelos están obligados a declarar este trait — y falla si
 * a alguno le falta.
 */
trait RegistraBitacora
{
    public static function bootRegistraBitacora(): void
    {
        foreach (['created', 'updated', 'deleted'] as $evento) {
            static::registerModelEvent($evento, [BitacoraObserver::class, $evento]);
        }
    }
}
