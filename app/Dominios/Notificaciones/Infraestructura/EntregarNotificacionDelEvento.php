<?php

namespace App\Dominios\Notificaciones\Infraestructura;

use App\Dominios\Notificaciones\Aplicacion\EntregarNotificacion;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * El único listener del motor (ADR 0025 punto 4): atiende TODOS los eventos de
 * dominio que tienen una regla, en vez de un closure por `ServiceProvider`. El
 * `NotificacionesServiceProvider` lo registra una vez por evento; lo que cambia
 * de un evento a otro vive en su regla, no acá.
 *
 * Dos decisiones de este archivo, las dos del ADR:
 *
 * - **Quien dispara el hecho no se avisa a sí mismo**: se excluye a la cuenta
 *   autenticada al momento del evento (la de la sesión del panel o la del token
 *   de dispositivo; sin nadie autenticado —una consola, un job— no se excluye a
 *   nadie).
 * - **Un fallo al avisar no rompe la operación de negocio** (punto 9): el
 *   contrato ya se creó, el trabajo ya se cerró. Cualquier error se reporta y
 *   se sigue; nunca llega como 500 sobre algo ya confirmado, ni aborta la
 *   transacción con que la app de campo sincroniza.
 */
final class EntregarNotificacionDelEvento
{
    public function __construct(private readonly EntregarNotificacion $entregar) {}

    public function handle(object $evento): void
    {
        try {
            $this->entregar->ejecutar($evento, $this->cuentaQueDisparo());
        } catch (Throwable $excepcion) {
            report($excepcion);
        }
    }

    private function cuentaQueDisparo(): ?int
    {
        $identificador = Auth::id();

        return $identificador === null ? null : (int) $identificador;
    }
}
