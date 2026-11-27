<?php

namespace App\Dominios\Seguridad\Contratos;

/**
 * Lo que cada módulo aporta a la eliminación de una cuenta (ADR 0047): qué la impide, qué se deshace al pedirla y qué datos
 * personales anonimiza o borra al ejecutarla. Seguridad no conoce a los demás módulos: cada uno se ofrece etiquetando su
 * implementación `eliminacion.participantes` (ADR 0003, regla 2).
 */
interface ParticipanteDeEliminacion
{
    /**
     * Motivos que impiden eliminar la cuenta ahora: `servicio_en_curso`, `reclamo_abierto`, `pilotos_vinculados`.
     *
     * @return list<string>
     */
    public function impedimentos(int $usuarioId): array;

    /** Efectos inmediatos al pedirla (cancelar solicitudes abiertas, retirar ofertas). No se deshacen si la persona se arrepiente. */
    public function alSolicitar(int $usuarioId): void;

    /** Anonimiza o borra los datos personales del módulo. La llama una transacción y debe ser idempotente. */
    public function anonimizar(int $usuarioId): void;
}
