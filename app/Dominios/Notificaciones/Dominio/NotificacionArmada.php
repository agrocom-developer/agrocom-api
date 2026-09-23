<?php

namespace App\Dominios\Notificaciones\Dominio;

/**
 * Lo que una regla decide de un hecho, todavía sin cuentas: qué aviso es, con
 * qué parámetros se escribe, a qué recurso lleva y a quiénes se dirige.
 *
 * `claveEvento` es la identidad del hecho y el corazón de la idempotencia
 * (ADR 0025 punto 5): el mismo hecho produce siempre la misma clave, así que
 * repartirlo dos veces deja las mismas filas. Se arma con el id del recurso
 * que nació o cambió (`contrato_creado:12`), nunca con la hora.
 */
final readonly class NotificacionArmada
{
    /**
     * @param  array<string, string|int>  $parametros  lo que el texto necesita, ya tomado del payload del evento
     * @param  list<Destinatario>  $destinatarios
     */
    public function __construct(
        public TipoNotificacion $tipo,
        public string $claveEvento,
        public array $parametros,
        public RecursoNotificable $recurso,
        public int $recursoId,
        public array $destinatarios,
    ) {}

    public static function claveDe(TipoNotificacion $tipo, int $recursoId): string
    {
        return "{$tipo->value}:{$recursoId}";
    }
}
