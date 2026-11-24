<?php

namespace App\Dominios\Calificaciones\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio (especificación §7): el cliente calificó al proveedor, al piloto
 * o al auxiliar, o el proveedor calificó al cliente. Solo avisa al proveedor cuando
 * lo califican a él: la calificación al cliente es privada (ADR 0031). DTO de
 * primitivos.
 */
final readonly class CalificacionRegistrada implements ShouldDispatchAfterCommit
{
    /**
     * @param  list<int>  $usuariosDestinatarios  cuentas a las que se avisa; vacío si la calificación es privada.
     */
    public function __construct(
        public int $calificacionId,
        public int $solicitudId,
        public string $autorRol,
        public string $sujetoTipo,
        public int $sujetoId,
        public ?int $proveedorId,
        public int $estrellas,
        public array $usuariosDestinatarios,
    ) {}
}
