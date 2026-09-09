<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Compartido\Dominio\AccionBitacora;
use Illuminate\Support\Carbon;

/**
 * Fila de la pantalla `/panel/bitacora` (tarea 63), ya resuelta para
 * pintarse sin que la vista calcule nada: el instante llega convertido a la
 * zona del que mira (nunca UTC crudo), el actor ya resuelto a nombre +
 * username, la entidad a su nombre legible, y el diff reducido a los campos
 * que de verdad cambiaron — que es exactamente lo que `antes`/`despues` ya
 * contienen, `BitacoraObserver` nunca guarda la fila entera ni un secreto
 * (ver `Configuracion::columnasSensiblesBitacora()`). No hay nada que la
 * vista tenga que excluir a mano: si una clave no está en `$diff`, es
 * porque nunca estuvo en `antes`/`despues`.
 */
final readonly class FilaBitacora
{
    /**
     * @param  string  $offset  Ej. "UTC−4", ya calculado sobre `$instante` (DST
     *                          incluido — lo resuelve la base IANA de PHP, no un
     *                          offset fijo).
     * @param  ?string  $zonaRegistrada  Zona IANA en la que ocurrió la mutación,
     *                                   SOLO si es distinta de la del que mira
     *                                   (si coincide o no hay dato, `null` — nada
     *                                   que aclarar).
     * @param  ?string  $actorNombre  `null` si `user_id` es NULL (mutación sin
     *                                sesión: seeder, comando) — la vista imprime
     *                                "Sistema" vía `__()`, no este DTO.
     * @param  list<array{campo: string, antes: mixed, despues: mixed}>  $diff
     */
    public function __construct(
        public int $id,
        public Carbon $instante,
        public string $offset,
        public ?string $zonaRegistrada,
        public ?string $actorNombre,
        public ?string $actorUsername,
        public string $tabla,
        public string $tablaLegible,
        public int $registroId,
        public AccionBitacora $accion,
        public array $diff,
    ) {}
}
