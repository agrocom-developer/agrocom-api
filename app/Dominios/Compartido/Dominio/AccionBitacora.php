<?php

namespace App\Dominios\Compartido\Dominio;

/**
 * Acción registrada por la bitácora de auditoría transversal (ADR 0007,
 * invariante 9 de CLAUDE.md). Los tres eventos de ciclo de vida que
 * `BitacoraObserver` escucha: alta, modificación y borrado lógico — nunca
 * borrado físico, que `ModeloDominio` bloquea antes de que exista algo que
 * auditar.
 *
 * Sin caso `Restaurado` a propósito: `SoftDeletes::restore()` persiste con
 * `save()`, así que una restauración ya dispara el evento `updated` de
 * Eloquent (`BitacoraObserver::updated()`) y queda registrada como
 * `Actualizado` con `deleted_at` pasando de una fecha a NULL en el
 * antes/después — un caso `Restaurado` aparte duplicaría la fila sin agregar
 * información, o exigiría un mecanismo con estado solo para un camino que
 * hoy ningún caso de uso ejercita.
 */
enum AccionBitacora: string
{
    case Creado = 'creado';
    case Actualizado = 'actualizado';
    case Eliminado = 'eliminado';
}
