<?php

namespace App\Dominios\Seguridad\Contratos;

/**
 * Datos de cuenta que las pantallas del marketplace necesitan mostrar (nombre,
 * login, correo y si está habilitada) sin importar `SecUser`.
 */
interface LecturaCuentas
{
    /**
     * @param  list<int>  $ids
     * @return array<int, DatosCuenta> indexado por id de cuenta; los ids que no existen no aparecen.
     */
    public function porIds(array $ids): array;

    /** Id de la cuenta viva con ese `username`, o `null` si no existe. */
    public function idPorUsername(string $username): ?int;
}
