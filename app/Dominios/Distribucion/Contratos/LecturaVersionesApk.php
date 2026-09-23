<?php

namespace App\Dominios\Distribucion\Contratos;

/**
 * Frontera de lectura de Distribucion hacia el panel (ADR 0003, regla 2;
 * tarea 139): qué versión del APK está autorizada hoy y cuáles esperan el visto
 * bueno. Solo lee; autorizar sigue pasando por la máquina de estados de la
 * versión (invariante 7).
 */
interface LecturaVersionesApk
{
    /** La versión autorizada hoy (a lo sumo una), o `null` si ninguna lo está. */
    public function vigente(): ?VersionApkPanel;

    /**
     * Las versiones que esperan autorización, de la más nueva a la más vieja.
     *
     * «Pendiente» no alcanza para decirlo: al autorizar una versión nueva la
     * anterior vuelve a `pendiente` (no a `rechazada`), así que una versión más
     * vieja que la vigente también figura como pendiente sin que nadie espere
     * autorizarla. Acá solo entran las más nuevas que la vigente — o todas las
     * pendientes si no hay ninguna vigente.
     *
     * @return list<VersionApkPanel>
     */
    public function pendientesDeAutorizar(): array;
}
